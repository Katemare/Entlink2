<?
// это несколько более умная версия вызова, которая знает о механизме зависимостей. Она хранит сведения о том, какие объекты связывает, чтобы после срабатывания (или из-за потери актуальности) убрать себя из списков и не мешать работе уборщика мусора, а также не создавать лишних, уже не нужных вызовов.

load_debug_concern('Task', 'Task');
class Dependancy_call extends Call
{
	public
		$master,	// объект, который ждёт вызова от зависимости.
		$host,		// зависимость, пообещавшая совершить этот вызов при определённых обстоятельствах.
		$pool='default';	// пул вызовов
		
	public function register($host, $pool='default')
	{
		$this->host=$host;
		if ( ($this->master instanceof Task) && ($this->master->is_needed()) ) $this->host->increase_need_by($this->master);
		$this->pool=$pool;
		$this->host->add_call($this, $pool);
		$this->register_at_master();
	}
	
	public function register_at_master()
	{
		$this->master->dependancy_calls[$this->object_id]=$this;
	}
	
	public function before_invoke()
	{
		$this->unregister();
	}
	
	public function unregister()
	{
		if (is_object($this->host))
		{
			if ( ($this->master instanceof Task) && ($this->master->is_needed()) ) $this->host->decrease_need_by($this->master); // если родительская задача была не нужна, то она и не добавляла единичку зависимости.
			unset($this->host->caller_calls[$this->pool][$this->object_id]);
		}
		$this->unregister_at_master();
	}
	
	public function unregister_at_master()
	{
		unset($this->master->dependancy_calls[$this->object_id]);
	}
	
	public function bindTo($object)
	{
		$new_call=parent::bindTo($object);
		$new_call->master=$object;
		return $new_call;
	}
}

// интерфейс для задач, которые служат для отложенного получения результатов: например, отложенное получение шаблона у неподтверждённой сущности. Нужно потому, что необходимый шаблон может требовать некоторой обработки, как только будет получен. Кроме того, такие задачи-оболочки в качестве своего результата сохраняют результат выполнения внутренней задачи.
interface Task_proxy
{
	// конкретных методов не требуется. Главное, чтобы в момент получения целевой задачи объект задействовал крючок "proxy_resolved" с двумя аргументами - собой (ввиду Caller_backreference) и получившейся задачей. иногда в роли результата может быть не задача, а значение - например, в случае обращения к значению сущности.
}

abstract class Task
{
	use Caller_backreference, Object_id, Report_spawner, Logger_Task;

	const
		MAX_ITERATIONS=100,
		STEP_INIT=0, // для черты Task_steps, потому что у черт не может быть констант.
		// следующие статусы служат исключительно одной цели - объяснить, почему progressable===false.
		STATE_DEPENDS=1;
		
	public
		$progressable=true, // принимает true (можно двигаться), false (движение закончилось) или одну из констант выше (нельзя двигаться по такой-то причине).
		$complete=null, // null - не закончена, false - невозможно закончить, true - успешно закончена.
		$resolution,
		$errors=null,
		$subtasks=[],
		$need=0;
	
	protected
		$report=null; // обращение через report()
	
	public function __construct()
	{
		$this->generate_object_id();
	}
	
	public function __clone()
	{
		$this->generate_object_id();
	}
	
	public function human_readable()
	{
		return get_class($this).'['.$this->object_id.'] ('.$this->report()->human_readable().')';
	}
	
	public function reset()
	{
		// if (!$this->completed()) die ('RESETTING UNFINISHED TASK');
		$this->progressable=true;
		$this->complete=null;
		$this->resolution=null;
		$this->dependancy_callback=null;
		$this->unregister_dependancies();
		$this->invalidate_report();
	}
	
	public function completed()
	{
		return $this->complete!==null;
	}
	
	public function successful()
	{
		return $this->complete===true;
	}
	
	public function failed()
	{
		return $this->complete===false;
	}
	
	public function is_needed() { return $this->need>0; }
	public function needs() // генератор
	{
		foreach ($this->subtasks as $task) { yield ($task); }
	}
	public function increase_need_for($target)
	{
		if ($this->is_needed()) $target->increase_need_by($this);
	}
	public $debug_needers=[];
	public function increase_need_by($who)
	{
		if (array_key_exists($who->object_id, $this->debug_needers)) return;
		else $this->debug_needers[$who->object_id]=true;
		$this->need++;
		$this->log('need', $who);
		if ($this->need==1) $this->recover_needs();
	}
	public function decrease_need_for($target)
	{
		if ($this->is_needed()) $target->decrease_need_by($this);
	}
	public function decrease_need_by($who)
	{
		if (!array_key_exists($who->object_id, $this->debug_needers)) return;
		else unset($this->debug_needers[$who->object_id]);
		$this->need--;
		$this->log('unneed', $who);
		if ($this->need==0) $this->drop_needs();
	}
	public function recover_needs()
	{
		foreach ($this->needs() as $task) { $task->increase_need_by($this); }
	}
	public function drop_needs()
	{
		foreach ($this->needs() as $task) { $task->decrease_need_by($this); }
	}
	
	public function max_standalone_progress()
	{
		$try=0;
		while ( ($this->progressable===true) && (++$try<=static::MAX_ITERATIONS) )
		{
			$this->progress();
		}
	}
	
	public function create_process()
	{
		return new Process_single_goal($this);
	}
	
	public function complete()
	{
		if ($this->completed()) return;
		$this->log('to_complete');
		$process=$this->create_process();
		$process->complete();
		if ($process->failed()) $this->impossible($process->errors);
		$this->finalize();
	}
	
	public function now()
	{
		$this->complete();
		if ($this->failed()) return $this->report();
		return $this->resolution;
	}
	
	public abstract function progress();
	
	public function finalize()
	{
		if (!$this->completed()) { vdump('NO RESOLUTION'); vdump($this); debug_dump(); die('HISS'); $this->impossible('no_resolution'); }
	}
	
	public function finish($success=true)
	{
		if ($this->completed()) return;
		if (!is_bool($success)) die ('INVALID SUCCESS');
		$this->invalidate_report();
		$this->unregister_dependancies();
		$this->progressable=false;
		$this->complete=$success;
		
		if ($success) $this->log('success');
		else $this->log('failure');
		
		$this->make_final_calls('complete'); // только несколько типов задач могут сбрасываться и запускаться снова, например, запросу (Request). но в этих случаях их обычно следует воспринимать как независимые задачи.
	}
	
	public function impossible($errors=null)
	{
		if (is_array($errors)) $this->errors=$errors;
		elseif (!is_null($errors)) $this->errors=[$errors];
		$this->finish(false);
	}
	
	public function finish_with_resolution($resolution)
	{
		$this->resolution=$resolution;
		$this->finish();
	}
	
	public function invalidate_report()
	{
		$this->report=null;
	}
	
	public function report()
	{
		if ($this->report!==null) return $this->report;
		$this->report=$this->make_report();
		$this->sign_report($this->report);
		return $this->report;
	}
	
	public function make_report()
	{
		if ($this->complete===false) $report=new Report_impossible($this->errors);
		elseif ($this->complete===true) $report=new Report_resolution($this->resolution);
		elseif ($this->progressable===true) $report=new Report_in_progress();
		elseif ($this->progressable===static::STATE_DEPENDS) $report=new Report_tasks($this->subtasks);
		else { vdump($this); vdump('UNKNOWN STATE 1:'); vdump($this->progressable); exit; }
		return $report;
	}
	
	public $dependancy_calls=[]; // этот массив нужен для одной вещи: если задача теряет необходимость раньше, чем выполнена задача-зависимость, открепить вызов зависимости
	// FIX! если задача уже выполнена, то нужно сразу произвести действия, необходимые при её завершении.
	public function register_dependancy($task, $identifier=null)
	{
		if ($task instanceof Report_task) $task=$task->task;
		elseif (!($task instanceof Task)) { xdebug_print_function_stack(); vdump($task); vdump($this); die('NOT TASK DEP'); }
		if (array_key_exists($task->object_id, $this->subtasks)) return;
		$this->progressable=static::STATE_DEPENDS;
		$this->invalidate_report();
		$this->subtasks[$task->object_id]=$task;
		$call=clone $this->dependancy_callback();
		if ($identifier!==null) $call->post_args=[$identifier];
		// объект вызова создаётся таким образом для того, чтобы функцию можно было наследовать и перегружать.
		if ($task->completed()) { vdump('DEP'); vdump($task); vdump('ME'); vdump($this); debug_dump(); xdebug_print_function_stack(); die('COMPLETED DEPENDANCY'); } // $call($this); // FIX! возможно, при срабатывании этой ветви происходит ошибка.
		else $call->register($task, 'complete');
	}
	
	public function unregister_dependancies()
	{
		foreach ($this->dependancy_calls as $call)
		{
			$call->unregister();
		}
		$this->subtasks=[];
		$this->dependancy_calls=[];
	}
	
	public function register_dependancies($tasks, $identifier=null)
	{
		if ($tasks instanceof Report_tasks) $tasks=$tasks->tasks;
		foreach ($tasks as $key=>$task)
		{
			if ($identifier===null) $ident=null;
			elseif (!is_array($identifier)) $ident=$identifiers;
			elseif (array_key_exists($key, $identifier)) $ident=$identifier[$key];
			else $ident=null;
			$this->register_dependancy($task, $ident);
		}
	}
	
	public function dependancy_resolved($task, $identifier=null)
	{
		$this->log('dep_resolved', ['task'=>$task]);
		unset($this->subtasks[$task->object_id]);
		if (empty($this->subtasks)) $this->dependancies_resolved();	
	}
	
	public function dependancies_resolved()
	{
		$this->unregister_dependancies(); // на случай, если это вызвано искусственно, потому что в зависимостях больше нет нужды.
		$this->progressable=true;
		$this->invalidate_report();
		$this->log('progressable');
		$this->make_calls('progressable');
	}
	
	public $dependancy_callback=null;
	public function dependancy_callback()
	{
		if (is_null($this->dependancy_callback)) $this->dependancy_callback=$this->create_dependancy_callback();
		return $this->dependancy_callback;
	}
	
	public function create_dependancy_callback()
	{
		$call=new Dependancy_call
		(
			function($task, $identifier=null /* идентификатор закладывается при регистрации зависимости и хранится в вызове */)
			{
				$this->dependancy_resolved($task, $identifier);
			}
		);
		$call->master=$this;
		return $call;
	}
	
	public function pool()
	{
		if (!empty($this->pool)) return $this->pool;
		return EntityPool::default_pool();
	}
}

trait Task_inherit_dependancies_success
{
	public function dependancies_resolved()
	{
		parent::dependancies_resolved();
		$this->finish();
	}
}

trait Task_inherit_dependancy_failure
{
	public function dependancy_resolved($task, $identifier=null)
	{
		if ($task->failed()) $this->impossible('inherited_failure');
		parent::dependancy_resolved($task, $identifier);
	}
}

trait Task_steps
{
	//const
		// STEP_INIT=0,
		// у черт не может быть констант, так что эта константа объявлена в Task и доступна любому унаследованному объекту.
		
		// STEP_ДРУГОЙ_ШАГ=1,
		// STEP_ЕЩЁ_ОДИН_ШАГ=2,
		// эта система снова использует константные номера шагов, а не массив или объекты, и вот по какой причине:
		// в отличие от предыдущей системы, подзадачи теперь отдельные объекты, и наследование и изменение поведения будет в них, а не в наследовании метода run_step. поэтому содержимое run_step практически не должно изменяться.
		// Шаги могут передвигать шаг через ++ или назначать шаг вручную, так что шаги не обязаны идти подряд. Просто удобно расположить подряд шаги, которые выполняются подряд. Единственное место, где шаг передвигается на ++ автоматически - это когда предыдущий шаг создал зависимости. Шаг, разбирающий результат отработки зависимостей, должен быть следующим по номеру.

	public
		$step=null;
	
	// эта реализация подразумевает, что невозможность выполнить шаг означает невозможность завершить задачу, а успех шага - соответственно успех всей задачи.
	public function progress()
	{
		if ($this->step===null) $this->step=static::STEP_INIT;
		$result=$this->run_step();
		$this->process_step_report($result);
	}
	
	// следует использвать только у задач, которые переходят только к шагам с более высокими номерами.
	public function progress_to_step($target_step)
	{
		if ($this->step>=$target_step) return;
		
		$try=0;
		while ( ($this->progressable) && (!$this->completed()) && (++$try<=static::MAX_TRIES) && ($this->step<$target_step) )
		{
			$this->progress();
		}
		
		if (!$this->progressable) return $this->report();
		if ($try>static::MAX_TRIES) return false;
		if ($this->step===$target_step) return true;
		die ('BAD TO_STEP FINISH');
	}
	
	public function process_step_report($result)
	{
		if ($this->completed()) return;
		elseif ($result===null) $this->impossible('unknown_step: '.$this->step);
		elseif ($result===true) return;
		elseif ($result instanceof Report_tasks) $result->register_dependancies_for($this);
		elseif ($result instanceof Report_impossible) $this->impossible($result->errors);
		elseif ($result instanceof Report_success)
		{
			if ($result instanceof Report_resolution) $this->resolution=$result->resolution;
			$this->finish();
		}
		else { vdump('UNKNOWN RESULT: '); vdump($result); vdump($this); exit; }
	}
	
	public function dependancies_resolved()
	{
		parent::dependancies_resolved();
		if ($this->step!==null) $this->advance_step();
	}
	
	public function advance_step($new_step=null)
	{
		if ($new_step===null) $this->step++;
		else $this->step=$new_step;
		return true; // чтобы можно было написать return advance_step() в run_step() и одновременно завершить обработку шага, передвинуть шаг и вернуть сведения об успешной обработке шага.
	}
	
	public function repeat_step()
	{
		return true;
	}
	
	// этот метод выполняет действия шага и возвращает следующее:
	// true - следует продолжить обработку шагов. Переключение номера шага должно осуществляться внутри данного метода вручную! Единственное исключение - увеличение шага на 1 при отработке всех зависимостей.
	// null - нераспознанный шаг, ошибка.
	// объект класса Report - данная задача завершена, невозможна или имеет зависимости, которые требуют решения в рамках процесса.
	public abstract function run_step();
}

class Task_delayed_call extends Task implements Task_proxy
{
	public
		$task,
		$final,
		$call;

	public static function with_call($call, $dependancy)
	{
		$task=new static();
		if ($dependancy instanceof Report_tasks) $dependancy=$dependancy->tasks;
		elseif ($dependancy instanceof Report) { vdump($dependancy); xdebug_print_function_stack(); die('BAD DEP'); }
		if ( (is_array($dependancy)) && (count($dependancy)==1) ) $dependancy=reset($dependancy);
		elseif (is_array($dependancy)) $dependancy=new Process_collection($dependancy);
		$task->task=$dependancy;
		$task->call=$call;
		return $task;
	}
	
	public function progress()
	{
		if ($this->final!==null)
		{
			if ($this->final->failed()) $this->impossible('subtask_failed');
			elseif ($this->final->successful()) $this->finish_with_resolution($this->final->resolution);
			return;
		}
		if ($this->task->failed()) $this->impossible('subtask_failed');
		elseif ($this->task->successful())
		{
			$call=$this->call;
			$result=$call();
			if ($result instanceof Report_impossible) $this->impossible($result->errors);
			elseif ($result instanceof Report_success)
			{
				if ($result instanceof Report_resolution) $this->resolution=$result->resolution;
				$this->finish();
			}
			elseif ($result instanceof Report_task)
			{
				$this->task=$result->task;
				$this->register_dependancy($this->task);
			}
			elseif ($result instanceof Task)
			{
				$this->final=$result;
				$this->make_calls('proxy_resolved', $this->final);
				$this->register_dependancy($this->final);
			}
			elseif ($result instanceof Report_tasks) { vdump($result); die ('BAD DELAYED CALL'); }
			else
			{
				$this->resolution=$result;
				$this->finish();
			}
		}
		else
		{
			$this->register_dependancy($this->task);
		}
	}
}
?>