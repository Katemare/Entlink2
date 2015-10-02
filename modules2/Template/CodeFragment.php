<?
namespace Pokeliga\Template;

abstract class CodeFragment extends Task
{
	use Prototyper;
	
	static
		$prototype_class_base='CodeFragment_';
	
	public
		$host=null, // шаблон или иное кодохранилище
		$args,
		$frag_id;
	
	public static function create_unattached($type, $id, $args=[])
	{
		$frag=static::from_prototype($type);
		$frag->frag_id=$id;
		$frag->setup($args);
		return $frag;
	}
	
	public static function for_host($host, $type, $id, $args=[])
	{
		$frag=static::create_unattached($type, $id, $args);
		$frag->attach_to_host($host);
		return $frag;
	}
	
	public function attach_to_host($host, $clone=false)
	{
		if ($clone) $frag=clone $this;
		else $frag=$this;
		$frag->host=$host;
		return $frag;
	}
	
	public function clone_for_host($host)
	{
		return $this->attach_to_host($host, true);
	}
	
	public function setup($args=[])
	{
		$this->args=$args;
	}
}

class CodeFragment_expression extends CodeFragment
{
	use Task_steps;
	
	const
		STEP_PRECALC=0,
		STEP_SOLVE=1;
		
	public function run_step()
	{
		if ($this->step===static::STEP_PRECALC)
		{
			if (empty($this->args['precalc'])) return $this->advance_step();
			$tasks=[];
			foreach ($this->args['precalc'] as $key=>$precalc)
			{
				if ($precalc instanceof \Pokeliga\Data\Compacter)
				{
					$precalc=$precalc->extract_for($this->host);
				}
				if ($precalc instanceof \Pokeliga\Task\Task)
				{
					if ($precalc->failed()) return $this->sign_report(new \Report_impossible('bad_precalc'));
					elseif ($precalc->successful()) $this->args['precalc'][$key]=$precalc->resolution;
					else
					{
						if ($this->host instanceof Template) $this->host->setup_subtemplate($precalc);
						$tasks[]=$precalc;
						$this->args['precalc'][$key]=&$precalc->resolution;
					}
				}
				else $this->args['precalc'][$key]=$precalc;
			}
			if (!empty($tasks)) return $this->sign_report(new \Report_tasks($tasks));
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_SOLVE)
		{
			if (!empty($this->args['precalc'])) $_PRECALC=$this->args['precalc'];
			$result=eval('return '.$this->args['expression'].';');
			return $this->sign_report(new \Report_resolution($result));
		}
	}
	
	public function completed_dependancy($task, $identifier=null)
	{
		if ( ($task->failed()) && ($this->step===static::STEP_PRECALC) ) // FIXME: есть опасность при использовании внешних зависимостей.
		{
			$this->impossible('bad_precalc');
			return;
		}
	}
}

abstract class CodeFragment_command extends CodeFragment
{
	use Task_steps;
	
	const
		STEP_CONNECT=0, // для связанных блочных операторов, таких как if ... elseif ... else.
		STEP_PREPARE=1,
		STEP_WAIT=2,
		STEP_EXECUTE=3,
		STEP_FINISH=4;
	
	public
		$previous=null;
		
	public function run_step()
	{
		if ($this->step===static::STEP_CONNECT) return $this->connect();
		elseif ($this->step===static::STEP_PREPARE) return $this->prepare();
		elseif ($this->step===static::STEP_WAIT)
		{
			if ( ($this->previous instanceof \Pokeliga\Task\Task) && (!$this->previous->completed()) ) return $this->sign_report(new \Report_task($this->previous));
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_EXECUTE) return $this->execute();
		elseif ($this->step===static::STEP_FINISH) return $this->analyze_finish();
	}
	
	public function connect()
	{
		return $this->advance_step();
	}
	
	public function prepare()
	{
		return $this->advance_step();
	}
	
	public abstract function execute();

	public function analyze_finish()
	{
		return $this->sign_report(new \Report_resolution($this->resolution)); // для тех, кто в качестве выполнения зарегистрировал зависимости.	
	}
	
	public function finish($success=true)
	{
		if ($success===false) $this->resolution='BAD COMMAND: '.get_class($this).'; ';
		parent::finish($success);
	}
}

class CodeFragment_sequence extends CodeFragment_command
{
	public
		$commands=null,
		$buffer=[];
		
	public function prepare()
	{
		$this->fill_commands();
		return $this->advance_step();
	}
	
	public function fill_commands()
	{
		$this->commands=[];
		$previous=null;
		foreach ($this->args as $id)
		{
			$command=$this->host->get_codefrag($id);
			if ($previous!==null) { $command->previous=$previous; debug('MAKING '.$previous->object_id.' PREVIOUS FOR '.$command->object_id); }
			$previous=$command;
			$this->commands[]=$command;
			$this->buffer[]=&$command->resolution;
		}
	}
	
	public function execute()
	{
		return $this->sign_report(new \Report_tasks($this->commands));
	}
	
	public function analyze_finish()
	{
		$resolution=implode($this->buffer);
		return $this->sign_report(new \Report_resolution($resolution));
	}
}

class CodeFragment_echo extends CodeFragment_command
{	
	public function prepare()
	{
		$content=&$this->args['content'];
		if ($content instanceof \Pokeliga\Data\Compacter) $content=$content->extract_for($this->host);
		if ($content instanceof \Pokeliga\Task\Task) return $this->sign_report(new \Report_task($content));
		return $this->advance_step();
	}
	
	public function execute()
	{
		$content=$this->args['content'];
		if ($content instanceof \Pokeliga\Task\Task) return $content->report();
		return $this->sign_report(new \Report_resolution($content));
	}
}

class CodeFragment_if extends CodeFragment_command
{
	public
		$branched=null;

	public function prepare()
	{
		$condition=&$this->args['condition'];
		if ($condition instanceof Compacter) $condition=$condition->extract_for($this->host);
		if ($condition instanceof Task) return $this->sign_report(new \Report_task($condition));
		return $this->advance_step();
	}
	
	public function execute()
	{
		$condition=$this->args['condition'];
		if ($condition instanceof Task)
		{
			$report=$condition->report();
			if ($report instanceof \Report_impossible) $condition=false;
			else $condition=$condition->resolution;
		}
		if (!$condition) return $this->sign_report(new \Report_success());
		
		$on_true=$this->args['on_true'];
		if ($on_true instanceof \Pokeliga\Data\Compacter) $on_true=$on_true->extract_for($this->host);
		if (!($on_true instanceof \Pokeliga\Task\Task)) die ('BAD IF BRANCH');
		$on_true->resolution=&$this->resolution;
		$this->branched=true;
		return $this->sign_report(new \Report_task($on_true));
	}
}

class CodeFragment_elseif extends CodeFragment_if
{
	public function connect()
	{
		if (!($this->previous instanceof CodeFragment_if)) die ('BAD ELSEIF');
		$this->branched=&$this->previous->branched;
		return $this->advance_step();
	}

	public function prepare()
	{
		if ($this->branched) return $this->sign_report(new \Report_success()); // если уже известно, что сработала предыдущая ветвь.
		return parent::prepare();
	}
	
	public function execute()
	{
		if ($this->branched) return $this->sign_report(new \Report_success());
		return parent::execute();
	}
}

class CodeFragment_else extends CodeFragment_command
{
	public
		$branched=null;
		
	public function connect()
	{
		if (!($this->previous instanceof CodeFragment_if)) die ('BAD ELSEIF');
		$this->branched=&$this->previous->branched;
		return $this->advance_step();
	}

	public function prepare()
	{
		if ($this->branched) return $this->sign_report(new \Report_success()); // если уже известно, что сработала предыдущая ветвь.
		return parent::prepare();
	}
	
	public function execute()
	{
		if ($this->branched) return $this->sign_report(new \Report_success());
		
		$commands=$this->args['commands'];
		if ($commands instanceof \Pokeliga\Data\Compacter) $commands=$commands->extract_for($this->host);
		if (!($commands instanceof \Pokeliga\Task\Task)) die ('BAD IF BRANCH');
		$this->branched=true;
		$commands->resolution=&$this->resolution;
		return $this->sign_report(new \Report_task($commands));
	}
}
?>