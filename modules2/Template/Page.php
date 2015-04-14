<?

abstract class Page extends Task implements Pathway
{
	use Task_steps, Report_spawner;

	const
		STEP_GET_INPUT=0,
		STEP_ANALYZE_INPUT=1,
		STEP_ANALYZE_CONTENT=2,
		STEP_PROCESS=3,
		
		PRIORITY_STANDARD_FRAMEWORK=0,
		PRIORITY_CONSTRUCT_LAYOUT=10,
		PRIORITY_INTERACTION_FRAMEWORK=20,
		PRIORITY_LAST=30,
		PRIORITY_DEFAULT=Page::PRIORITY_LAST,
		
		INPUTSET_CLASS='InputSet';
	
	public
		$error=null,
		$input_model=[],
		$input=null,
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
	
	public function __construct()
	{
		parent::__construct();
		$this->pool();
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_GET_INPUT)
		{
			$this->supply_input_model();
			if (empty($this->input_model)) return $this->advance_step();
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
		elseif ($this->step===static::STEP_PROCESS)
		{
			return $this->process();
		}
	}
	
	public function supply_input_model()
	{
	}
	
	public function fill_input()
	{
		$this->input=$this->create_inputset();
		$result=$this->input->input();
		if ($result instanceof Report_success) return $this->advance_step();
		return $result;
	}
	
	public function create_inputset()
	{
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
	
	public abstract function process(); // должно вернуть ответ в формате метода run_step()! разрешается вместо этого сделать немедленный редирект.
	
	public function redirect($address, $args=null)
	{
		Engine()->redirect($address, $args);
	}
	
	public function redirect_by_input($address, $more_args=null)
	{
		if (empty($this->input)) $this->redirect($address, $more_args);
		$this->redirect($address, $this->input->make_url_args($more_args));
	}
	
	public function redirect_change_arguments($new_arguments)
	{
		$this->redirect(Engine()->url_args_only($new_arguments));
	}
	
	public function get_back($add=null)
	{
		Engine()->get_back($add);
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
	
	public function follow_track($track)
	{
		return $this->sign_report(new Report_impossible('no_path'));
	}
}

// покзывает стандартную страницу сайта в заданном дизайне.
abstract class Page_view extends Page implements Templater, ValueHost
{
	use ValueHost_standard
	{
		ValueHost_standard::ValueHost_request as std_ValueHost_request;
	}

	const
		STEP_DISPLAY=4; // Page::STEP_PROCESS+1;

	public
		$template_class='replace_me',
		$master_template,
		$content=null,
		$cached=false;
	
	public function run_step()
	{
		if ($this->step===static::STEP_DISPLAY)
		{
			$this->display();
			return $this->sign_report(new Report_success());
		}
		else return parent::run_step();
	}
	
	public function display()
	{
		$this->send_header();
		echo $this->master_template->resolution;
	}
	
	public function send_header()
	{
		header('Content-Type: text/html; charset=utf-8'); // STUB	
	}
	
	public function process()
	{
		$template=$this->create_master_template();
		$this->setup_master_template($template);
		$this->master_template=$template;
		return $this->sign_report(new Report_task($this->master_template));
	}
	
	public function create_master_template()
	{
		$class=$this->template_class;
		$template=$class::blank();
		if (!($template instanceof Template_page)) die ('BAD PAGE TEMPLATE');
		return $template;
	}
	
	public function setup_master_template($template)
	{
		$template->page=$this;
		$template->initiated();
	}
	
	public function content()
	{
		if ($this->content===null)
		{
			$cache_key=$this->cache_key(); // STUB - быстрый костыль кэша
			
			if (is_array($cache_key))
			{
				$query=
				[
					'action'=>'select',
					'table'=>'info_cache',
					'where'=>
					[
						'code'=>$cache_key['code'],
						'num'=>$cache_key['num'],
						['expression'=>'({{expires}} IS NULL OR {{expires}}>UNIX_TIMESTAMP())']
					]
				];
				$result=Retriever()->run_query($query);
				if ( (is_array($result)) && (!empty($result)) )
				{
					$result=reset($result);
					$this->cached=true;
					return $result['content'];
				}
			}
			if ($this->error!==null) $content=$this->create_error_content();
			else $content=$this->create_content();
			
			if ($content===null) die('NULL CONTENT');
			
			$this->content=$content;
			$this->set_requirements();
			
			if (is_array($cache_key))
			{
				if (!is_object($content)) $this->save_cache();
				elseif ($content instanceof Task)
				{
					$content->add_call
					(
						function() { $this->save_cache(); },
						'complete'
					);
				}
			}
		}
		return $this->content;
	}
	
	public function save_cache()
	{
		$cache_key=$this->cache_key();
		if (!is_array($cache_key)) return;
		
		$content=$this->content();
		if (!is_object($content)) $save=$content;
		elseif ( ($content instanceof Task) && ($content->completed()) ) $save=$content->resolution; // ERR: нет обработки ошибки, когда задача завершилась с ошибкой!
		else die ('BAD CONTENT');
		
		if (array_key_exists('expires',  $cache_key)) $expires=$cache_key['expires'];
		else $expires=null;
		
		$query=
		[
			'action'=>'replace',
			'table'=>'info_cache',
			'value'=>
			[
				'code'=>$cache_key['code'],
				'num'=>$cache_key['num'],
				'expires'=>$expires,
				'content'=>$save
			]
		];
		Retriever()->run_query($query);
	}
	
	public function create_error_content()
	{
		return 'ERROR: '.$this->error;
	}
	
	public abstract function create_content();
	
	public function set_requirements() { }
	
	public $cache_key=null;
	public function cache_key()
	{
		if (is_null($this->cache_key)) $this->cache_key=$this->make_cache_key();
		return $this->cache_key;
	}
	
	public function make_cache_key()
	{
		return false;
	}
	
	// реализует интерфейс Templater
	public function template($code, $line=[])
	{
		if ($this->input===null) return;
		$value=$this->input->produce_value_soft($code);
		if ($value instanceof Templater) return $value->template(null, $line);
	}
	
	// реализует интерфейс ValueHost
	public function request($code)
	{
		if ($this->input===null) return $this->ValueHost_request($code);
		$value=$this->input->produce_value_soft($code);
		if ($value instanceof Report_impossible) return $this->ValueHost_request($code);
		return $value->request();
	}
	
	public function value($code)
	{
		if ($this->input===null) return $this->ValueHost_value($code);
		$value=$this->input->produce_value_soft($code);
		if ($value instanceof Report_impossible) return $this->ValueHost_value($code);
		return $value->value();
	}
	
	// STUB!
	public function ValueHost_request($code)
	{
		if ($code==='admin') return $this->sign_report(new Report_resolution(Module_AdoptsGame::instance()->admin()));
		if ($code==='logged_in') return User::logged_in();
		if ($code==='user') return User::current_user();
		return $this->std_ValueHost_request($code); 
	}
	
	public function follow_track($track)
	{
		return $this->request($track);
	}
}

// для тестирования.
class Page_view_contentless extends Page_view
{
	public
		$master_template;

	public static function from_template($template)
	{
		$page=new static();
		$page->master_template=$template;
		return $page;
	}
	
	public function create_master_template()
	{
		return $this->master_template;
	}

	public function create_content()
	{
		die('WHAT CONTENT');
	}
}

// страница, содержимое которой описывается шаблоном Template_from_db или его наследником.
class Page_view_from_db extends Page_view
{
	const
		STEP_PRE_REQUEST_TEMPLATE_CACHE=3,
		STEP_PRE_REQUEST_TEMPLATES=4,
		STEP_PROCESS=5,
		STEP_DISPLAY=6,
		STEP_SAVE_TEMPLATE_CACHE=7,
		STEP_FINISH=8;

	public
		$master_db_key=null,
		$content_class='Template_from_db',
		$error_content_class='Template_from_db',
		$db_key=null,
		$db_key_base=null,
		
		$cached_templates=[],
		$cached_templates_task;

	public function run_step()
	{
		if ($this->step===static::STEP_PRE_REQUEST_TEMPLATE_CACHE)
		{
			if ($this->error!==false) return $this->advance_step(static::STEP_PROCESS);
			$key=$this->template_cache_key();
			if ($key===null) return $this->advance_step(static::STEP_PROCESS);
			$this->cached_templates_task=Task_retrieve_cache::with_cache_key($key);
			return $this->sign_report(new Report_task($this->cached_templates_task));
		}
		elseif ($this->step===static::STEP_PRE_REQUEST_TEMPLATES)
		{
			if ($this->cached_templates_task->failed()) return $this->advance_step();
			$result=explode(',', $this->cached_templates_task->resolution);
			if ($result===false) return $this->advance_step();
			
			$this->cached_templates=$result;
			$report=$this->templates_request()->get_data_set($this->cached_templates);
			if ($report instanceof Report_final) return $this->advance_step();
			return $report;
		}
		elseif ($this->step===static::STEP_DISPLAY)
		{
			$report=parent::run_step();
			if ($report instanceof Report_success) return $this->advance_step();
			return $report;
		}
		elseif ($this->step===static::STEP_SAVE_TEMPLATE_CACHE)
		{
			if (empty($this->cached_templates_task)) return $this->sign_report(new Report_success());
			
			$used_templates=array_keys($this->templates_request()->data);
			sort($used_templates);
			if ($used_templates==$this->cached_templates) return $this->sign_report(new Report_success());
			
			$this->cached_templates=array_unique(array_merge($this->cached_templates, $used_templates));
			sort($this->cached_templates);
			return $this->cached_templates_task->save_cache(implode(',', $this->cached_templates));
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			return $this->sign_report(new Report_success());
		}
		else return parent::run_step();
	}
	
	public function templates_request()
	{
		return Request_by_unique_field::instance(Template_from_db::TEMPLATE_TABLE, Template_from_db::KEY_FIELD);
	}
	
	public function template_cache_key()
	{
		$key=get_class($this);
		$db_key=$this->default_db_key();
		if (!empty($db_key)) $key.='|'.$db_key;
		
		return ['code'=>'PgTpl::'.$key, 'expires'=>2592000 /* месяц */ ];
	}
		
	public function setup_master_template($template)
	{
		if ( ($template instanceof Template_from_db) && ($this->master_db_key!==null) )  $template->db_key=$this->master_db_key;
		parent::setup_master_template($template);
	}
	
	public function create_error_content()
	{
		$class=$this->error_content_class;
		$template=$class::blank();
		$this->setup_error_content($template);
		return $template;
	}
	
	public function setup_error_content($template)
	{
		if ($this->db_key_base===null) $base='custom'; else $base=$this->db_key_base;
		$db_key=$base.'.error_'.$this->error;
		$template->db_key=$db_key;
	}
	
	public function create_content()
	{
		$class=$this->content_class;
		$template=$class::blank();
		$this->setup_content($template);
		return $template;
	}
	
	public function setup_content($template)
	{
		$key=$this->default_db_key();
		if ($key!==null) $template->db_key=$key;
	}
	
	public function default_db_key()
	{
		if ($this->db_key===null) return;
		if ($this->db_key_base!==null) return $this->db_key_base.'.'.$this->db_key;
		return $this->db_key;
	}
}

// страница, у которой могут быть стрелки "предыдущее" и "следующее".
interface Page_traversable
{
	// возвращают ссылки на следующее и предыдущее; либо сущности, по смыслу являющиеся соседними.
	public function next();
	public function previous();
}

trait Page_sibling_entities
{
	/*
		public
			$sibling_url_field= ... ;
	*/

	public abstract function traversable_reference();
	
	public function next()
	{
		$reference=$this->traversable_reference();
		if (empty($reference)) return '';
		return $this->pool()->entity_from_provider([$this->sibling_provider_keyword(), $reference, Provide_sibling::DIR_NEXT], $reference->id_group);
	}
	
	public function previous()
	{
		$reference=$this->traversable_reference();
		if (empty($reference)) return '';
		return $this->pool()->entity_from_provider([$this->sibling_provider_keyword(), $reference, Provide_sibling::DIR_PREV], $reference->id_group);
	}
	
	public function sibling_provider_keyword()
	{
		return 'sibling';
	}
	
	public function sibling_url($entity)
	{
		if (empty($this->sibling_url_field)) return $entity->value('profile_url');
		return $entity->value($this->sibling_url_field);
	}
}

// в целях тестирования.
class Page_predefined extends Page_view
{
	public function __construct($content)
	{
		$this->content=$content;
		parent::__construct();
	}

	public function create_content() { die ('SET DONT CREATE'); }
}

abstract class Page_operation extends Page
{
	public
		$pool=EntityPool::MODE_OPERATION;
}

// интерфейс для шаблонов, которые могут служить старшим шаблоном страницы. 
interface Template_page
{
	// константы тут только для удобства обращения.
	const
		ELEMENT_BEGIN='begin',	
		ELEMENT_CONTENT='content',
		ELEMENT_END='end',
		ELEMENT_APPEND='append';
}

// черта для страниц, состоящих из трёх основных компонентов: начала, содержимого и конца.
trait Template_page_trifold
{	
	public function make_template($code, $line=[])
	{
		if ($code===static::ELEMENT_CONTENT) return $this->content();
		if ($code===static::ELEMENT_BEGIN) return $this->begin();
		if ($code===static::ELEMENT_END) return $this->end();
		if ($code===static::ELEMENT_APPEND) return $this->appended();
	}
	
	// не для того, чтобы у этого шаблона могло быть иное содержимое, чем у страницы, а чтобы при создании элемента-содержимого можно было выполнить дополнительные функции, такие как подписка на завершение.
	public function content()
	{
		return $this->page->content();
	}
	
	public function begin()
	{
		return '';
	}
	
	public function end()
	{
		return '';
	}
	
	public $to_append=[];
	public function to_append($template)
	{
		if (is_array($template)) $this->to_append=array_merge($this->to_append, $template);
		else $this->to_append[]=$template;
	}
	
	public function appended()
	{
		$template=Template_composed_appended::blank();
		return $template;
	}
}

class Template_composed_appended extends Template_composed
{
	public $content_checked=false;

	public function progress()
	{
		if ($this->content_checked===false)
		{
			$content=$this->page->content();
			if (!$content->completed()) $this->register_dependancy($content);
			$this->content_checked=true;
			return;
		}
		parent::progress();
	}

	public function spawn_subtasks()
	{
		return $this->page->master_template->to_append;
	}
}

// старший шаблон страницы, складывающий свой текст из трёх компонентов.
class Template_page_composed extends Template_composed implements Template_page
{
	use Template_page_trifold;
	
	public
		$elements=[Template_page::ELEMENT_BEGIN, Template_page::ELEMENT_CONTENT, Template_page::ELEMENT_END, Template_page::ELEMENT_APPEND],
		$order=[Template_page::ELEMENT_BEGIN, Template_page::ELEMENT_CONTENT, Template_page::ELEMENT_APPEND, Template_page::ELEMENT_END];
	
	public function spawn_subtasks()
	{
		$list=[];
		// начало и конец сами, если надо, подпишутся на завершение содержимого.
		foreach ($this->order as $code)
		{
			$template=$this->make_template($code);
			$list[]=$template;
		}
		return $list;
	}
}

// старшый шаблон страницы, берущий свой текст из БД.
class Template_page_from_db extends Template_from_db implements Template_page
{
	use Template_page_trifold;
	
	public
		$elements=[Template_page::ELEMENT_BEGIN, Template_page::ELEMENT_CONTENT, Template_page::ELEMENT_END, Template_page::ELEMENT_APPEND],
		$appendable=true;
}
?>