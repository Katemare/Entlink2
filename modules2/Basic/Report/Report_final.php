<?

namespace
{
	/**
	* Такие отчёты обозначают итоговое, а не промежуточное состояние, не предполагающее дальнейшее выполнение.
	* Следует заметить, что эти отчёты не обязательно происходят от выполненных задач! они могут быть также результатом вызова метода.
	*/
	abstract class Report_final extends Report implements \Pokeliga\Entlink\FinalPromise
	{
		public function completed()		{ return true; }
		public function now()			{ return $this->resolution(); }
		public function failed()		{ return !$this->successful(); }
		public function get_promise()	{ return $this; }
		public function register_dependancy_for($task, $identifier=null)
		{
			$task->register_dependancy($this, $identifier);
		}
		public function to_task()
		{
			throw new \Exception('precompleted Promise to task');
		}
	}

	/**
	* Говорит о невозможности провести операцию или получить значение.
	*/
	class Report_impossible extends Report_final
	{
		/**
		* @var array $errors Список ошибок (пока строковых).
		*/
		private
			$errors=[];
		
		/**
		@param null|array|string|\Pokeliga\Entlink\ErrorsContainer $errors Список или источник ошибок.
		*/
		public function __construct($errors=null, $by=null)
		{
			if (empty($errors)) return;
			if (is_array($errors)) $this->errors=$errors;
			elseif ($errors instanceof \Pokeliga\Entlink\ErrorsContainer) $this->errors=$errors->get_errors();
			else $this->errors=[$errors];
			parent::__construct($by);
		}
		
		public function human_readable()
		{
			return parent::human_readable().': '.var_export($this->errors, true);
		}
		
		public function successful()	{ return false; }
		public function resolution()	{ return $this; }
		
		public function get_errors()	{ return $this->errors; }

	}

	/**
	* Успешное завершение, не предполагающее получение значения.
	*/
	class Report_success extends Report_final
	{
		public function successful()	{ return true; }
		public function resolution()	{ return true; }
		public function get_errors()	{ return; }
	}

	/**
	* Успешное завершение, предполагающее итоговое значение.
	*/
	class Report_resolution extends Report_success
	{
		/**
		* @var mixed $resolution Собственно итоговое значение - что угодно кроме медиатора.
		*/
		private $resolution;
		
		/**
		* @param mixed $resolution Собственно итоговое значение - что угодно кроме медиатора.
		*/
		public function __construct($resolution=null, $by=null)
		{
			parent::__construct($by);
			$this->resolution=$resolution;
		}
		
		public function human_readable()
		{
			if (is_object($this->resolution)) $details=get_class($this->resolution);
			elseif (is_array($this->resolution)) $details='Array';
			else
			{
				ob_start();
				echo($this->resolution);
				$export=ob_get_contents();
				ob_end_clean();
				$details=htmlspecialchars(substr($export, 0, 200));
			}
			
			return parent::human_readable().': '.$details;
		}
		
		public function resolution()	{ return $this->resolution; }
	}
}
?>