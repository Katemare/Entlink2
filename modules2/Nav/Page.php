<?

// суть класса двойная: во-первых, представлять понятие страницы; во-вторых, выполнять шаги, общие для всей работы с данной страницей - в частности, ввод и проверка пригодности ввода.
// страницу можно получить двумя способами: запрос по URL (разбирает Router) или запрос у сущности, модуля или другого объекта. В первом случае обычно запрашивает пользователь и хочет увидеть страницу или выполнить связанную с ней операцию; во втором случае обычно требуется получить адрес, название, расположение или другие параметры страницы - скажем, ссылку на профиль покемона, упомянутого на странице.
// как правило, классы страниц должны полагаться на данные модели во всём, касающимся специфики выполнения. только некоторые специфические классы странц могут наверняка знать единственную цель, для которой они применяются.

abstract class Page extends Task implements Pathway, SiteNode
{
	use Task_steps;

	const
		STEP_GET_INPUT=0,
		STEP_ANALYZE_INPUT=1,
		STEP_ANALYZE_CONTENT=2,
		STEP_FINISH=3,
		
		PRIORITY_STANDARD_FRAMEWORK=0,
		PRIORITY_CONSTRUCT_LAYOUT=10,
		PRIORITY_INTERACTION_FRAMEWORK=20,
		PRIORITY_LAST=30,
		PRIORITY_DEFAULT=Page::PRIORITY_LAST,
		
		INPUTSET_CLASS	='InputSet',
		PROCESSOR_CLASS	='PageProcessor',	// replace_me
		LOCATOR_CLASS	='PageLocator',
		TITLE_CLASS		='PageTitle',
		BREADCRUMBS_TASK='PageBreadcrumbs',
		
		UNTITLED_PAGE_KEY='standard.untitled_page';
	
	public
		$page_tasks=[],	// к странице обращаются разными задачами - адрес, загловок, содеримое... задачи кэшируются здесь.
		$error=null,		// если ввод или данные неверные, сюда записывается код ошибки.
		$input_model=[],	// модель для пользовательского ввода из GET/POST (обычно)
		$direct_input=false,	// можно ли странице самой вводить данные пользователя или только полагаться на поданное ей движком - query.
		$query_to_input=0, // количество аргументов в $query (см. apply_query()), которые один к одному накладываются на $input_model. остальные считываются по названиям.
		$url_formation=Router::URL_UNKNOWN, // при обращении к странице по URL сюда сохраняется формат URL.
		$proper_url_formation=null,	// формат "правильного", канонического URL.
		$route=null,				// фрагменты пути при обращении по URL.
		$page_model=[],			// данные, по которым была создана страница. обычно содержат класс и ключ шаблона, но иногда и другие параметры, например, данные для правильного URL.
		$input=null,				// сюда записывается объект InputSet для ввода.
		$initial_query=null, // первоначальный ввод из URL, для воспроизведения первоначального URL.
		$pool=EntityPool::MODE_READ_ONLY;
	
	public function pool()
	{
		if (!is_object($this->pool))
		{
			if (EntityPool::default_pool(false)===false)
			{
				$this->pool=new EntityPool($this->pool);
				$this->pool->be_default_pool();
			}
			$this->pool=EntityPool::default_pool();
		}
		return $this->pool;
	}
	
	// для страниц, создающихся по модели, превышающей указание просто класса и ключа.
	public function apply_model($model)
	{
		if (empty($model)) return;
		$this->page_model=array_merge($this->page_model, $model);
		$this->use_model();
	}
	
	public function use_model() { }
	
	public function record_route($route)
	{
		if (!is_array($route)) $route=['url_formation'=>$route];
		$this->route=[];
		if (array_key_exists('url_formation', $route)) $this->url_formation=$route['url_formation'];
		if ($this->url_formation===Router::URL_UNKNOWN) return;
		if (empty(Router::$url_formations[$this->url_formation])) return;
		foreach (Router::$url_formations[$this->url_formation]['parts'] as $code)
		{
			if (array_key_exists($code, $route)) $this->route[$code]=$route[$code];
		}
	}
	
	public function relevant_module()
	{
		if (!empty($this->module_slug)) return Engine()->module_by_slug($this->module_slug);
		if (!array_key_exists('module_slug', $this->route)) return Engine()->module_by_slug($this->route['module_slug']);
	}
	
	public function apply_query($query)
	{
		$this->initial_query=$query;
		$inputset=$this->get_inputset();
		if (empty($inputset)) return;
		
		reset($query);
		if ($this->query_to_input>0)
		{
			$model=$inputset->model;
			// у некоторых ValueSet'ов модель наполняется по мере запроса к методу model(). однако здесь предполагается, что мы можем соотнести упорядоченные аргументы в query с кодами в модели ввода - именно из этого предположения следует назначать InputSet'ы таким страницам.
			for
			(
				$value=current($query), $x=key($query), reset($model), $key=key($model);
				$x!==false && $x<$this->query_to_input && $key!==false;
				$value=next($query), $x=key($query), next($model), $key=key($model)
			)
			{
				$inputset->set_value($key, $query[$x], Value::BY_INPUT);
			}
		}
		for
		(
			$key=current($query), $value=next($query);
			$key!==false && $value!==false;
			$key=next($query), $value=next($query)
		)
		{
			if (!$this->model_code_exists($key)) continue;
			$inputset->set_value($key, $value, Value::BY_INPUT);
		}
	}
	
	public function allow_direct_input()
	{
		$this->direct_input=true;
	}
	public function forbid_direct_input()
	{
		$this->direct_input=false;
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_GET_INPUT)
		{
			$this->get_inputset(); // чтобы произвести объект inputset, даже если ввода не поставлено.
			if (!$this->direct_input) return $this->advance_step(); // ввод уже осуществлён из query.
			$report=$this->fill_input();
			if ($report instanceof Report_impossible) return $this->advance_step();
			return $report;
		}
		elseif ($this->step===static::STEP_ANALYZE_INPUT)
		{
			if ($this->input===null) return $this->advance_step();
			return $this->analyze_input();
		}
		elseif ($this->step===static::STEP_ANALYZE_CONTENT)
		{
			return $this->analyze_content();
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			return $this->sign_report(new Report_success());
		}
	}
	
	public function supply_input_model() { }
	
	public function fill_input()
	{
		$inputset=$this->get_inputset();
		if (empty($inputset)) return $this->advance_step();
		$result=$inputset->input();
		if ($result instanceof Report_success) return $this->advance_step();
		return $result;
	}
	
	public function get_inputset()
	{
		if ($this->input===null) $this->input=$this->create_inputset();
		return $this->input;
	}
	
	public function create_inputset()
	{
		$this->supply_input_model();
		if (empty($this->input_model)) return false;
		$class=static::INPUTSET_CLASS;
		return $class::from_model($this->input_model);
	}
	
	public function analyze_input()
	{
		return $this->advance_step();
	}
	
	// возвращает ответ в формате run_step()
	public function record_error($error)
	{
		$this->error=$error;
		return $this->advance_step();  // ERR: пока нет обработки ошибок
	}
	
	public function analyze_content()
	{
		if (($rightful=$this->rightful())!==true) return $this->record_error( ($rightful===false)?('unauthorized'):($rightful) );
		return $this->advance_step();
	}
	
	public function rightful()
	{
		return true;
	}
	
	public function redirect($address, $args=null)
	{
		Router()->redirect($address, $args);
	}
	
	public function redirect_by_input($address, $more_args=null)
	{
		if (empty($this->input)) $this->redirect($address, $more_args);
		$this->redirect($address, $this->input->make_url_args($more_args));
	}
	
	public function redirect_change_arguments($new_arguments)
	{
		$this->redirect(Router()->url_args_only($new_arguments));
	}
	
	public function get_back($add=null)
	{
		Router()->get_back($add);
	}
	
	public
		$requirements=[];
	public function register_requirement($domain, $req, $priority=Page::PRIORITY_DEFAULT)
	{
		if (!array_key_exists($domain, $this->requirements)) $this->requirements[$domain]=[];
		elseif (array_key_exists($req, $this->requirements[$domain])) return;
		$this->requirements[$domain][$req]=$priority;
	}
	public function register_requirements($domain, $reqs /* в формате адрес=>приоритет */)
	{
		if (!array_key_exists($domain, $this->requirements)) $this->requirements[$domain]=[];
		$this->requirements[$domain]+=$reqs;
	}
	
	public function get_page_task($code)
	{
		if (empty($this->page_tasks[$code]))
		{
			$const_name=$code.'_CLASS';
			$class=constant(get_class($this).'::'.$const_name);
			if (empty($class)) die('BAD PAGE TASK');
			$task=$class::for_page($this);
			$this->page_tasks[$code]=$task;
		}
		return $this->page_tasks[$code];
	}
	
	public function get_processor_task()
	{
		return $this->get_page_task('PROCESSOR');
	}
	
	public function get_locator_task()
	{
		return $this->get_page_task('LOCATOR');
	}
	
	public function get_title_task()
	{
		return $this->get_page_task('TITLE');
	}
	
	//  реализация интерфейса SiteNode
	public function node_url_template() { return $this->get_locator_task(); }
	public function node_title_template() { return $this->get_title_task(); }
	
	// последующие три метода вызываются по крайней мере после завершения подготовки страницы (когда данная задача становится законченной).
	
	public function url()
	{
		if (empty($this->proper_url)) $this->proper_url=$this->generate_proper_url();
		return $this->proper_url;
	}
	
	public function actual_url()
	{
		if ( ($this->url_formation===Router::URL_UNKNOWN) || ($this->initial_query===null) ) return $this->sign_report(new Report_impossible('no_definite_url'));
		return Router()->compose_router_url($this->url_formation, $this->route, $this->initial_query);
	}
	
	# вернёт строку адреса, если необходим редирект. Например:
	# /adopts/pokemon/999/Zulu	- правильный URL страницы про тренерского покемона № 999 по имени Зулу.
	# /adopts/pokemon/999		- допустимый URL на того же покемона.
	# /adopts/pokemon/999/Meow	- требует редиректа на правильный URL, поскольку имя не совпадает.
	# /adopts/shelter/999		- требует редиректа на правильный URL, поскольку покемон не находится в приюте (возможно, был взят)
	public function needs_redirect() { }
	
	// "правильный url" отличается от url, по которому состоялось обращение тем, что он приведён в каноническую форму: определённый порядок аргументов, только предпочтительные ключевые слова, правильная подсказка, если требуется...
	public function generate_proper_url()
	{
		$url_formation=null;
		if (!empty($this->proper_url_formation)) $url_formation=$this->proper_url_formation;
		elseif ($this->url_formation!==Router::URL_UNKNOWN) $url_formation=$this->url_formation;
		else return $this->sign_report(new Report_impossible('no_definite_url'));
		
		$parts=Router::$url_formations[$url_formation];
		$route=[];
		foreach ($parts as $part)
		{
			$try = $this->url_part_from_model($part) || $this->url_part_from_route($part) || $this->generate_url_part($part);
			if (empty($try)) return $this->sign_report(new Report_impossible('cant_generate_url'));
			else $this->route[$part]=$try;
		}
		$query=$this->input_to_query();
		return Router()->compose_router_url($url_formation, $route, $query);
	}
	
	public function url_part_from_route($part)
	{
		if (array_key_exists($part, $this->route)) return $this->route[$part];
	}
	
	public function url_part_from_model($part)
	{
		if (!array_key_exists($part, $this->page_model)) return;
		$from_model=$this->page_model[$part];
		if (is_array($from_model)) return reset($from_model);
		return $from_model;
	}
	
	// страница общего характера не может догадаться о том, по какому пути ей положено находиться, но у специфических страниц могут быть догадки.
	public function generate_url_part($part) { }
	
	public function input_to_query()
	{
		$inputset=$this->get_inputset();
		if (empty($inputset)) return [];
		
		$result=[];
		$non_default_index=null;
		$current_index=null;
		$input_model=$inputset->model;
		reset($input_model);
		for
		(
			$x=0, $code=key($input_model);
			$x<$this->query_to_input && $code!==false;
			$x++, next($input_model), $code=key($input_model)
		)
		{
			$current_index=$x;
			$value=$this->produce_value($code);
			if ($value->has_state(Value::STATE_FILLED)) $result[]=$value->for_input();
			if ($value->has_nondefault_content()) $non_default_index=$current_index;
			else $result[]='';
		}
		
		for
		(
			$code=key($input_model);
			$code!==false;
			next($input_model), $code=key($input_model)
		)
		{
			$value=$this->produce_value($code);
			if (!$value->has_state(Value::STATE_FILLED)) continue;
			
			if ($current_index===null) $current_index=1;
			else $current_index+=2;
			if ($value->has_nondefault_content()) $non_default_index=$current_index;
			
			$result[]=$code;
			$result[]=$value->for_input();
		}
		
		if ($non_default_index===null) return [];
		if ($non_default_index<$current_index) $result=array_slice($result, 0, $non_default_index+1);
		return $result;
	}
	
	public function immediate_title()
	{
		$template=Template_from_db::with_db_key(static::UNTITLED_PAGE_KEY);
		$template->context=$this;
		return $template;
	}
	
	public function generate_breadcrumbs()
	{
		return [];
	}
	
	public function follow_track($track)
	{
		return $this->sign_report(new Report_impossible('no_path'));
	}
}

?>