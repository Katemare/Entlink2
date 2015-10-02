<?

namespace Pokeliga\Entlink
{
	/**
	* Объект, являющийся контейнером задач (объектов \Pokeliga\Task\Task).
	*/
	interface TasksContainer
	{
		/**
		* Возвращает массив задач.
		* @return array Массив объектов \Pokeliga\Task\Task
		*/
		public function get_tasks();
	}
}

namespace
{
	/**
	* Обозначает отчёты, говорящие о том, что для получения данных или выполнения операции требуется стороннее завершение задач.
	*/
	interface Report_delay extends \Pokeliga\Entlink\TasksContainer
	{
		/**
		* Возвращает массив задач, требующих разрешения для преодоления задержки.
		* @return array Массив объектов \Pokeliga\Task\Task
		*/
		public function delayed_by();
		
		/**
		* Регистрирует задачи, задерживающие выполнения, у целевой задачи.
		* @param \Pokeliga\Task\Task $task Задача, у которой следует определить зависимости.
		* @param null|string|int|array $identifier Идентификаторы, по которым при желании можно отличить одну зависимость от другой.
		* @see \Pokeliga\Task\Task::register_dependancy() О применении идентификаторов зависимостей.
		*/
		public function register_dependancies_for($task, $identifier=null);
	}

	/**
	* Обозначает отчёты, содержащие зависимости, а не конечный результат. После завершения зависимостей операцию можно продолжить или вызвать повторно.
	*/
	interface Report_dependant extends Report_delay
	{
		/**
		* Возвращает массив задач-зависимостей.
		* @return array Массив объектов \Pokeliga\Task\Task
		*/
		public function get_deps();
	}

	/**
	* Отчёт, содержащий задачи.
	*/
	abstract class Report_tasks extends Report implements Report_delay
	{
		/**
		* @var array $tasks Массив задач \Pokeliga\Task\Task.
		*/
		protected
			$tasks=[];
		
		/**
		* @var null|\Pokeliga\Task\Process $process Процесс, соответствующий выполнению всех задач.
		*/
		private
			$process=null;
		
		/**
		* @param array $tasks Массив задач \Pokeliga\Task\Task.
		*/
		public function __construct($tasks, $by=null)
		{
			parent::__construct($by);
			$this->tasks=$tasks;
		}
		
		/**
		* Добавляет задачу в отчёт. Только для этапа формирования отчёта!
		* @param \Pokeliga\Task\Task $task Задача к добавлению.
		*/
		public function add_task($task) { $this->tasks[]=$task; }
		
		public function get_tasks() { return $this->tasks; }
		
		/**
		* Конвертирует отчёт в необходимость.
		* @return \Pokeliga\Task\Need
		* @see \Pokeliga\Task\Need
		*/
		public function to_need()
		{
			return new \Pokeliga\Task\Need_all($this->tasks);
		}
		
		/**
		* Конвертирует отчёт в необязательную необходимость.
		* @return \Pokeliga\Task\Need
		* @see \Pokeliga\Task\Need Определение и назначение необходимостей.
		*/
		public function to_optional_need()
		{
			return new \Pokeliga\Task\Need_all($this->tasks, false);
		}
		
		/**
		* Возвращает процесс, в результате выполнения которого все содержащиеся задачи будут выполнены.
		* @return \Pokeliga\Task\Process
		*/
		public function get_process()
		{
			if ($this->process===null) $this->process=new Process_collection($this->tasks);
			return $this->process;	
		}
		
		public function register_dependancies_for($task, $identifier=null)
		{
			$task->register_dependancies($this->tasks, $identifier);
		}
		
		public function delayed_by() { return $this->tasks; }
		
		public function human_readable()
		{
			/*
			$result=[];
			foreach ($this->tasks as $task)
			{
				$result[]=$task->human_readable();
			}
			*/
			return parent::human_readable().': '.count($this->tasks).' tasks'; //.implode(', ', $result);
		}
		
	}

	/**
	* Облегчает возвращение и обработку отчёта с одной операцией.
	*/
	abstract class Report_task extends Report_tasks
	{
		/**
		* @var \Pokeliga\Task\Task $task Собственно единственная задача в отчёте.
		*/
		protected
			$task;
		
		/**
		* @param \Pokeliga\Task\Task $task Задача для отчёта.
		*/
		public function __construct($task, $by=false)
		{
			parent::__construct([$task], $by);
			$this->task=$task;
		}
		
		
		/**
		* @throws \BadMethodCallException Всегда, потому что при формировании отчёта с одной задачей нельзя добавлять к нему задачи. Это нарушает тот принцип, что потомок всегда должен реализовывать функции родителя, однако Report_task просто не следует использовать для создания отчёта с неизвестным числом задач.
		*/
		public function add_task($task) { throw new \BadMethodCallException('adding more tasks to Report_task'); }
		
		public function get_process()
		{
			if ($this->process===null) $this->process=$this->task->create_process();
			return $this->process;
		}
		
		public function to_need()
		{
			return $this->task->to_need();
		}
		
		public function to_optional_need()
		{
			return $this->task->to_optional_need();
		}
		
		public function register_dependancies_for($task, $identifier=null)
		{
			$task->register_dependancy($this->task, $identifier);
		}
	}

	/**
	* Содержит задачу, результат выполнения которой и является искомым данным.
	*/
	class Report_promise extends Report_task implements \Pokeliga\Entlink\FinalPromise, \Pokeliga\Entlink\PromiseLink
	{
		// Связь с Promise можно встроить и в Report_task, но это бесполезно и даже вредно, потому что хотя по составу объект может выполнить все те же действия, он может предполагать совершенно другой смысл ("выполни это, чтобы процедура могла продлиться", а не "выполни это и у него же спроси результат").
		
		use \Pokeliga\Entlink\Promise_from_link;
		
		public function get_promise() { return $this->task; }
	}

	/**
	* Обозначает, что задачи являются зависимостями.
	*/
	class Report_deps extends Report_tasks implements Report_dependant
	{
		public function get_deps() { return $this->tasks; }
	}
	/**
	* Обозначает, что задача являются зависимостью.
	*/
	class Report_dep extends Report_task implements Report_dependant
	{
		public function get_deps() { return $this->tasks; } // ведь Report_task уже имеет массив, содержащий 
	}
}
?>