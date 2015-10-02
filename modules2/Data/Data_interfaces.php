<?
namespace Pokeliga\Data;

interface ValueLink // для объектов, связанных с одним значением.
{
	public function get_value();
}

interface ValueModel // для объектов, поставляющих модель значения (не путать с моделью сущности, формы...)
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
	
	// возвращает значение; либо \Report_promise; либо \Report_impossible; либо, если значения в модели нет и установлено $srict, исключение (точнее, сейчас просто останов).
	public function value_model($code=null, $strict=true)
	{
		$source=&$this->value_model_array();
		if ($code===null) return $source;
		if (!$this->in_value_model($code))
		{
			if ($strict) { vdump($this); vdump($this->value_model()); die ('BAD MODEL CODE: '.$code); }
			return new \Report_impossible('bad_model_code', $this);
		}
		
		$content=&$source[$code];
		if (Compacter::recognize_mark($content)) $content=Compacter::by_mark_and_extract($this, $content);
		// важно, что если компактер раскрывается в задачу, то в модели остаётся именно задача. при изменении зависимостей эту задачу следует сбросить, в противном случае мы можем работать с устаревшими данными. FIXME: сейчас этого не делается.
		if ($content instanceof \Pokeliga\Task\Task) return $content->resolution_or_promise();
		return $content;
	}
	
	// как предыдущее, но поскольку $strict не стоит, то в случае отсутствия значения возвращает \Report_impossible. Кроме того, ответ в виде задачи также превращается в \Report_impossible.
	public function value_model_soft($code)
	{
		$result=$this->value_model($code, false);
		if ($result instanceof \Report_delay) return new \Report_impossible('model_not_ready', $this);
		return $result;
	}
	
	// возвращает значение или же останавливается (должно быть исключение). для применения в конструкциях, которые не рассчитаны на обработку отчётов и предполагают от модели поведения массива.
	public function value_model_now($code)
	{
		$result=$this->value_model($code, true);
		if ($result instanceof \Report_promise) $result=$result->now();
		if ($result instanceof \Report_impossible) { vdump($code); vdump($this); die('VALUE MODEL UNAVAILABLE'); }
		return $result;
	}
	
	public function in_value_model($code)
	{
		return array_key_exists($code, $this->value_model_array());
	}
}

// для объектов, содержащих значение подобно Value (к примеру, это также сложные поля формы).
interface ValueContent extends ValueModel
{
	public function set_state($state);
	
	public function has_state($state);
	
	public function state();
	
	public function content();
	
	public function set($content, $source=Value::BY_OPERATION);
	
	public function is_valid();
}

// для объектов, поставляющих доступ ко множеству значений.
interface ValueHost
{	
	public function request($code); // возвращает \Report_resolution со значением; \Report_impossible при невозможности; \Report_promise с задачей, результатом которой станет значение.
	
	public function value($code); // возвращает значение либо \Report_impossible при невозможности.
}

// для объектов, одновременно предоставляющих доступ ко множеству значенй и имеющих собственное значение; или предоставяющих значения по умолчанию.
interface ValueHost_combo extends ValueHost
{
	// аргумент может быть равен null для двух целей: 1) запрос значения по умолчанию; 2) некоторые объекты одновременно сами являются значениями (принимают value() и request() без аргументом) и являются владельцами значений. это позволяет применить к ним данный интерфейс сразу, без промежуточного наследования.
	
	public function request($code=null);
	
	public function value($code=null);
}

// при использовании этой черты нужно определить только ValueHost_request для всех необходимых кодов.
trait ValueHost_standard
{
	public function ValueHost_request($code)
	{
		return new \Report_impossible('unknown_subvalue_code: '.$code, $this);
	}
	
	public function ValueHost_value($code)
	{
		$result=$this->ValueHost_request($code);
		if ($result instanceof \Report_promise) return $result->now();
		else return $result->resolution();
	}
}

// данное Value обеспечивает значения для опций выпадающего списка.
interface Value_provides_options
{
	public function options($line=[]);
}

// данное Value обеспечивает не только значения для опций выпадающего списка, но и их заголовки.
interface Value_provides_titled_options extends Value_provides_options
{
	public function titled_options($line=[]);
}

// данное Value позволяет делать поисковые запросы по API.
interface Value_searchable_options
{
	public function API_search_arguments();
	// возвращает аргументы, которые нужно передать к API, чтобы воспроизвести значение и определить круг поиска.

	public function option_by_value($value, $line=[]);
	// возвращает шаблон, отвечающий за опцию выпадающего списка (включая значение и заголовок).
	
	public function found_options_template($search=null);
	// поисковая строка, которая вместе с моделью должна дать результаты поиска.
}

interface ValueType_handles_fill
{
	public function detect_mode();
	public function fill();
}
?>