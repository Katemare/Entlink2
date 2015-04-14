<?
// результатом работы шаблона является строка, предназначенная для демонстрации пользователю тем или иным образом.
abstract class Template extends Task implements Templater, ValueHost, Pathway
{
	use ValueHost_standard;
	
	const
		MASTER_POINTER='#', // через этот префикс в стёке идёт обращение к шаблонам, просто находящимся в БД, не связанным ни с одним шаблонизатором.
		TRACK_PAGE='page',
		TRACK_CONTEXT='context';
		
	public
		$line=[],
		$elements=[],
		$context=null,
		$page=null;

	// возвращает старший шаблонизатор, через который извлекаются шаблоны из БД, не связанные с сущностями, модулями и другими шаблонами.
	public static function master_instance()
	{
		return Template_master::instance();
	}
	
	public static function blank()
	{
		return new static();
	}
	
	public static function with_line($line=[])
	{
		$template=static::blank();
		$template->line=$line;
		return $template;
	}
	
	public function clone_with_line($line=[])
	{
		$template=clone $this;
		$template->reset();
		$template->line=array_merge($template->line, $line);
		return $template;
	}
	
	// чтобы метод template() не вызывался как конструктор.
	public function __construct()
	{
		parent::__construct();
	}
	
	public function find_template($code, $line=[])
	{
		if (($element=$this->template($code, $line))!==null) return $element;
		if (($element=$this->find_context_template($code, $line))!==null) return $element;
		if (($element=$this->find_module_template($code, $line))!==null) return $element;
		return 'MISSING ELEMENT: '.$code.'!';
	}
	
	public function template($code, $line=[])
	{
		if ($this->recognize_element($code, $line))
		{
			// иногда make_template может возвращать отчёт о задаче, содержащий внутри собственно шаблон.
			$template=$this->make_template($code, $line);
			if ($template instanceof Report_task) $template=$template->task;
			if ( ($template instanceof Template) && (empty($template->line)) ) $template->line=$line;
			return $template;
		}
	}
	
	// для соответствия интерфейсу ValueHost
	public function request($code)
	{
		return $this->ValueHost_request($code);
	}
	
	public function value($code)
	{
		return $this->ValueHost_value($code);
	}
	
	public function recognize_element($code, $line=[])
	{
		return in_array($code, $this->elements);
	}
	
	// этот и следующий методы вызываются при единичном ключевом слове в данном шаблоне, например, {{id}} (а не {{pokemon.id}})
	public function find_context_template($code, $line=[])
	{
		if (empty($this->context)) return;
	
		foreach ($this->context->templaters() as $templater)
		{
			if (($element=$templater->template($code, $line))!==null) return $element;
		}
	}
	
	public function find_module_template($code, $line=[])
	{
		foreach (Engine()->templaters as $templater)
		{
			if (($element=$templater->template($code, $line))!==null) return $element;
		}
	}
	
	public function follow_track($track)
	{
		if ($track===static::TRACK_PAGE) return $this->page;
		if (empty($this->context)) return;
		if ($track===static::TRACK_CONTEXT) return $this->context;
		return $this->context->follow_track($track);
	}
	
	public function setup_subtemplate($template)
	{
		if ($template instanceof Template)
		{
			if ( ($this->page!==null) && ($template->page===null) ) $template->page=$this->page;
			if ( ($this->context!==null) && ($template->context===null) ) $template->context=$this->context;
			// if ( (!empty($template->db_key)) && ($template->db_key==='standard.linked_title') ) { vdump('HISS'); vdump($template); }
			$template->initiated();
		}
		elseif ($template instanceof Task_proxy)
		{
			$template->add_call
			(
				function($task, $resolution)
				{
					$this->setup_subtemplate($resolution);
				},
				'proxy_resolved'
			);
		}
	}
	
	// этот метод вызывается только если шаблон является старшим или же при тестировании.
	public function complete()
	{
		$this->initiated();
		parent::complete();
	}
	
	public function initiated()
	{
	}
	
	public function entity()
	{
		if ($this->context instanceof Entity) return $this->context;
		die ('UNIMPLEMENTED YET: deduce context');
	}
	
	public function make_template($code, $line=[])
	{
	}
}

class Template_master implements Templater
{
	use Singleton;
	
	public function template($code, $line=[])
	{
		if (strpos($code, '.')===false) $db_key='custom.'.$code;
		else $db_key=$code;
		$template=Template_from_db::with_db_key($db_key, $line);
		return $template;
	}
}

class Template_js_var extends Template
{
	public
		$subtemplate;

	public static function from_template($subtemplate, $line=[])
	{
		$template=static::with_line($line);
		$template->subtemplate=$subtemplate;
		return $template;
	}
	
	public function initiated()
	{
		parent::initiated();
		$this->setup_subtemplate($this->subtemplate);
	}
	
	public function progress()
	{
		if ($this->subtemplate instanceof Task)
		{
			if ($this->subtemplate->failed()) $this->impossible('bad_subtemplate');
			elseif ($this->subtemplate->successful())
			{
				$this->finish_with_resolution($this->for_js($this->subtemplate->resolution));
			}
			else $this->register_dependancy($this->subtemplate);
		}
		else $this->finish_with_resolution($this->for_js($this->subtemplate));
	}
	
	public function for_js($content)
	{
		if ($content===null) return 'null';
		return var_export($this->var_safe($content), true); // предположение, что этого хватит.
	}
	
	public function var_safe($content)
	{
		return str_replace(["'", "\n", "\r"], ["\'", '\n', '\n'], $content);
	}
}

class Template_js_html extends Template_js_var
{
	public function for_js($content)
	{
		if ( (array_key_exists('format', $this->line)) && ($this->line['format']==='object') )
			return "{html: '".$this->var_safe($this->filter_out_scripts($content))."', eval: '".$this->var_safe($this->extract_scripts($content))."'}";
			// дальнейшая обработка (заключение в кавычки) не требуется.
			
		if (array_key_exists('scripts', $this->line))
		{
			if ($this->line['scripts']) $content=$this->extract_scripts($content);
			else $content=$this->filter_out_scripts($content);
		}
		return parent::for_js($content);
	}
	
	public function extract_scripts($content)
	{
		preg_match_all('/<script>(.+?)<\/script>/s', $content, $m);
		$eval=implode("\n", $m[1]);
		return $eval;
	}
	
	public function filter_out_scripts($content)
	{
		return preg_replace('/<script>(.+?)<\/script>/s', '', $content);
	}
}

abstract class Template_composed extends Template
{
	public
		$subtemplates=null,
		$default_on_empty='-';
	
	public function progress()
	{
		if ($this->subtemplates===null)
		{
			$this->subtemplates=[];
			$list=$this->spawn_subtasks();
			if ($list instanceof Report_impossible)
			{
				$this->impossible($list->errors);
				return;
			}
			elseif (empty($list))
			{
				if (array_key_exists('on_empty', $this->line)) $this->finish_with_resolution($this->line['on_empty']);
				elseif (array_key_exists('on_empty_template', $this->line)) die ('UNIMPLEMENTED YET: template on empty');
				else $this->finish_with_resolution($this->default_on_empty);
				return;
			}
			$this->process_subtasks($list);
			return;
		}
		$this->resolve();
	}
	
	public function process_subtasks($list)
	{
		foreach ($list as $subtask)
		{
			$this->setup_subtemplate($subtask);
			if ($subtask instanceof Task)
			{
				$this->register_dependancy($subtask);
				$this->subtemplates[]=&$subtask->resolution;
			}
			elseif (!is_object($subtask)) $this->subtemplates[]=$subtask;
			else die ('BAD SUBTASK');
		}
	}
	
	public abstract function spawn_subtasks();
	
	public function resolve()
	{
		$this->finish_with_resolution($this->compose());
	}
	
	public function compose()
	{
		$before=''; $between=''; $after='';
		if (array_key_exists('preset', $this->line))
		{
			$preset=$this->preset_by_code($this->line['preset']);
			extract($preset);
		}
		elseif (array_key_exists('glue', $this->line)) $between=$this->line['glue']; else $between='';
		if (array_key_exists('before', $this->line)) $before=$this->line['before'].$before;
		if (array_key_exists('after', $this->line)) $after.=$this->line['after'];
		$result=$before.implode($between, $this->subtemplates).$after;
		return $result;
	}
	
	public function preset_by_code($code)
	{
		if ($code==='ol') return $this->preset_by_tags('ol', 'li');
		if ($code==='ul') return $this->preset_by_tags('ul', 'li');
		if ($code==='div') return $this->preset_by_tags(null, 'div');
		if ($code==='b') return $this->preset_by_tags(null, 'b', ', ');
	}
	
	public function preset_by_tags($master_tag, $element_tag, $glue=null)
	{
		$before=''; $between=''; $after='';
		if (!empty($element_tag))
		{
			$before='<'.$element_tag.'>';
			$after='</'.$element_tag.'>';
		}
		if (!empty($before_element)) $before=$before_element.$before;
		if (!empty($after_element)) $after.=$after_element;
		if (!empty($glue)) $between=$glue;
		$between=$after.$between.$before;
		if (!empty($master_tag))
		{
			$before='<'.$master_tag.'>'.$before;
			$after=$after.'</'.$master_tag.'>';
		}
		if (!empty($before_list)) $before=$before_list.$before;
		if (!empty($after_list)) $after=$after.$after_list;
		
		return ['before'=>$before, 'between'=>$between, 'after'=>$after];
	}
}

class Template_composed_preset extends Template_composed
{
	public
		$preset_list;
	
	public static function with_list($list, $line=[])
	{
		$template=static::with_line($line);
		$template->preset_list=$list;
		return $template;
	}
	
	public function spawn_subtasks()
	{
		return $this->preset_list;
	}
}

class Template_composed_call extends Template_composed
{
	public
		$call=null;
		
	public static function with_call($call, $line=[])
	{
		$template=static::with_line($line);
		$template->call=$call;
		return $template;
	}
	
	public function spawn_subtasks()
	{
		$call=$this->call;
		return $call($this->line);
	}
}

class Template_from_text extends Template
{
	use Task_steps;
	
	const
		STEP_GET_TEXT=0,
		STEP_EVAL=1,
		STEP_COMPOSE=2;
	
	public
		$text=null,
		$plain=false,		
		$buffer=[];
	
	public static function with_text($text, $line=[])
	{
		$template=static::with_line($line);
		$template->text=$text;
		return $template;
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_GET_TEXT)
		{
			$report=$this->text(false);
			if (is_string($report)) return $this->advance_step();
			elseif ($report===true) return $this->advance_step();
			return $report;
		}
		elseif ($this->step===static::STEP_EVAL)
		{
			$text=$this->text(true);
			if ($text instanceof Report_impossible) return $text;
			
			if ($this->plain) return $this->sign_report(new Report_resolution($this->text));
			ob_start();
			eval ($this->text);		
			$this->buffer_store_output(); // после последнего ключевого слова мог остаться какой-нибудь вывод, сохраняем его.
			ob_end_clean();
			$report=$this->report();
			if ($report instanceof Report_in_progress) return $this->advance_step(); // значит, нет зависимостей.
			elseif ($report instanceof Report_tasks) return true; // задачи уже записаны в зависимости.
			return $report;
		}
		elseif ($this->step===static::STEP_COMPOSE)
		{
			return $this->sign_report(new Report_resolution($this->compose()));
		}
	}
	
	public function compose()
	{
		return implode($this->buffer);
	}
	
	public function finish($success=true)
	{
		$this->buffer=[];
		return parent::finish($success);
	}
	
	public function text($now=true)
	{
		if ($this->text!==null) return $this->text;
		$result=$this->get_text($now);
		if (is_string($result))
		{
			$this->text=$result;
			return $this->text;
		}
		return $result;
	}
	
	public function get_text($now=true)
	{
		die('NO TEXT RETRIEVAL');	
	}
	
	public function keyword($track, $line=[])
	{
		$this->buffer_store_output();	
		$task=$this->keyword_task($track, $line);
		
		if ($task instanceof Report_impossible) $this->buffer_store_string('UNKNOWN TEMPLATE: '.((is_array($track))?(implode('.', $track)):($track)));
		elseif ($task instanceof Report_tasks) $this->buffer_store_subtemplates($task);
		elseif ($task instanceof Task) $this->buffer_store_subtemplate($task);
		elseif (is_object($task)) die ('BAD ELEMENT');
		else $this->buffer_store_string($task);
	}
	
/*
	может вернуть один из следующих ответов:
	
	1. задачу Task_resolve_keyword_track или Task_delayed_keyword, обе из которых реализуют интерфейс Task_proxy, то есть по выполнении имеют результат идентичный шаблону, который пытаются получить.
	2. Другой Task, обычно являющийся шаблоном.
	3. Report_tasks, содержащий несколько шаблонов, которые предлагается зарегистрировать в буфере подряд - пока не реализовано.
	4. Report_impossible, если шаблон по такому коду не найден.
	5. Просто значение.
*/
	public function keyword_task($track, $line=[])
	{
		if ( (is_array($track)) && (reset($track)==='@') )
		{
			array_shift($track);
			if (count($track)==1) $track=reset($track);
			return $this->value_task($track);
		}
		
		$line_has_tasks=false;
		foreach ($line as &$arg)
		{
			if ($arg instanceof Compacter) $arg=$arg->extract_for($this);
			if ($arg instanceof Task) $line_has_tasks=true;
		}
		
		if ( ($line_has_tasks) || (array_key_exists('cache_key', $line)) )
		{
			return new Task_delayed_keyword($track, $line, $this);
		}
		
		if (is_array($track)) return new Task_resolve_keyword_track($track, $line, $this);
		
		$element=$this->find_template($track, $line);
		if ($element instanceof Report_impossible) return $element;
		if ($element instanceof Report_task) return $element->task;
		if ($element instanceof Report_tasks) return $element; // FIX: нужно предусмотреть вариант, когда ряд задач будет возвращён одному из Task_proxy, которые уже добавлены в буфер в виде единсвтенного шаблона.
		
		if ($element instanceof Report_resolution) $element=$element->resolution;
		
		if ($element===null) return $this->sign_report(new Report_impossible('no_keyword_resolution'));
		if ($element instanceof Task) return $element;
		if (is_object($element)) { vdump($element); vdump($this); die ('BAD REPORT 2'); }
		
		return $element;
	}
	
	// возвращает либо искомое значение, либо задачу, разрешением которой станет искомое значение.
	// такой запрос записывается не {{pokemon.id}}, а @pokemon.id и возвращает не шаблон - отображение для пользователя; а собственно значение, поэтому командной строки не нужно (она обычно уточняет параметры отображения). В выражениях следует всегда обращаться за значениями, а не шаблонами, потому что хотя вместо шаблона иногда может вернуться значение (например, числовые значения обычно возвращают просто себя), лучше наверняка получить значение.
	public function value_task($track)
	{
		if (is_array($track)) return new Task_resolve_value_track($track, $this);
		
		$element=$this->ValueHost_request($track);
		if ($element===null) return $this->sign_report(new Report_impossible('no_value_resolution'));
		if ($element instanceof Report_resolution) return $element->resolution;
		if ($element instanceof Report_impossible) return $element;
		if ($element instanceof Report_task) return $element->task;
		if ($element instanceof Report_tasks) die ('BAD REPORT');
		if ($element instanceof Task) return $element;
		
		if (is_object($element)) { vdump($element); die ('BAD REPORT 2'); }
		return $element;
	}
	
	public function buffer_store_string($s)
	{
		if ($s==='') return;
		$this->buffer[]=$s;
	}
	
	// скидывает в буфер вывод, накопившийся с прошлого обращения к буферу.
	public function buffer_store_output()
	{
		$output=ob_get_contents();
		if ($output!=='') $this->buffer[]=$output;
		ob_clean();
	}
	
	// добавляет в буфер ссылку на переменную.
	public function buffer_store_reference(&$target)
	{
		$this->buffer[]=&$target;
	}
	
	public function buffer_store_error($error)
	{
		if (is_array($error)) $error=implode(', ', $error);
		$this->buffer_store_string('BAD TEMPLATE: '.$error); // STUB
	}
	
	public function buffer_store_subtemplate($template)
	{
		$this->setup_subtemplate($template);
		$report=$template->report();
		if ($report instanceof Report_impossible) return $this->buffer_store_error($report->errors);
		if ($report instanceof Report_resolution) return $this->buffer_store_string($report->resolution);
		
		$this->buffer_store_reference($template->resolution);
		$this->register_dependancy($template);
	}
	
	public function buffer_store_subtemplates($templates)
	{
		if ($templates instanceof Report_tasks) $templates=$templates->tasks;
		foreach ($templates as $template)
		{
			$this->buffer_store_subtemplate($template);
		}
	}
	
	// добавляет в буфер информацию для вызова функции (в данной модели не используется).
	/*
	// пока нет функционала, чтобы это использовать, и случая, когда бы это пригождалось.
	public function buffer_store_call($call)
	{
		if (! ($call instanceof Call)) $call=new Call($call);
		$this->buffer[]=$call;
	}
	*/
	
	public function reset()
	{
		parent::reset();
		$this->step=null;
		$this->buffer=[];
		$this->text=null;
	}
}

interface CodeHost
{
	public function get_codefrag($id); // создаёт экземпляр инструкции.
	
	public function codefrag($type, $id); // предоставляет данные для создания инструкции.
}

/*
class Template_from_db extends Template_from_text implements CodeHost
{
	const
		STEP_GET_KEY=0,
		STEP_GET_TEXT=1,
		STEP_EVAL_ONCE=2,
		STEP_EVAL=3,
		STEP_COMPOSE=4,
		
		TEMPLATE_TABLE='info_templates',
		TEMPLATE_FIELD='template',
		KEY_FIELD='code',
		COMPILED_FIELD='compiled2',
		EVAL_ONCE_FIELD='eval_once';
	
	static
		$eval_once=[],
		$codefrag_cache=[];
	
	public
		$db_key=null,
		$file_template=null;
	
	public static function with_db_key($key, $line=[])
	{
		$template=static::with_line($line);
		$template->db_key=$key;
		return $template;
	}
	
	public function codefrag($type, $id)
	{
		if (array_key_exists($id, static::$codefrag_cache)) die ('CODEFRAG DOUBLE');
		$args=func_get_args();
		static::$codefrag_cache[$this->db_key][$id]=$args;
	}
	
	public $codefrags=[];
	public function get_codefrag($id)
	{
		if (!array_key_exists($id, $this->codefrags))
		{
			if (!array_key_exists($this->db_key, static::$codefrag_cache)) die ('UNKNOWN CODEFRAG');
			if (!array_key_exists($id, static::$codefrag_cache[$this->db_key])) die ('UNKNOWN CODEFRAG');
			$frag=static::$codefrag_cache[$this->db_key][$id];
			if (!is_object($frag))
			{
				$frag=call_user_func_array( ['CodeFragment', 'create_unattached'], $frag);
				static::$codefrag_cache[$this->db_key][$id]=$frag;
			}
			$this->codefrags[$id]=$frag->clone_for_host($this);
		}
		return $this->codefrags[$id];
	}
	
	public $previous_codefrag=null;
	public function command($id)
	{
		$this->buffer_store_output();
		
		$codefrag=$this->get_codefrag($id);
		if ($this->previous_codefrag!==null) $codefrag->previous=$this->previous_codefrag;
		$this->previous_codefrag=$codefrag;
		
		$this->buffer_store_subtemplate($codefrag);
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_GET_KEY)
		{
			$result=$this->db_key(false);
			if ($result instanceof Report) return $result;
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_EVAL_ONCE)
		{
			$text=$this->text(); // заставляет получить и проанализировать данные из БД, в том числе дописать в static::$eval_once.
			if ($text instanceof Report_impossible) return $text;
			
			$db_key=$this->db_key();
			if ( (array_key_exists($db_key, static::$eval_once)) && (static::$eval_once[$db_key]===null) ) return $this->advance_step();
			
			if ($text===true)
			{
				if ($this->cache_exists_eval_once())
				{
					$filename=Engine()->server_address('templates/eval_once/'.$this->db_key().'.php');
					include($filename);
				}
			}
			else
			{
				eval(static::$eval_once[$db_key]);
			}
			
			static::$eval_once[$db_key]=null;
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_EVAL)
		{
			$text=$this->text(true);
			
			if ($text===true)
			{
				$filename=Engine()->server_address('templates/'.$this->db_key().'.php');
				ob_start();
				include($filename);
				$this->buffer_store_output(); // после последнего ключевого слова мог остаться какой-нибудь вывод, сохраняем его.
				ob_end_clean();
				
				$report=$this->report();
				if ($report instanceof Report_in_progress) return $this->advance_step(); // значит, нет зависимостей.
				elseif ($report instanceof Report_tasks) return true; // задачи уже записаны в зависимости.
				return $report;
			}
			return parent::run_step();
		}
		else return parent::run_step();
	}
	
	static $cache_map=[];
	public function cache_exists($dir='')
	{
		if (!array_key_exists($dir, static::$cache_map))
		{
			$path=Engine()->server_address('templates/'.((empty($dir))?(''):($dir.'/')));
			if (!file_exists($path)) static::$cache_map[$dir]=[];
			else
			{
				$templates=scandir($path);
				array_walk($templates, function(&$filename) { $filename=substr($filename, 0, -4); });
				static::$cache_map[$dir]=array_fill_keys($templates, null);
			}
		}
		if (empty(static::$cache_map[$dir])) return false;
		return array_key_exists($this->db_key(), static::$cache_map[$dir]);
	}
	
	public function cache_exists_eval_once()
	{
		return $this->cache_exists('eval_once');
	}
	
	public function db_key($now=true)
	{
		if (!is_null($this->db_key)) return $this->db_key;
		$result=$this->get_db_key($now);
		if ($result instanceof Report) return $result;
		$this->db_key=$result;
		return $this->db_key;
	}
	
	public function get_db_key($now=true)
	{
		vdump($this);
		die('NO DB KEY RETRIEVAL');	
	}
	
	public function get_text($now=true)
	{
		if ($this->file_template===null) $this->file_template=$this->cache_exists();
		if ($this->file_template===true) return true;
	
		if ($now) $mode=Request::GET_DATA_NOW;
		else $mode=Request::GET_DATA_SET;
		
		$data=$this->get_request()->get_data($db_key=$this->db_key(), $mode);
		if ($data instanceof Report) return $data;
		if (!array_key_exists($db_key, static::$eval_once)) static::$eval_once[$db_key]=$data[static::EVAL_ONCE_FIELD];
		if ($data['plain']) $this->plain=true;
		return $data[static::COMPILED_FIELD];
	}
	
	public $request=null;
	public function get_request()
	{
		if (is_null($this->request))
		{
			$this->request=Request_by_unique_field::instance(static::TEMPLATE_TABLE, static::KEY_FIELD);
		}
		return $this->request;
	}
	
	public function impossible($errors=null)
	{
		if ($errors===null) $details='unknown error';
		elseif (is_array($errors)) $details=implode(', ', $errors);
		else $details=$errors;
		
		$this->resolution='NO TEMPLATE: '.$this->db_key.' ('.$details.')';
		parent::impossible();
	}
}
*/

class Template_from_db extends Template_from_text implements CodeHost
{
	const
		STEP_GET_KEY=0,
		STEP_GET_TEXT=1,
		STEP_EVAL_ONCE=2,
		STEP_EVAL=3,
		STEP_COMPOSE=4,
		
		TEMPLATE_TABLE='info_templates',
		TEMPLATE_FIELD='template',
		KEY_FIELD='code',
		COMPILED_FIELD='compiled2',
		EVAL_ONCE_FIELD='eval_once';
	
	static
		$eval_once=[],
		$codefrag_cache=[];
	
	public
		$db_key=null;
	
	public static function with_db_key($key, $line=[])
	{
		$template=static::with_line($line);
		$template->db_key=$key;
		return $template;
	}
	
	public function codefrag($type, $id)
	{
		if (array_key_exists($id, static::$codefrag_cache)) die ('CODEFRAG DOUBLE');
		$args=func_get_args();
		static::$codefrag_cache[$this->db_key][$id]=$args;
	}
	
	public $codefrags=[];
	public function get_codefrag($id)
	{
		if (!array_key_exists($id, $this->codefrags))
		{
			if (!array_key_exists($this->db_key, static::$codefrag_cache)) die ('UNKNOWN CODEFRAG');
			if (!array_key_exists($id, static::$codefrag_cache[$this->db_key])) die ('UNKNOWN CODEFRAG');
			$frag=static::$codefrag_cache[$this->db_key][$id];
			if (!is_object($frag))
			{
				$frag=CodeFragment::create_unattached(...$frag);
				static::$codefrag_cache[$this->db_key][$id]=$frag;
			}
			$this->codefrags[$id]=$frag->clone_for_host($this);
		}
		return $this->codefrags[$id];
	}
	
	public $previous_codefrag=null;
	public function command($id)
	{
		$this->buffer_store_output();
		
		$codefrag=$this->get_codefrag($id);
		if ($this->previous_codefrag!==null) $codefrag->previous=$this->previous_codefrag;
		$this->previous_codefrag=$codefrag;
		
		$this->buffer_store_subtemplate($codefrag);
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_GET_KEY)
		{
			$result=$this->db_key(false);
			if ($result instanceof Report) return $result;
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_EVAL_ONCE)
		{
			$this->text(); // заставляет получить и проанализировать данные из БД, в том числе дописать в static::$eval_once.
			$db_key=$this->db_key();
			if ( (array_key_exists($db_key, static::$eval_once)) && (static::$eval_once[$db_key]!==null) )
			{
				eval(static::$eval_once[$db_key]);
				static::$eval_once[$db_key]=null;
			}
			return $this->advance_step();
		}
		else return parent::run_step();
	}
	
	public function db_key($now=true)
	{
		if ($this->db_key!==null) return $this->db_key;
		$result=$this->get_db_key($now);
		if ($result instanceof Report) return $result;
		$this->db_key=$result;
		return $this->db_key;
	}
	
	public function get_db_key($now=true)
	{
		vdump($this);
		die('NO DB KEY RETRIEVAL');	
	}
	
	public function get_text($now=true)
	{
		if ($now) $mode=Request::GET_DATA_NOW;
		else $mode=Request::GET_DATA_SET;
		
		$data=$this->get_request()->get_data($db_key=$this->db_key(), $mode);
		if ($data instanceof Report) return $data;
		if (!array_key_exists($db_key, static::$eval_once)) static::$eval_once[$db_key]=$data[static::EVAL_ONCE_FIELD];
		if ($data['plain']) $this->plain=true;
		return $data[static::COMPILED_FIELD];
	}
	
	public $request=null;
	public function get_request()
	{
		if ($this->request===null)
		{
			$this->request=Request_by_unique_field::instance(static::TEMPLATE_TABLE, static::KEY_FIELD);
		}
		return $this->request;
	}
	
	public function impossible($errors=null)
	{
		if ($errors===null) $details='unknown error';
		elseif (is_array($errors)) $details=implode(', ', $errors);
		else $details=$errors;
		
		$this->resolution='NO TEMPLATE: '.$this->db_key.' ('.$details.')';
		parent::impossible();
	}
	
	public function human_readable()
	{
		return get_class($this).'['.$this->object_id.','.$this->db_key.'] ('.$this->report()->human_readable().')';
	}
}

trait Task_resolves_line
{
	public
		$line_resolved=false;
		
	public function resolve_line()
	{
		$tasks=[];
		foreach ($this->line as $code=>$argument)
		{
			if ($argument instanceof Compacter) $argument=$argument->extract_for($this->compacter_host());
			if ($argument instanceof Task)
			{
				if ($argument->failed()) return $this->sign_report(new Report_impossible('bad_argument'));
				elseif ($argument->successful()) $this->line[$code]=$argument->resolution;
				else
				{
					$tasks[$code]=$argument;
					$this->line[$code]=&$argument->resolution;
				}
			}
		}
		if (!empty($tasks)) return $this->sign_report(new Report_tasks($tasks));
		else return $this->sign_report(new Report_success());
	}
	
	public abstract function compacter_host();
}

trait Task_checks_cache
{
	public
		$cache_task=false;

	public function make_cache_key($line=[])
	{
		if (!array_key_exists('cache_key', $line)) return $this->sign_report(new Report_impossible('no_cache_key'));
		$cache_key=['code'=>$line['cache_key']];
		if (array_key_exists('cache_num', $line)) $cache_key['num']=$line['cache_num'];
		if (array_key_exists('cache_expiry', $line)) $cache_key['expiry']=$line['cache_expiry'];
		return $cache_key;
	}

	public function check_cache($line=[])
	{
		if (!array_key_exists('cache_key', $line)) return;
	
		$cache_key=$this->make_cache_key($line);
		
		$task=Task_retrieve_cache::with_cache_key($cache_key);
		$this->cache_task=$task;
		return $this->sign_report(new Report_task($task));
	}
	
	public function process_cache()
	{
		if ( ($this->cache_task instanceof Task) && ($this->cache_task->successful()) ) return $this->cache_task->report();
	}
	
	public function save_cache($content)
	{
		if (!($this->cache_task instanceof Task)) return;
		return $this->cache_task->save_cache($content);
	}
}

abstract class Task_resolve_track extends Task implements Task_proxy
{	
	public
		$original_track,
		$track,
		$location,
		$iteration=0,
		$again=null;
		
	public function __construct($track, $origin)
	{
		$this->track=$track;
		$this->original_track=$track;
		$this->location=$origin;
		parent::__construct();
	}
	
	public function compacter_host()
	{
		if ($this->iteration===0) return $this->location;
		die ('LATE COMPACTER CALL');
	}
	
	public function progress()
	{
		if ( (count($this->track)==1) && ($this->again===null) )
		{
			$this->endpoint_reached();
			return;
		}

		if (! ($this->location instanceof Pathway) ) { vdump($this->original_track); vdump($this->location); die ('BAD TRACK LOCATION'); }
		if ($this->again!==null) $track=$this->again;
		else $track=array_shift($this->track);
		$this->again=null;
		$this->iteration++;
		$result=$this->follow_track($track);
		
		/*
		vdump(get_class($this));
		vdump('ORIGINAL: '.implode('.', $this->original_track));
		vdump('TRACK: '.$track);
		vdump('LOC: '.get_class($this->location));
		if ($this->location instanceof Template)
		{
			vdump('CONTEXT: '.get_class($this->location->context));
			if ($this->location->context instanceof Entity) vdump('TYPE '.$this->location->context->id_group);
		}
		vdump('RESULT: '.get_class($result));
		vdump('---');
		*/
		
		if ($result instanceof Report_impossible) $this->impossible($result->errors);
		elseif ($result===null) $this->impossible('bad_track');
		elseif ($result instanceof Report_tasks)
		{
			$result->register_dependancies_for($this);
			$this->again=$track;
		}
		else $this->location=$result; // соответствие Pathway, Templater или ValueHost будет проверено в следующем прогрессе.
	}
	
	public function endpoint_reached()
	{
		$this->iteration=null;
		if (!$this->good_endpoint())
		{
			// vdump('BAD ENDPOINT: '.reset($this->track)); 
			$this->impossible('bad_endpoint');
			return;
		}
		$result=$this->ask_endpoint();
		// vdump('ENDPOINT RESULT: '); vdump($result);
		
		if ($result instanceof Report_task) $result=$result->task;
		if ($result instanceof Task) $this->register_dependancy($result);
		elseif ($result instanceof Report_impossible) $this->impossible($result->errors);
		elseif ($this->good_resolution($result))
		{
			$this->resolution=$result;
			$this->finish();
		}
		else { vdump($result); vdump($this); die ('BAD TRACK RESOLUTION 1'); }
		
		$this->make_calls('proxy_resolved', $result);
	}
	
	public abstract function good_endpoint();
	
	public abstract function ask_endpoint();
	
	public function follow_track($track)
	{
		if ($track==='')
		{
			if ($this->iteration>1) return $this->sign_report(new Report_impossible('inappropriate_track'));
			return $this->location; // конструкция типа ".title" используется для того, чтобы непременно задействовать этот способ обращения к шаблонизатору. например, .title в шаблоне, контекст которого - покемон, непременно обратится к покемону, не пытаясь найти то же кодовое слово в локальных элементах шаблона.
		}
		if ($track==='#')
		{
			if ($this->iteration>1) return $this->sign_report(new Report_impossible('inappropriate_track'));
			return Template::master_instance();
		}
		
		if ( ($this->iteration==1) && (array_key_exists($track, $pathways=Engine()->tracks)) ) return $pathways[$track];
		
		if (($location=$this->location->follow_track($track))!==null) return $location;
		
		return $this->sign_report(new Report_impossible('no_track'));
	}
	
	public function dependancy_resolved($task, $identifier=null)
	{
		parent::dependancy_resolved($task, $identifier);
		
		if ($this->iteration===null)
		{
			$report=$task->report();
			if ($report instanceof Report_impossible) $this->impossible($report->errors);
			elseif ($report instanceof Report_resolution)
			{
				$this->resolution=$report->resolution;
				$this->finish();
			}
			else die ('BAD TRACK DEPENDANCY');
		}
	}
	
	public function finish($success=true)
	{
		if ($success!==true) $this->resolution='MISSING TEMPLATE: '.implode('.', $this->original_track);
		parent::finish($success);
	}
	
	public abstract function good_resolution($result);
}

class Task_resolve_keyword_track extends Task_resolve_track
{	
	public
		$line=[];
		
	public function __construct($track, $line, $origin)
	{
		$this->line=$line;
		parent::__construct($track, $origin);
	}
	
	public function good_endpoint()
	{
		return $this->location instanceof Templater;
	}
	
	public function ask_endpoint()
	{
		return $this->location->template(reset($this->track), $this->line);
	}
	
	public function good_resolution($result)
	{
		return !is_object($result);
	}
}

class Task_resolve_value_track extends Task_resolve_track
{
	public function good_endpoint()
	{
		return $this->location instanceof ValueHost;
	}
	
	public function ask_endpoint()
	{
		$result=$this->location->request(reset($this->track));
		if ($result instanceof Report_resolution) return $result->resolution;
		elseif ($result instanceof Report_task) return $result->task;
		elseif ($result instanceof Report_tasks) die ('BAD VALUE ENDPOINT'); // недопустимый результат согласно интерфейсу ValueHost
		return $result;
	}
	
	public function good_resolution($result)
	{
		return true;
	}
}

// подразумевает односложный путь, типа {{id}}, а не {{pokemon.id}}
class Task_delayed_keyword extends Task implements Task_proxy
{
	use Task_resolves_line, Task_checks_cache, Task_steps;
	
	const
		STEP_RESOLVE_LINE=0,
		STEP_CHECK_CACHE=1,
		STEP_KEYWORD=2,
		STEP_CHECK_CONTENT=3,
		STEP_FINISH=4;
	
	public
		$keyword,
		$line,
		$host,
		$final=false;
		
	public function __construct($keyword, $line, $host)
	{
		$this->keyword=$keyword;
		$this->line=$line;
		$this->host=$host;
		parent::__construct();
	}
	
	public function compacter_host()
	{
		return $this->host;
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_RESOLVE_LINE)
		{
			$report=$this->resolve_line();
			if ($report instanceof Report_success) return $this->advance_step();
			return $report;
			
		}
		elseif ($this->step===static::STEP_CHECK_CACHE)
		{
			$report=$this->check_cache($this->line);
			if (empty($report)) return $this->advance_step();
			return $report;
		}
		elseif ($this->step===static::STEP_KEYWORD)
		{
			if (!empty($this->cache_task))
			{
				$report=$this->process_cache();
				if ($report instanceof Report_resolution) return $report;
			}
			
			$line=$this->line;
			unset($line['cache_key']);
			$result=$this->host->keyword_task($this->keyword, $line);
			$this->make_calls('proxy_resolved', $result);
			if ($result instanceof Report_tasks) die ('UNIMPLEMENTED YET: delayed multitemplate');
			$this->final=$result;
			if ($result instanceof Task) return $this->sign_report(new Report_task($result));
			elseif (!empty($this->cache_task)) return $this->sign_report(new Report_resolution($result));
			else return $this->advance_step();
		}
		elseif ($this->step===static::STEP_CHECK_CONTENT)
		{
			if ($this->final instanceof Task)
			{
				if ($this->final->successful()) $this->final=$this->final->resolution;
				else return $this->final->report();
			}
			
			if (!empty($this->cache_task))
			{
				$report=$this->save_cache($this->final);
				if ($report instanceof Report_task) return $report;
			}
			
			return $this->sign_report(new Report_resolution($this->final));
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			return $this->sign_report(new Report_resolution($this->final));
		}
	}
}
?>