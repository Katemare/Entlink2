<?
namespace Pokeliga\Nav;

// показывает стандартную страницу сайта в заданном дизайне.
abstract class Page_view extends Page implements \Pokeliga\Template\Templater, \Pokeliga\Data\ValueHost
{
	use \Pokeliga\Data\ValueHost_standard
	{
		\Pokeliga\Data\ValueHost_standard::ValueHost_request as std_ValueHost_request;
	}

	const
		BREADCRUMBS_CLASS	='Pokeliga\Nav\PageBreadcrumbs',
		PROCESSOR_CLASS		='Pokeliga\Nav\PageDisplay',
		RENDER_CLASS		='Pokeliga\Nav\PageRender',
		DEFAULT_ERROR_KEY	='standard.unknown_error';

	public
		$template_class=null,
		$master_template,
		$content=null,
		$cached=false;
	
	
	public function get_render_task()
	{
		return $this->get_page_task('RENDER');
	}

	public function get_display_task()
	{
		return $this->get_processor_task();
	}
	
	public function get_breadcrumbs_task()
	{
		return $this->get_page_task('BREADCRUMBS');
	}
	
	public function get_master_template()
	{
		if ($this->master_template===null)
		{
			$template=$this->create_master_template();
			$this->setup_master_template($template);
			$this->master_template=$template;
		}
		return $this->master_template;
	}
	
	public function send_header()
	{
		header('Content-Type: text/html; charset=utf-8'); // STUB	
	}
	
	public function create_master_template()
	{
		$class=$this->template_class;
		if (empty($class))
		{
			$module=$this->relevant_module();
			if (!empty($module)) $class=$module->master_template_class;
		}
		if (empty($class)) $class=Engine()->master_template_class();
		if (empty($class)) die('NO MASTER TEMPLATE');
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
			$cache_key=$this->cache_key();
			
			if ( (!empty($cache_key)) && (($cached=$this->get_cached_content($cache_key))!==null) )
			{
				$this->cached=true;
				return $cached;
			}
			if ($this->error!==null) $content=$this->create_error_content();
			else $content=$this->create_content();
			
			if ($content===null) die('NULL CONTENT');
			
			$this->content=$content;
			$this->set_requirements();
			
			if (is_array($cache_key))
			{
				if (!is_object($content)) $this->save_cache();
				elseif ($content instanceof \Pokeliga\Task\Task)
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
	
	public function get_cached_content($cache_key)
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
			return $result['content'];
		}
	}
	
	public function save_cache()
	{
		$cache_key=$this->cache_key();
		if (!is_array($cache_key)) return;
		
		$content=$this->content();
		if (!is_object($content)) $save=$content;
		elseif ( ($content instanceof \Pokeliga\Task\Task) && ($content->completed()) ) $save=$content->resolution; // ERR: нет обработки ошибки, когда задача завершилась с ошибкой!
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
		if ($this->cache_key===null) $this->cache_key=$this->make_cache_key();
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
		if ($value instanceof \Pokeliga\Template\Templater) return $value->template(null, $line);
	}
	
	// реализует интерфейс ValueHost
	public function request($code)
	{
		if ($this->input===null) return $this->ValueHost_request($code);
		$value=$this->input->produce_value_soft($code);
		if ($value instanceof \Report_impossible) return $this->ValueHost_request($code);
		return $value->request();
	}
	
	public function value($code)
	{
		if ($this->input===null) return $this->ValueHost_value($code);
		$value=$this->input->produce_value_soft($code);
		if ($value instanceof \Report_impossible) return $this->ValueHost_value($code);
		return $value->value();
	}
	
	// STUB!
	public function ValueHost_request($code)
	{
		if ($code==='admin') return $this->sign_report(new \Report_resolution(Module_AdoptsGame::instance()->admin()));
		if ($code==='logged_in') return User::logged_in();
		if ($code==='user') return User::current_user();
		return $this->std_ValueHost_request($code); 
	}
	
	public function follow_track($track, $line=[])
	{
		if ( ($this->input!==null) && ($this->input->model_exists($track)) ) return $this->input->produce_value($track);
		return $this->request($track);
	}
}

class PageRender extends PageProcessor
{
	public function run_step()
	{
		if ($this->step===static::STEP_FINISH)
		{
			$template=$this->page->get_master_template();
			return $template->report();
		}
		else return parent::run_step();
	}

	public function process()
	{
		$template=$this->page->get_master_template();
		return $this->sign_report(new \Report_task($template));
	}
}

class PageDisplay extends PageProcessor
{
	const
		STEP_CHECK_REDIRECT=1,
		STEP_PROCESS=2,
		STEP_FINISH=3;
		
	public
		$render;
		
	public function process()
	{
		$this->render=$this->page->get_render_task();
		if  ($this->render instanceof \Pokeliga\Task\Task)
		{
			if ($this->render->successful()) return $this->advance_step();
			elseif ($this->render->failed()) return $render->report();
			return $this->sign_report(new \Report_task($this->render));
		}
		return $this->advance_step();
	}

	public function run_step()
	{
		if ($this->step===static::STEP_CHECK_REDIRECT)
		{
			$actual_url=$this->page->actual_url();
			$proper_url=$this->page->url();
			if ( ($actual_url!==$proper_url) && (is_string($redirect_to=$this->page->needs_redirect())) ) Router()->redirect($redirect_to);
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			$render=null;
			if ($this->render instanceof \Pokeliga\Task\Task)
			{
				if ($this->render->failed()) return $this->render->report();
				$render=$this->render->resolution;
			}
			else $render=$this->render;
			
			$this->page->send_header();
			echo $render;
			return $this->sign_report(new \Report_success());
		}
		else return parent::run_step();
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
		PROCESS_TASK='PageRender_from_db';

	public
		$master_db_key='standard.page',
		$content_class='Template_from_db',
		$error_content_class='Template_from_db',
		$db_key=null,
		$error_keys=[];
		
	public function setup_master_template($template)
	{
		if ( ($template instanceof \Pokeliga\Template\Template_from_db) && ($this->master_db_key!==null) )  $template->db_key=$this->master_db_key;
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
		// xdebug_print_function_stack(); vdump($this); die('MEOW');
		if ( (!empty($this->error)) && (array_key_exists($this->error, $this->error_keys)) ) $db_key=$this->error_keys[$this->error];
		else $db_key=static::DEFAULT_ERROR_KEY;
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
		$template->db_key=$this->db_key;
	}
}

class PageRender_from_db extends PageRender
{
	const
		STEP_PRE_REQUEST_TEMPLATE_CACHE=1,
		STEP_PRE_REQUEST_TEMPLATES=2,
		STEP_PROCESS=3,
		STEP_SAVE_TEMPLATE_CACHE=4,
		STEP_FINISH=5;
	
	public
		$cached_templates=[],
		$cached_templates_task;
	
	public function templates_request()
	{
		return Request_by_unique_field::instance(Template_from_db::TEMPLATE_TABLE, Template_from_db::KEY_FIELD);
	}
	
	public function template_cache_key()
	{
		$key=get_class($this->page);
		$db_key=$this->page->db_key;
		if (!empty($db_key)) $key.='|'.$db_key;
		
		return ['code'=>'PgTpl::'.$key, 'expires'=>2592000 /* месяц */ ];
	}
		
	public function run_step()
	{
		if ($this->step===static::STEP_PRE_REQUEST_TEMPLATE_CACHE)
		{
			if ($this->page->error!==null) return $this->advance_step(static::STEP_PROCESS);
			$key=$this->template_cache_key();
			if ($key===null) return $this->advance_step(static::STEP_PROCESS);
			$this->cached_templates_task=Task_retrieve_cache::with_cache_key($key);
			return $this->sign_report(new \Report_task($this->cached_templates_task));
		}
		elseif ($this->step===static::STEP_PRE_REQUEST_TEMPLATES)
		{
			if ($this->cached_templates_task->failed()) return $this->advance_step();
			$result=explode(',', $this->cached_templates_task->resolution);
			if ($result===false) return $this->advance_step();
			
			$this->cached_templates=$result;
			$report=$this->templates_request()->get_data_set($this->cached_templates);
			if ($report instanceof \Report_final) return $this->advance_step();
			return $report;
		}
		elseif ($this->step===static::STEP_SAVE_TEMPLATE_CACHE)
		{
			if (empty($this->cached_templates_task)) return $this->sign_report(new \Report_success());
			
			$used_templates=array_keys($this->templates_request()->data);
			sort($used_templates);
			if ($used_templates==$this->cached_templates) return $this->sign_report(new \Report_success());
			
			$this->cached_templates=array_unique(array_merge($this->cached_templates, $used_templates));
			sort($this->cached_templates);
			return $this->cached_templates_task->save_cache(implode(',', $this->cached_templates));
		}
		else return parent::run_step();
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

class Template_composed_appended extends \Pokeliga\Template\Template_composed
{
	public $content_checked=false;

	public function progress()
	{
		if ($this->content_checked===false)
		{
			$content=$this->page->content();
			if ( ($content instanceof \Pokeliga\Task\Task) && (!$content->completed()) ) $this->register_dependancy($content);
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

// старшый шаблон страницы, берущий свой текст из БД.
class Template_page_from_db extends \Pokeliga\Template\Template_from_db implements Template_page
{
	use Template_page_trifold;
	
	public
		$elements=[Template_page::ELEMENT_BEGIN, Template_page::ELEMENT_CONTENT, Template_page::ELEMENT_END, Template_page::ELEMENT_APPEND],
		$appendable=true;
}

?>