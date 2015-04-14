<?

interface ValueLink // для объектов, связанных с одним значением.
{
	public function get_value();
}

interface ValueModel
{
	public function value_model($code=null, $strict=true);
	
	public function value_model_soft($code);
	
	public function value_model_now($code);
	
	public function in_value_model($code);
}

trait ValueModel_from_link // для использования на объектах с интерфейсом ValueLink для обеспечения интерфейса ValueModel
{
	public function value_model($code=null, $strict=true)
	{
		return $this->get_value()->value_model($code, $strict);
	}
	
	public function value_model_soft($code)
	{
		return $this->get_value()->value_model_soft($code);
	}
	
	public function value_model_now($code)
	{
		return $this->get_value()->value_model_now($code);
	}
	
	public function in_value_model($code)
	{
		return $this->get_value()->in_value_model($code);
	}
}

trait ValueModel_owner // для использования в объектах, у которых собственная версия модели.
{
	// public $model=[];

	public function &value_model_array()
	{
		return $this->model;
	}
	
	// возвращает значение; либо Report_task с задачей, в результате которой появляется значение; либо Report_impossible; либо, если значения в модели нет и установлено $srict, исключение (точнее, сейчас просто останов).
	public function value_model($code=null, $strict=true)
	{
		$source=&$this->value_model_array();
		if ($code===null) return $source;
		if (!$this->in_value_model($code))
		{
			if ($strict) { vdump($this); vdump($this->value_model()); die ('BAD MODEL CODE: '.$code); }
			return $this->sign_report(new Report_impossible('bad_model_code'));
		}
		
		$content=&$source[$code];
		if (Compacter::recognize_mark($content)) $content=Compacter::by_mark_and_extract($this, $content);
		// важно, что если компактер раскрывается в задачу, то в модели остаётся именно задача. при изменении зависимостей эту задачу следует сбросить, в противном случае мы можем работать с устаревшими данными. FIX: сейчас этого не делается.
		if ($content instanceof Task)
		{
			if ($content->failed()) return $content->report();
			if ($content->successful()) return $content->resolution;
			return $this->sign_report(new Report_task($content));
		}
		return $content;
	}
	
	// как предыдущее, но поскольку $strict не стоит, то в случае отсутствия значения возвращает Report_impossible. Кроме того, ответ в виде задачи также превращается в Report_impossible.
	public function value_model_soft($code)
	{
		$result=$this->value_model($code, false);
		if ($result instanceof Report_tasks) return $this->sign_report(new Report_impossible('model_not_ready'));
		return $result;
	}
	
	// возвращает значение или же останавливается (должно быть исключение). для применения в конструкциях, которые не рассчитаны на обработку отчётов и предполагают от модели поведения массива.
	public function value_model_now($code)
	{
		$result=$this->value_model($code, true);
		if ($result instanceof Report_task)
		{
			$result->complete(); // здесь может быть неоптимальное выполнение задачи, поскольку оно делается без очереди запросов. не знаю, отмечать ли это где-то? предполагаю, что прибегать к компактеру в моделях следует только в валидаторах, рассчитанных обрабатывать отчёты.
			if ($result->task->failed()) { vdump($code); vdump($this); die('VALUE MODEL UNAVAILABLE'); }
			return $result->task->resolution;
		}
		elseif ($result instanceof Report_impossible) { vdump($code); vdump($this); die('VALUE MODEL UNAVAILABLE'); }
		return $result;
	}
	
	public function in_value_model($code)
	{
		return array_key_exists($code, $this->value_model_array());
	}
}

interface ValueContent extends ValueModel
{
	public function set_state($state);
	
	public function has_state($state);
	
	public function state();
	
	public function content();
	
	public function set($content, $source=Value::BY_OPERATION);
	
	public function is_valid();
}

interface ValueHost
{
	public function request($code); // возвращает Report_resolution со значением; Report_impossible при невозможности; Report_task с задачей, результатом которой станет значение.
	
	public function value($code); // возвращает значение либо Report_impossible при невозможности.
}

trait ValueHost_standard
{
	public function ValueHost_request($code)
	{
		return $this->sign_report(new Report_impossible('unknown_subvalue_code: '.$code));
	}
	
	public function ValueHost_value($code)
	{
		$result=$this->ValueHost_request($code);
		if ($result instanceof Request_task)
		{
			$task=$result->task;
			$task->complete();
			if ($task->failed()) return $task->report();
			return $task->resolution;
		}
		elseif ($result instanceof Report_resolution) return $result->resolution;
		elseif ($result instanceof Report_impossible) return $result;
		else die ('BAD REQUEST REPORT');
	}
}

interface Value_provides_options
{
	public function options($line=[]);
}

interface Value_provides_titled_options extends Value_provides_options
{
	public function titled_options($line=[]);
}

// в случае элемента формы select показывает специальный select с поиском, а результаты получает по API
interface Value_searchable_options
{
	public function API_search_arguments();
	// возвращает аргументы, которые нужно передать к API, чтобы воспроизвести значение и определить круг поиска.

	public function option_by_value($value, $line=[]);
	
	public function found_options_template($search=null);
	// поисковая строка, которая вместе с моделью должна дать результаты поиска.
}

trait Value_searchable_entity
{
	public function option_by_value($value, $line=[])
	{
		$id_group=$this->value_model_now('id_group');
		$entity=$this->pool()->entity_from_db_id($value, $id_group);
		if (empty($entity)) return $this->sign_report(new Report_impossible('no_entity'));
		
		$template=Template_entity_option::for_entity($entity);
		return $template;
	}
}
?>