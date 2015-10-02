<?
namespace Pokeliga\Template;

/**
* Результатом работы шаблона является строка или число, предназначенные для демонстрации пользователю тем или иным образом.
*/
abstract class Template extends \Pokeliga\Task\Task implements \Pokeliga\Data\HasContext
{
	use \Pokeliga\Task\Task_coroutine;
	
	const
		STEP_NEED_LINE		=0,
		STEP_CHECK_CACHE	=1,
		STEP_RESOLVE_LINE	=2,
		STEP_STORE_CACHE	=3;
		STEP_FINISH			=4;
	
	public
		$line=[],
		$context=null,
		$page=null;
	
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
	
	// чтобы метод template() не вызывался как конструктор.
	public function __construct()
	{
		parent::__construct();
	}
	
	public function coroutine()
	{
		if (!empty($this->line))
		{
			yield $need=new \Pokeliga\Entlink\Need_all($this->line);
			$this->line=$need->resolution;
		}
		
		if ($this->is_cacheable())
		{
			yield $need=new 
		}
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
		elseif ($template instanceof \Pokeliga\Task\Task_proxy)
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
	
	public function initiated() { }
	
	// FIXME: преждевременное знание о сущностях?
	public function entity()
	{
		if ($this->context instanceof \Pokeliga\Entity\Entity) return $this->context;
		die ('UNIMPLEMENTED YET: deduce context');
	}
	
	public function get_context() { return $this->context; }
	
	public function make_template($code, $line=[]) { }
}

class Template_master implements Templater
{
	use \Pokeliga\Entlink\Singleton;
	
	public function template($code, $line=[])
	{
		if (strpos($code, '.')===false) $db_key='custom.'.$code;
		else $db_key=$code;
		$template=Template_from_db::with_db_key($db_key, $line);
		return $template;
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
			if ($argument instanceof \Pokeliga\Data\Compacter) $argument=$argument->extract_for($this->compacter_host());
			if ($argument instanceof \Pokeliga\Task\Task)
			{
				if ($argument->failed()) return new \Report_impossible('bad_argument', $this);
				elseif ($argument->successful()) $this->line[$code]=$argument->resolution;
				else
				{
					$tasks[$code]=$argument;
					$this->line[$code]=&$argument->resolution;
				}
			}
		}
		if (!empty($tasks)) return new \Report_tasks($tasks, $this);
		else return new \Report_success(, $this);
	}
	
	public abstract function compacter_host();
}

trait Task_checks_cache
{
	public
		$cache_task=false;

	public function make_cache_key($line=[])
	{
		if (!array_key_exists('cache_key', $line)) return new \Report_impossible('no_cache_key', $this);
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
		return new \Report_task($task, $this);
	}
	
	public function process_cache()
	{
		if ( ($this->cache_task instanceof \Pokeliga\Task\Task) && ($this->cache_task->successful()) ) return $this->cache_task->report();
	}
	
	public function save_cache($content)
	{
		if (!($this->cache_task instanceof \Pokeliga\Task\Task)) return;
		return $this->cache_task->save_cache($content);
	}
}

// подразумевает односложный путь, типа {{id}}, а не {{pokemon.id}} ???
class Task_delayed_keyword extends \Pokeliga\Task\Task implements \Pokeliga\Task\Task_proxy
{
	use Task_resolves_line, Task_checks_cache, \Pokeliga\Task\Task_steps;
	
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
			if ($report instanceof \Report_success) return $this->advance_step();
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
				if ($report instanceof \Report_resolution) return $report;
			}
			
			$line=$this->line;
			unset($line['cache_key']);
			$result=$this->host->keyword_task($this->keyword, $line);
			$this->make_calls('proxy_resolved', $result);
			if ($result instanceof \Report_tasks) die ('UNIMPLEMENTED YET: delayed multitemplate');
			$this->final=$result;
			if ($result instanceof \Pokeliga\Task\Task) return new \Report_task($result, $this);
			elseif (!empty($this->cache_task)) return new \Report_resolution($result, $this);
			else return $this->advance_step();
		}
		elseif ($this->step===static::STEP_CHECK_CONTENT)
		{
			if ($this->final instanceof \Pokeliga\Task\Task)
			{
				if ($this->final->successful()) $this->final=$this->final->resolution;
				else return $this->final->report();
			}
			
			if (!empty($this->cache_task))
			{
				$report=$this->save_cache($this->final);
				if ($report instanceof \Report_task) return $report;
			}
			
			return new \Report_resolution($this->final, $this);
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			return new \Report_resolution($this->final, $this);
		}
	}
}
?>