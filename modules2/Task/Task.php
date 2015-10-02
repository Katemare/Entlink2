<?
namespace Pokeliga\Task;

load_debug_concern(__DIR__, 'Task');

// интерфейс для задач, которые служат для отложенного получения результатов: например, отложенное получение шаблона у неподтверждённой сущности. Нужно потому, что необходимый шаблон может требовать некоторой обработки, как только будет получен. Кроме того, такие задачи-оболочки в качестве своего результата сохраняют результат выполнения внутренней задачи.
interface Task_proxy
{
	// конкретных методов не требуется. Главное, чтобы в момент получения целевой задачи объект задействовал крючок "proxy_resolved" с двумя аргументами - собой (ввиду Caller_backreference) и получившейся задачей. иногда в роли результата может быть не задача, а значение - например, в случае обращения к значению сущности.
}

abstract class Task implements \Pokeliga\Entlink\Promise
{
	use \Pokeliga\Entlink\Caller_backreference, \Pokeliga\Entlink\Object_id, Logger_Task;

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
	
	#####################################
	### Реализация интерфейса Promise ###
	#####################################
	
	public function completed() { return $this->complete!==null; }
	public function successful() { return $this->complete===true; }
	public function failed() { return $this->complete===false; }
	public function resolution()
	{
		if ($this->successful()) return $this->resolution;
		elseif ($this->failed()) return $this->report();
		return new \Report_impossible('premature_resolution', $this);
	}
	public function report()
	{
		if ($this->report!==null) return $this->report;
		$this->report=$this->create_report()->sign($this);
		return $this->report;
	}
	private function create_report()
	{
		if ($this->complete===false) $report=new \Report_impossible($this->errors);
		elseif ($this->complete===true) $report=new \Report_resolution($this->resolution);
		elseif ($this->progressable===true) $report=new \Report_in_progress();
		elseif ($this->progressable===static::STATE_DEPENDS) $report=new \Report_deps($this->subtasks);
		else { vdump($this); vdump('UNKNOWN STATE 1:'); vdump($this->progressable); exit; }
		return $report;
	}
	
	public function register_dependancy_for($task, $identifier=null)
	{
		$task->register_dependancy($this, $identifier);
	}
	public function to_task() { return $this; }
	
	########################
	### Полезные функции ###
	########################
	
	public function report_promise($promise=null)
	{
		if ($promise===null) $promise=$this;
		return new \Report_promise($promise, $this);
	}
	public function report_dependancy($deps=null)
	{
		if ($deps===null) $deps=$this;
		if (is_array($deps)) return new \Report_deps($deps, $this);
		else return new \Report_dep($deps, $this);
	}
	public function report_impossible($errors)
	{
		return new \Report_impossible($errors, $this);
	}
	
	public function to_need()
	{
		return new Need_one($this);
	}
	
	public function to_optional_need()
	{
		return new Need_one($this, false);
	}
	
	public function resolution_or_promise()
	{
		if ($this->completed()) return $this->resolution();
		return $this->report_promise();
	}
	
	public function resolution_or_need()
	{
		if ($this->completed()) return $this->resolution();
		return $this->to_need();
	}
	
	public function resolution_or_mandatory_need()
	{
		if ($this->completed()) return $this->resolution();
		return $this->to_mandatory_need();
	}
	
	public function standalone_resolution_or_promise()
	{
		$this->max_standalone_progress();
		return $this->resolution_or_promise();
	}
	
	public function standalone_resolution_or_need()
	{
		$this->max_standalone_progress();
		return $this->resolution_or_need();
	}
	
	public function standalone_resolution_or_mandatory_need()
	{
		$this->max_standalone_progress();
		return $this->resolution_or_mandatory_need();
	}
	
	public function requestless_resolution_or_promise()
	{
		$this->max_requestless_progress();
		return $this->resolution_or_promise();
	}
	
	public function requestless_resolution_or_need()
	{
		$this->max_requestless_progress();
		return $this->resolution_or_need();
	}
	
	public function requestless_resolution_or_mandatory_need()
	{
		$this->max_requestless_progress();
		return $this->resolution_or_mandatory_need();
	}
	
	#########################
	### Механизм нужности ###
	#########################
	
	public function is_needed() { return $this->need>0; }
	public function increase_need_for($target)
	{
		if ($target===$this or $this->is_needed()) $target->increase_need_by($this);
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
	public function set_standalone_need() { $this->increase_need_by($this); }
	public function withdraw_standalone_need() { $this->decrease_need_by($this); }
	public function needs() // генератор
	{
		foreach ($this->subtasks as $task) { yield ($task); }
	}
	
	###########################
	### Оболочка выполнения ###
	###########################
	
	public function max_standalone_progress()
	{
		$try=0;
		while ( ($this->progressable===true) && (++$try<=static::MAX_ITERATIONS) )
		{
			$this->progress();
		}
	}
	
	public function max_requestless_progress()
	{
		if ($this->completed()) return;
		$this->set_standalone_need();
		
		$this->max_standalone_progress();
		if (!$this->completed())
		{
			$process=$this->create_process();
			$process->max_standalone_progress();
		}
		$this->withdraw_standalone_need();
	}
		
	public function complete()
	{
		if ($this->completed()) return;
		$this->log('to_complete');
		
		$this->max_standalone_progress();
		if (!$this->completed())
		{
			$process=$this->create_process();
			$process->complete();
		}
		$this->finalize();
	}
	public function create_process()
	{
		debug('CREATING PROCESS for '.get_class($this).$this->object_id);
		return new Process_single_goal($this);
	}
	
	public function now()
	{
		$this->complete();
		return $this->resolution();
	}
	
	public function finalize()
	{
		if (!$this->completed()) { vdump('NO RESOLUTION'); vdump($this); debug_dump(); die('HISS'); $this->impossible('no_resolution'); }
	}
	
	public function finish($success=true)
	{
		if ($this->completed()) return;
		if ($success instanceof \Report_final) $this->finish_by_report($success);
		elseif ($success instanceof \Pokeliga\Entlink\Promise) $this->finish_by_promise($success);
		elseif (is_bool($success)) $this->finish_by_bool($success);
		else die ('INVALID SUCCESS');
	}
	
	public function success() { $this->finish_by_bool(true); }
	private function finish_by_bool($success=true)
	{
		if ($this->completed()) return;
		$this->invalidate_report();
		$this->unregister_dependancies();
		$this->progressable=false;
		$this->complete=$success;
		
		if ($success)
		{
			$this->log('success');
			$this->on_success();
		}
		else
		{
			$this->on_failure();
			$this->log('failure');
		}
		$this->on_finish();
		
		$this->make_final_calls('complete'); // только несколько типов задач могут сбрасываться и запускаться снова, например, запросы (Request). но в этих случаях их обычно следует воспринимать как независимые задачи.
	}
	
	public function on_success() { }
	public function on_failure() { }
	public function on_finish() { }
	
	public function finish_by_promise($promise)
	{
		if ($this->completed()) return;
		if (!$promise->completed()) die('FINISH BY UNCOMPLETED PROMISE');
		if ($promise->failed()) $this->impossible($promise);
		else $this->finish_with_resolution($promise->resolution());
	}
	public function finish_by_task($task) { $this->finish_by_promise($task); }
	public function finish_by_report($report) { $this->finish_by_promise($report); }
	
	public function impossible($errors=null)
	{
		if ($this->completed()) return;
		if ($errors instanceof \Pokeliga\Entlink\Promise)
		{
			if (!$errors->failed()) throw new \Exception('impossible() by non-failed Promise');
			$errors=$errors->resolution();
		}
		if ($errors instanceof \Pokeliga\Entlink\ErrorsContainer) $errors=$errors->get_errors();
		
		if (is_array($errors)) $this->errors=$errors;
		elseif ($errors!==null) $this->errors=[$errors];
		
		$this->finish_by_bool(false);
	}
	
	public function finish_with_resolution($resolution)
	{
		if ($this->completed()) return;
		$this->resolution=$resolution;
		$this->success();
	}
	
	public function invalidate_report()
	{
		$this->report=null;
	}
	
	public abstract function progress();
	
#############################
### Механизм зависимостей ###
#############################	
	
	public $dependancy_calls=[]; // этот массив нужен для одной вещи: если задача теряет необходимость раньше, чем выполнена задача-зависимость, открепить вызов зависимости
	public function register_dependancy($promise, $identifier=null)
	{
		if (!($promise instanceof \Pokeliga\Entlink\Promise)) die('BAD DEPENDANCY');
		if ($promise->completed())
		{
			$this->completed_dependancy($promise, $identifier);
			return;
		}
		$task=$promise->to_task();	
		if (array_key_exists($task->object_id, $this->subtasks)) return;
	
		$this->progressable=static::STATE_DEPENDS;
		$this->invalidate_report();
		$this->subtasks[$task->object_id]=$task;
		$call=clone $this->dependancy_callback();
		if ($identifier!==null) $call->post_args=[$identifier];
		$call->register($task, 'complete');
	}
	
	public function register_dependancies($tasks, $identifier=null)
	{
		if ($tasks instanceof \Pokeliga\Entlink\Promise)
		{
			$this->register_dependancy($tasks, $identifier);
			return;
		}
		if ($tasks instanceof \Pokeliga\Enlink\TasksContainer) $tasks=$tasks->get_tasks();
		
		foreach ($tasks as $key=>$task)
		{
			if ($identifier===null) $ident=null;
			elseif (!is_array($identifier)) $ident=$identifier;
			elseif (array_key_exists($key, $identifier)) $ident=$identifier[$key];
			else $ident=null;
			$this->register_dependancy($task, $ident);
		}
	}
	
	public function dependancy_resolved($task, $identifier=null)
	{
		$this->log('dep_resolved', ['task'=>$task]);
		unset($this->subtasks[$task->object_id]);
		$this->completed_dependancy($task, $identifier);
		if (empty($this->subtasks)) $this->dependancies_resolved();	
	}
	
	public function dependancies_resolved()
	{
		if ($this->completed()) return;
		$this->unregister_dependancies(); // на случай, если это вызвано искусственно, потому что в зависимостях больше нет нужды.
		$this->progressable=true;
		$this->invalidate_report();
		$this->log('progressable');
		$this->make_calls('progressable');
	}
	
	public function completed_dependancy($promise, $identifier=null)
	{
		if ($promise->successful()) $this->on_successful_dependancy($promise, $identifier);
		else $this->on_failed_dependancy($promise, $identifier);
		$this->on_completed_dependancy($promise, $identifier);
	}
	public function on_successful_dependancy($promise, $identifier=null) { }
	public function on_failed_dependancy($promise, $identifier=null) { }
	public function on_completed_dependancy($promise, $identifier=null) { }
	
	public function unregister_dependancies()
	{
		foreach ($this->dependancy_calls as $call)
		{
			$call->unregister();
		}
		$this->subtasks=[];
		$this->dependancy_calls=[];
	}
	
	public $dependancy_callback=null;
	public function dependancy_callback()
	{
		if ($this->dependancy_callback===null) $this->dependancy_callback=$this->create_dependancy_callback();
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
		return \Pokeliga\Entity\EntityPool::default_pool();
	}
}

trait Task_resetable
{
	// во многих подходах Promise'ы не должны возвращаться к исходному состоянию. но это используется преимущественно с Request'ами к БД, которые может и можно было бы разделить на два класса - задача и менеджер - но зачем?
	// но есть одно но. задача возвращается в "пустое" состояние, а не в то, в котором она была создана. например, некоторые задачи создаются с зарегистрированными зависимостями и не знали бы, что делать, если первый проход произойдёт в других условиях. так что этот метод для специального, осторожного пользования.
	public function reset()
	{
		$this->progressable=true;
		$this->complete=null;
		$this->resolution=null;
		$this->unregister_dependancies();
		$this->invalidate_report();
	}
}

trait Task_inherit_dependancy_failure
{
	public function on_failed_dependancy($promise, $identifier=null)
	{
		$this->impossible($promise);
		parent::on_failed_dependancy($promise, $identifier);
	}
}

// эти задачи могут закончиться только результатом "истина" либо невозможностью. результат "ложь" превращается в невозможность, остальные конвертируются в "истину".
trait Task_binary
{
	public
		$task_binary=true;
		
	public function finish_boool($success=true)
	{
		if ($success and $this->task_binary)
		{
			if ($this->resolution===false) $success=false;
			else $this->resolution===true;
		}
		parent::finish_by_bool($success);
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
		elseif ($result instanceof \Report_dependant)
		{
			$result->register_dependancies_for($this);
			if ($this->progressable===true) $this->move_forward(); // если все зависимости отказались уже выполненными.
		}
		elseif ($result instanceof \Report_final) $this->finish_by_report($result);
		else { vdump('UNKNOWN RESULT: '); vdump($result); vdump($this); exit; }
	}
	
	public function dependancies_resolved()
	{
		parent::dependancies_resolved();
		$this->move_forward();
	}
	
	public function move_forward()
	{
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
	// объект класса \Report - данная задача завершена, невозможна или имеет зависимости, которые требуют решения в рамках процесса.
	public abstract function run_step();
}

// FIX: возможно, после обновления механизмов будет не нужна.
class Task_delayed_call extends Task implements Task_proxy
{
	public
		$task,
		$final,
		$call;

	public static function with_call($call, $dependancy)
	{
		$task=new static();
		if ($dependancy instanceof \Report_tasks) $dependancy=$dependancy->tasks;
		elseif ($dependancy instanceof \Report) { vdump($dependancy); xdebug_print_function_stack(); die('BAD DEP'); }
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
			if ($result instanceof \Report_impossible) $this->impossible($result->errors);
			elseif ($result instanceof \Report_success)
			{
				if ($result instanceof \Report_resolution) $this->resolution=$result->resolution;
				$this->finish();
			}
			elseif ($result instanceof \Report_task)
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
			elseif ($result instanceof \Report_tasks) { vdump($result); die ('BAD DELAYED CALL'); }
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

// это несколько более умная версия вызова, которая знает о механизме зависимостей. Она хранит сведения о том, какие объекты связывает, чтобы после срабатывания (или из-за потери актуальности) убрать себя из списков и не мешать работе уборщика мусора, а также не создавать лишних, уже не нужных вызовов.
class Dependancy_call extends \Pokeliga\Entlink\Call
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
			if ($this->master instanceof Task) $this->host->decrease_need_by($this->master);
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
?>