<?

namespace Pokeliga\Entlink
{
	interface TasksContainer
	{
		public function get_tasks();
	}
}

namespace
{
	// обозначает отчёты, говорящие о том, что для получения данных или выполнения операции требуется стороннее завершение задач.
	interface Report_delay extends \Pokeliga\Entlink\TasksContainer
	{
		public function delayed_by();
		public function register_dependancies_for($task, $identifier=null); // тот же метод, что в \Pokeliga\Entlink\Promise - по сигнатуре и действию.
	}

	// обозначает отчёты, содержащие зависимости, а не конечный результат.
	interface Report_dependant extends Report_delay
	{
		public function get_deps();
	}

	/*
	следует различать Report_to_promise, которая позволяет конвертировать отчёт в обещание; и Report_promise, который отчитывается непосредственно о задаче-обещании.
	разница в Report_dependant и Report_promise в следующем:
	- Report_dependant сообщает о задачах, которые необходимо выполнить для продолжения какого-либо процесса или получения результата от вызова.
	- Report_promise содержит задачу, результат выполнения которой и является искомым данным.
	*/

	abstract class Report_tasks extends Report implements Report_delay
	{
		public
			$tasks=[],
			$process=null;
		
		public function __construct($tasks, $by=null)
		{
			parent::__construct($by);
			$this->tasks=$tasks;
		}
		public function add_task($task) { $this->tasks[]=$task; }
		
		public function get_tasks() { return $this->tasks; }
		
		public function to_need()
		{
			return new \Pokeliga\Task\Need_all($this->tasks);
		}
		
		public function to_optional_need()
		{
			return new \Pokeliga\Task\Need_all($this->tasks, false);
		}
		
		public function get_process()
		{
			if ($this->process===null) $this->process=new Process_collection_absolute($this->tasks);
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

	// облегчает возвращение отчёта с одной операцией.
	abstract class Report_task extends Report_tasks
	{
		public
			$task=null;
		
		public function __construct($task, $by=false)
		{
			parent::__construct([$task], $by);
			$this->task=$task;
		}
		public function add_task($task) { die('SINGLE TASK REPORT'); }
		// это нарушает тот принцип, что потомок всегда должен реализовывать функции родителя, однако Report_task просто не следует использовать для создания отчёта с неизвестным числом задач.
		
		public function get_promise()
		{
			return $this->task;
		}
		
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

	// обозначает, что единственная содержащаяся задача и даст искомый результат. связь с Promise можно встроить и в Report_task, но это бесполезно и даже вредно, потому что хотя по составу объект может выполнить все те же действия, он может предполагать совершенно другой смысл ("выполни это, чтобы процедура могла продлиться", а не "выполни это и у него же спроси результат").
	class Report_promise extends Report_task implements \Pokeliga\Entlink\FinalPromise, \Pokeliga\Entlink\PromiseLink
	{
		use \Pokeliga\Entlink\Promise_from_link;
		
		public function get_promise() { return $this->task; }
	}
	// COMP: большинство мест, генерирующих Report_task, подразумевают задачу, которая в результате выполнения даст искомое или сделает искомое. Там, где это не так, следует поменять класс на Report_dep!

	// обозначают, что задачи являются зависимостями. можно было бы сделать это трейтом, но пока выгодны от этого нет.
	class Report_deps extends Report_tasks implements Report_dependant
	{
		public function get_deps() { return $this->tasks; }
	}
	class Report_dep extends Report_task implements Report_dependant
	{
		public function get_deps() { return $this->tasks; } // ведь Report_task уже имеет массив, содержащий 
	}
}
?>