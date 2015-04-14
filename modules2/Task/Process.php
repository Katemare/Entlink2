<?
load_debug_concern('Task', 'Process');

// в рамках процесса статус DEPENDS означает, что следующим шагом должно быть выполнение запросов из $this->requests, и без этого ни одна задача не может продвинуться.
// также Process не регистрирует свои подзадачи как зависимости, хотя сам может быть зависимостью для других задач.
abstract class Process extends Task
{
	use Logger_Process;
	
	const
		MAX_ACTIVE_PASSES=10000;
	
	public
		$requests=[],
		$active_tasks=[],
		$delayed_tasks=[],
		$delayed_processes=[]; // им нужно сообщать о том, что были произведены все запросы к БД, потмоу что процессы не используют обычный механизм зависимостей.
	
	public static function complete_tasks(...$args)
	{
		$process=new static(...$args);
		$process->complete();
		return $process;
	}
	
	public function create_process()
	{
		return $this;
	}
	
	public function complete()
	{
		$this->log('to_complete');
		
		$try=0;
		$unresolved=$this->subtasks;
		$this->increase_need_by($this);
		while ( ($this->progressable===true) && (++$try<=static::MAX_ITERATIONS) )
		{
			$this->log('new_cycle', ['try'=>$try]);
			$this->max_standalone_progress();
			if ($this->completed())	break;
			elseif ($this->progressable===static::STATE_DEPENDS) $this->make_requests();
			else { vdump($this); die ('UNKNOWN PROCESS STATE'); }
		}
		$this->decrease_need_by($this);
		$this->finalize();
		
	}
	
	public function make_requests()
	{
		$this->log('requests');
		foreach ($this->requests as $request)
		{
			if (!$request->is_needed()) continue; // удалять из массива не требуется, они и так удалятся в рамках made_requests()
			$this->log('request', ['request'=>$request]);
			$request->max_standalone_progress();
			if ($this->completed()) return;			
			$this->log('subtask_report', ['subtask'=>$request]);
		}
		if ($this->progressable===static::STATE_DEPENDS) $this->made_requests();
	}
	
	public function made_requests()
	{
		$this->progressable=true;
		foreach ($this->delayed_processes as $process)
		{
			$process->made_requests();
		}
		$this->invalidate_report();
		$this->requests=[];
		$this->make_calls('progressable');
	}
	
	public function progress()
	{
		$this->max_standalone_progress();
	}
	
	// максимальный прогресс задач без обращения к БД
	public function max_standalone_progress()
	{
		$over_limit=false;
		foreach ($this->active_tasks(static::MAX_ACTIVE_PASSES) as $key=>$task)
		{
			if ($task===false)
			{
				$over_limit=true;
				break;
			}

			if ( ($task->progressable===true) && ($task->is_needed()) )
			{
				$this->log('progressing_subtask', ['subtask'=>$task]);
				$task->max_standalone_progress();
			}
			
			if ($this->completed()) return;
			
			if ($task->completed())
			{
				$this->remove_completed($task);
				continue;
			}
			elseif (!$task->is_needed())
			{
				$this->remove_subtask($task);
				$this->log('drop_unneeded', ['subtask'=>$task]);
				continue;
			}
			elseif ( ($report=$task->report()) instanceof Report_tasks)
			{
				$this->add_dependancies($report->tasks, $task);
				$this->delay_subtask($task);
			}
			else { vdump($report); vdump($task); die('BAD TASK STATE'); }
		}
		
		if ($this->completed()) return;
		if ($over_limit)
		{
			die('ИЗБЫТОК ОБРАБАТЫВАЕМЫХ ЗАДАЧ :( где-то бесконечный цикл или недостаточная оптимизация.');
			$this->impossible('endless_process');
		}
		elseif (!empty($this->requests))
		{
			$this->progressable=static::STATE_DEPENDS;
			$this->invalidate_report();
		}
		else
		{
			$this->no_progress_possible();
		}
		
		$this->log('progress_result');
		// если процесс максимально продвинул задачи и, значит, все они застряли, но ни одна ни требует запросов - до завершения процесса невозможно.
	}
	
	// некоторые процессы в таком случае могут извлечь дополнительные задачи и продолжить прогресс.
	public function no_progress_possible()
	{
		$this->impossible('no_progress_possible');
	}
	
	// генератор.
	public function active_tasks($limit=self::MAX_ACTIVE_PASSES)
	{
		// $this->debug('GEN, '.count($this->active_tasks).' ACTIVE TASKS of '.$this->object_id);
		$c=0;
		while ( (!empty($this->active_tasks)) && ($c<$limit) )
		{
			if (count($this->active_tasks)==0) break;
			$task=current($this->active_tasks); // после прохода по задаче задача должна так или иначе выписаться из активных, и текущая позиция изменится. всё равно на какую в данном случае.
			if ($task===false) $task=reset($this->active_tasks); // наша песня хороша, начинай с начала.
			yield key($this->active_tasks)=>$task;
			$c++;
		}
		if ($c>=$limit) yield false;
	}
	
	public function make_report()
	{
		if ($this->progressable===static::STATE_DEPENDS)
		{
			if (empty($this->requests)) // и так должно быть обнаружено выше, но на всякий случай.
			{
				$this->finalize();
				return parent::make_report();
			}
			return new Report_tasks($this->requests);
		}	
		else return parent::make_report();
	}
	
	public function add_subtask($task)
	{
		if ($task->completed()) return;
		
		$this->log('adding_subtask', ['subtask'=>$task]);
		$request=($task instanceof Request);
		if ($request) $pool=&$this->requests;
		else $pool=&$this->subtasks;
			
		if (array_key_exists($task->object_id, $pool)) return;		
		$pool[$task->object_id]=$task;
		$report=$task->report();
	
		if ($report instanceof Report_tasks)
		{
			if (!$request) $this->delay_subtask($task);
			$this->add_subtasks($report->tasks);
		}
		elseif (!$request) $this->activate_task($task);
	}
	
	// пока только для обычных задач! запросы удаляются все разом после обращения к БД.
	// это единственное место, где задачи удаляются.
	public function remove_subtask($task)
	{
		$this->deactivate_task($task);
		$this->clear_delay($task);
		unset($this->subtasks[$task->object_id]);
	}
	
	public function add_subtasks($tasks)
	{
		if ($tasks instanceof Task) return $this->add_subtask($tasks);
		
		foreach ($tasks as $task)
		{
			$this->add_subtask($task);
		}
	}
	
	public function add_dependancies($deps, $task)
	{
		foreach ($deps as $dep)
		{
			$this->add_dependancy($dep, $task);
		}
	}
	
	// выделено в метод для того, чтобы наследуемые классы могли выполнять дополнительные действия в связи с этим.
	public function add_dependancy($dep, $task)
	{
		$this->add_subtask($dep);
	}
	
	// это единственное место, где задачи попадают в активные.
	public function activate_task($task)
	{
		if ($task->completed()) return;
		$this->clear_delay($task);
		$this->active_tasks[$task->object_id]=$task;
		// $this->debug(count($this->active_tasks).' ACTIVE TASKS of '.$this->object_id);
	}
	// это единственное место, где задачи выписываются из активных.
	public function deactivate_task($task)
	{
		// $this->debug('DEACTIVATE, '.count($this->active_tasks).' ACTIVE TASKS NOW of '.$this->object_id);
		unset($this->active_tasks[$task->object_id]);
	}
	
	public $call_progressable=[];
	// это единственное место, где задачи попадают в задержанные.
	public function delay_subtask($task)
	{
		$this->deactivate_task($task);
		$this->delayed_tasks[$task->object_id]=$task;
		if ($task instanceof Process) $this->delayed_processes[$task->object_id]=$task;
		if (empty($this->call_progressable[$task->object_id]))
		{
			$task->add_call($this->delay_resolved_call(), 'progressable');
			$task->add_call($this->remove_completed_call(), 'complete');
			$this->call_progressable[$task->object_id]=true;
		}
	}
	// это единственное место, где задачи выписываются из задержанных.
	public function clear_delay($task)
	{
		unset($this->delayed_tasks[$task->object_id]);
		unset($this->delayed_processes[$task->object_id]);
	}
	
	public $delay_resolved_call;
	public function delay_resolved_call()
	{
		if ($this->delay_resolved_call===null)  $this->delay_resolved_call=function($task) { $this->activate_task($task); };
		return $this->delay_resolved_call;
	}
	
	public $remove_completed_call;
	public function remove_completed_call()
	{
		if ($this->remove_completed_call===null)  $this->remove_completed_call=function($task) { $this->remove_completed($task); };
		return $this->remove_completed_call;
	}
	
	// может быть вызвано дважды, если задача уже попадала в задержанные. в таком случае второй раз ничего не происходит.
	public function remove_completed($task)
	{
		if (!array_key_exists($task->object_id, $this->subtasks)) return;
		if (!$task->completed()) return;
		$this->log('removing_subtask', ['subtask'=>$task]);
		$this->remove_subtask($task);
	}
	
	public function register_dependancy($task, $identifier=null)
	{
		die('PROCESS CANT HAVE DEPENDANCIES');
	}
}

class Process_single_goal extends Process
{
	public
		$goal;
		
	public function __construct($goal)
	{
		parent::__construct();
		
		$this->add_subtask($goal);
		$this->goal=$goal;
		// $this->increase_need_for($goal); // нет смысла, потому что процесс стартует с нулевой нуждой.
		$this->resolution=&$goal->resolution;
		
		if ($goal->completed()) return $this->goal_completed();
		
		$goal->add_call
		(
			function() { $this->goal_completed(); },
			'complete'
		);
	}
	
	public function goal_completed()
	{
		$this->log('goal_completed', ['goal'=>$this->goal]);
		// $this->decrease_need_for($this->goal); // нет смысла, потому что выполненный процесс сам перестанет быть нужен, да и выполненная цель в любом случае не подлежит дальнейшему выполнению.
		if ($this->goal->successful()) $this->finish();
		else $this->impossible('goal_failed');
	}
	
	public function needs() { yield $this->goal; }
}

class Process_collection extends Process
{
	public $goals=[];

	public function __construct($tasks)
	{
		parent::__construct();
		$this->register_goals($tasks);
		if (empty($this->goals)) $this->finish();
		if ($this->completed()) return;
		$this->apply_goals();
	}
	
	public function register_goals($tasks)
	{
		foreach ($tasks as $task)
		{
			$result=$this->register_goal($task);
			if (is_bool($result))
			{
				if ($result===true) $this->finish();
				else $this->impossible('bad_goal');
				return;
			}
		}
	}
	
	public function apply_goals($tasks=null)
	{
		if ($tasks===null) $tasks=$this->goals;
		$this->add_subtasks($tasks);
	}
	
	public function register_goal($task)
	{
		if ($task->completed()) return;
		if (array_key_exists($task->object_id, $this->goals)) return;
		$this->increase_need_for($task); // обычно ничего не делает, потому что эта часть выполняется при создании объекта, когда у него самого нужда ещё 0.
		$this->goals[$task->object_id]=$task; // гарантирует, что ключи соответствуют задачам.
		$task->add_call
		(
			function($task)
			{
				$this->goal_completed($task);
			},
			'complete'
		);			
	}
	
	public function goal_completed($task)
	{
		unset($this->goals[$task->object_id]);
		$this->decrease_need_for($task);
		$this->make_calls('goal_completed', $this);
		if (empty($this->goals)) $this->finish();
	}
	
	public static function from_reports($reports)
	{
		$tasks=[];
		foreach ($reports as $report)
		{
			if (! ($report instanceof Report_tasks)) die ('BAD GOALS REPORT');
			$tasks=array_merge($tasks, $report->tasks);
		}
		return new static($tasks);
	}
	
	public function finish($success=true)
	{
		$this->drop_needs();
		parent::finish($success);
	}
	
	public function needs()
	{
		foreach ($this->goals as $goal) { yield $goal; }
	}
}

class Process_collection_absolute extends Process_collection
{
	public function register_goal($task)
	{
		if ($task->failed()) return false;
		return parent::register_goal($task);
	}
	
	public function goal_completed($task)
	{
		if ($task->failed()) $this->impossible('goal_failed');
		else parent::goal_completed($task);
	}
}

/*
	этот процесс выполняет задачи в строгом порядке их добавления. кроме того, о завершении каждой задачи он шлёт вызов и, если получает знак, завершается. нужно для задач в духе "проверить 500 записей сложной проверкой, но остановитсья после 10 удачных проверок). если этот процесс не является верхним, то запросы к БД он передаёт наверх, но свои задачи решает сам.
	
	Логика такая:
	1)		Выполнять первую цель и зависимости, которые она присылает.
	1.1) 	Если цель выполнена (не важно, все ли выполнены её зависимости и успешна ли она), сделать вызов о выполненной цели. Возможно, прекратить работу после этого согласно результату вызова.
	1.2)	Если цель требует запроса в БД, преступить к выполнению второй цели.
	2)		Выполнять вторую цель и зависимости, которые она присылает. Если вдруг первая цель или её зависимости становятся доступными, сразу переключиться на них (см. п. 1).
	2.1)	Если вторая цель выполнена (не важно, выполнены ли её зависимости и успешна ли она), сделать вызова о выполненной цели. Вторая цель может быть выполнена до первой! В вызове предоставляются сведения о порядке.
	2.2)	Если вторая цель не выполнена, продолжить со следующей целью...
	3) Если все цели достигли тупика и процесс ещё не готов, то сделать запросы в БД. Вернуться к п.1.
*/
class Process_prioritized extends Process_collection
{
	const
		DEFAULT_BLOCK_PASTE=0.2;
		
	public
		$active_tasks, // незачем создавать массив, которым всё равно не будем пользоваться.
		
		$ordered_goals=[],
		$priority_by_task_id=[],
		$next_goal_order=0,
		
		$blocks=[],
		$current_block=0,
		$block_by_task_id=[],
		$block_size,
		$block_bit; // если в следующем, последнем блоке осталось такое количество, то оно присовокупляется к предыдущему блоку. чтобы не было блоков в 1.
	
	public function __construct($tasks, $block_size=null, $block_paste=self::DEFAULT_BLOCK_PASTE)
	{
		if ($block_size!==null) $this->set_block_size($block_size, $block_paste);
		parent::__construct($tasks);
	}
	
	public function set_block_size($block_size, $block_paste=self::DEFAULT_BLOCK_PASTE)
	{
		if ($block_size<1) die('BAD BLOCK SIZE');
		$this->block_size=(int)$block_size;
		if ($block_paste!==null) $this->block_bit=(int)($this->block_size*$block_paste);
	}
	
	public function is_blocked() { return $this->block_size!==null; }
	
	public function register_goals($tasks)
	{
		$result=parent::register_goals($tasks);
		if ($this->completed()) return;
		$this->determine_priorities();
		if ($this->is_blocked()) $this->determine_blocks();
	}
	
	public function apply_goals($tasks=null)
	{
		if ( ($tasks!==null) || (!$this->is_blocked()) )
		{
			parent::apply_goals($tasks);
			return;
		}
		
		// если мы попали сюда, то это блочная обработка и общий вызов.
		parent::apply_goals(reset($this->blocks));
	}
	
	public function register_goal($task)
	{
		$unique=!array_key_exists($task->object_id, $this->goals);
		
		$result=parent::register_goal($task);
		if (is_bool($result)) return $result; // истина означает успех процесса, а ложь - плохую задачу и невозможность двигать процесс.
		
		if ( ($unique) && (!($task instanceof Request)) ) // запросы к БД выполняются в обычном порядке.
		{
			$order=$this->next_goal_order++;
			$this->ordered_goals[$order]=$task;
		}
	}
	
	public function determine_priorities()
	{
		$this->active_tasks=new SplPriorityQueue();
		
		$max_order=$this->next_goal_order-1;
		// наивысший приоритет у нулевой цели, а низший - нулевой - у последней цели, порядок которой на единицу меньше, чем следующий свободный порядок. у целей порядок чётный, у их зависимостей - нечётный, на единицу больше, чем у соответствующей цели.
		
		foreach ($this->ordered_goals as $order=>$goal)
		{
			$this->priority_by_task_id[$goal->object_id]=$max_order-$order;
		}
	}
	
	public function determine_blocks()
	{
		$this->blocks=array_chunk($this->goals, $this->block_size, true); // сохраняются индексы, являющиеся айди задач.
		if ( ($this->block_bit!==null) && (count($this->blocks)>1) )
		{
			$last_block=end($this->blocks);
			if (count($last_block)<=$this->block_bit)
			{
				// мы знаем, что функция array_chunk заполняет ключи от 0 и выше.
				$last_key=key($this->blocks);
				$this->blocks[$last_key-1]+=$this->blocks[$last_key]; // поскольку индексы не совпадают.
				unset($this->blocks[$last_key]);
			}
		}
		foreach ($this->blocks as $block_id=>$block)
		{
			foreach ($block as $task_id=>$task)
			{
				$this->block_by_task_id[$task_id]=$block_id;
			}
		}
	}
	
	public function add_dependancy($dep, $task)
	{
		if (!($dep instanceof Request))
		{
			if (!array_key_exists($task->object_id, $this->priority_by_task_id)) die('BAD DEPENDANCY SOURCE');
			$priority=$this->priority_by_task_id[$task->object_id];
			if (array_key_exists($task->object_id, $this->goals)) $priority-=0.5; // если это зависимость 
			$this->priority_by_task_id[$dep->object_id]=$priority;
		}
		parent::add_dependancy($dep, $task);
	}
	
	public function activate_task($task)
	{
		if ($task->completed()) return;
		$this->clear_delay($task);
		if (!array_key_exists($task->object_id, $this->priority_by_task_id))
		{
			$this->impossible('bad_activated_task');
			return;
		}
		$priority=$this->priority_by_task_id[$task->object_id];
		$this->active_tasks->insert($task, $priority);
	}
	
	// к сожалению, из SplPriorityQueue нельзя удалить элемент другим образом, кроме как достать его в порядке очереди. поэтому остаётся только ждать этого и отсеять задачу по тому, что она не может быть продвинута.
	public function deactivate_task($task) { }
	
	public function active_tasks($limit=self::MAX_ACTIVE_PASSES)
	{
		$c=0;
		while ( (!$this->active_tasks->isEmpty()) && ($c<$limit) )
		{
			$task=$this->active_tasks->extract();
			yield $task->object_id=>$task;
			$c++;
		}
		if ($c>=$limit) yield false;
	}
	
	public function goal_completed($task)
	{
		parent::goal_completed($task);
		if (!$this->is_blocked()) return;
		if ($this->completed()) return;
		
		$block_id=$this->block_by_task_id[$task->object_id];
		unset($this->blocks[$block_id][$task->object_id]);
		if (empty($this->blocks[$block_id]))
		{
			// при этом все блоки пустыми быть не могут потому, что тогда процесс бы уже завершился и мы бы сюда не попали.
			unset($this->blocks[$block_id]);
			if ($block_id==$this->current_block)
			{
				reset($this->blocks);
				$new_block=reset($this->blocks);
				$this->current_block=key($this->blocks);
				$this->apply_goals($new_block);
			}
		}
	}
}
?>