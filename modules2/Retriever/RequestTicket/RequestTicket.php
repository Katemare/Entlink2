<?
namespace Pokeliga\Retriever;

/**
* Этот класс содержит информацию для получения данных из запроса (Request).
* Это выделено в отдельный класс не только для удобства, но и для использования Request_reuser'ом, модифицирующим запросов. Ему нужна возможность создавать как "общественые" запросы (-тоны), так и уникальные, и разбираться, с которым типом он имеет дело.
*/
class RequestTicket implements \Pokeliga\Entlink\Multiton_argument
{
	/**
	* @var int SPAWN_NEW Инструкция создать уникальный запрос или запись, что таковой был создан.
	* @var int SPAWN_INSTANCE Инструкция по возможности создать общественный запрос или запись, что именно такая попытка была предпринята.
	*/
	const
		SPAWN_NEW=0,
		SPAWN_INSTANCE=1;
	
	/**
	* @var string $class Название класса запроса.
	* @var array $constructor_args Агрументы для конструктора запроса.
	* @var array $get_data_args Аргументы для запроса get_data(), то есть ключи.
	* @var null|\Pokelita\Request\Request $request собственно созданный запрос.
	* @var null|int $spawn_method Одна из констант выше. null означает, что запрос ещё не был создан и предпочтения метода нет.
	*/
	protected
		$class,
		$constructor_args,
		$get_data_args,
		$request,
		$spawn_method;
	
	/**
	* @param string $class Название класса запроса.
	* @param array $constructor_args Агрументы для конструктора запроса.
	* @param array $get_data_args Аргументы для запроса get_data(), то есть ключи.
	*/
	public function __construct($class, $constructor_args=[], $get_data_args=[])
	{
		$this->class=$class;
		$this->constructor_args=$constructor_args;
		$this->get_data_args=$get_data_args;
	}
	
	/**
	* Создаёт объект запроса методом, предполагающимся по умолчанию, либо возвращает уже созданный.
	* @return \Pokeliga\Retriever\Request
	*/
	public function get_request()
	{
		if ($this->request!==null) return $this->request;
		elseif ($this->spawn_method===static::SPAWN_NEW) return $this->standalone();
		else return $this->instance();
	}
	
	/**
	* Создаёт общественный запрос или возвращает уже существующий.
	* @return \Pokeliga\Retriever\Request
	* @throws \Exception если запрос был создан другим образом.
	*/
	public function instance()
	{
		if ($this->request!==null)
		{
			if ($this->spawn_method===static::SPAWN_INSTANCE) return $this->request;
			throw new \Exception('Request double');
		}
		
		$class=$this->class;
		$instance=$class::instance(...$this->constructor_args);
		// если запрос относится к Noton'ам, то будет создана новая копия в любом случае. Это также касается некоторых классов-мультитонов, например, некоторых Request_reuser'ов.
		if ($instance instanceof \Report) return $instance;
		
		$this->request=$instance;
		$this->spawn_method=static::SPAWN_INSTANCE;
		return $instance;
	}
	
	/**
	* Создаёт уникальный запрос или возвращает уже существующий.
	* @return \Pokeliga\Retriever\Request
	* @throws \Exception если запрос был создан другим образом.
	*/
	public function standalone()
	{
		if ($this->request!==null)
		{
			if ($this->spawn_method===static::SPAWN_NEW) return $this->request;
			throw new \Exception('Request double');
		}
		
		$class_name=$this->class;
		$instance=new $class_name(...$this->constructor_args);
		
		$this->request=$instance;
		$this->spawn_method=static::SPAWN_NEW;
		return $instance;
	}
	
	/**
	* Довольно грубый метод, насильно устанавливающий запрос.
	* Без проверок, но сейчас используется только в одном месте.
	* @deprecated FIXME! Используется в одном месте, нужно использовать что-то другое.
	*/
	public function set_request($request)
	{
		$spawn_method=null;
		if ($request instanceof RequestTicket)
		{
			$spawn_method=$request->spawn_method;
			$request=$request->request;
		}
		if ($request===null) die('SETTING BY EMPTY REQUEST');
		
		if ($this->request===$request) return;
		if ($this->request!==null) die('SETTING EXISTING REQUEST');
		
		$this->spawn_method=$spawn_method;
		$this->request=$request;
	}
	
	public function make_Multiton_key()
	{
		if ($this->request===null) $ask=$this->class;
		else $ask=$this->request;
		if (!$ask::is_ton()) return;
		return $ask::make_Multiton_key($this->constructor_args);
	}
	
	/**
	* Проверяет, является ли поставляемый RequestTicket относящимся к эквивалентном запросу.
	* @param \Pokeliga\Retriever\RequestTicket $ticket
	* @return bool
	*/
	public function coinstance($ticket)
	{
		return ($this->class===$ticket->class) && (($multiton_key=$this->make_Multiton_key())!==null) && ($multiton_key===$ticket->make_Multiton_key());
	}
	
	/**
	* Соответствующие обращения к запросу.
	*/
	public function get_data()				{ return $this->get_request()->get_data(...$this->get_data_args); }
	public function get_data_or_dep()		{ return $this->get_request()->get_data_or_dep(...$this->get_data_args); }
	public function get_data_or_promise()	{ return $this->get_request()->get_data_or_promise(...$this->get_data_args); }
	public function get_data_or_fail()		{ return $this->get_request()->get_data_or_fail(...$this->get_data_args); }
	public function set_data() 				{ return $this->get_request()->set_data(...$this->get_data_args); }
	public function compose_data() 			{ return $this->get_request()->compose_data(...$this->get_data_args); }
	public function by_unique_field()		{ return $this->get_request()->by_unique_field(); }
	
	/**
	* Возвращает Promise, результатом выполнения которого являются искомые данные.
	* @return \Pokeliga\Entlink\Promise
	*/
	public function to_promise() { return Task_request_get_data::with_ticket($this); }
	
	/**
	* Возвращает \Report_promise с обещанием, результатом выполнения которого являются искомые данные.
	* Разница с предыдущим методом в том, что этот ответ является медиатором и \Pokeliga\Entlink\FinalPromise.
	* @see \Pokeliga\Entlink\FinalPromise
	* @return \Report_promise
	*/
	public function report_promise() { return new \Report_promise($this->to_promise(), $this); }
	
	/**
	* Создаёт объект-Query или соответствующего формата массив из уникального запроса.
	* @return array|\Pokeliga\Retriever\Query
	*/
	public function create_query()
	{
		$source=null;
		if ($this->spawn_method===static::SPAWN_NEW) $source=$this;
		else
		{
			$source=clone $this;
			$source->standalone();
		}
		$source->set_data();
		$query=$source->get_request()->create_query();
		return $query;
	}
	
	/**
	* Создаёт строку, являющуюся запросом к БД, из уникального запроса.
	* @return string
	*/
	public function compose_query()
	{
		$query=$this->create_query();
		$query=Query::from_array($query);
		return $query->compose();
	}
	
	public function __clone()
	{
		$this->request=null;
		$this->clone_subtickets();
	}
	
	/**
	* Клонирование внутренних RequestTicket'ов при клонировании данного.
	*/
	protected function clone_subtickets()
	{
		$this->clone_subtickets_in_array($this->constructor_args);
		$this->clone_subtickets_in_array($this->get_data_args);
	}
	
	/**
	* Служебная функция для клонирования внутренних RequestTicket'ов при клонировании данного.
	*/
	protected function clone_subtickets_in_array(&$arr)
	{
		foreach ($arr as &$value)
		{
			if (is_array($value)) $this->clone_subtickets_in_array($value);
			elseif ($value instanceof RequestTicket) $value=clone $value;
			elseif ($value instanceof Query) $value=clone $value;
		}
	}

	public function Multiton_argument()
	{
		return get_class($this).':'.array_reduce([$this->class, $this->constructor_args, $this->get_data_args], '\Pokeliga\Entlink\flatten_Multiton_args');
	}
}

/**
* Закладывает данные как обычно, а запрашивает у специального метода.
*/

abstract class RequestTicket_special extends RequestTicket
{
	/**
	* Служебный метод, помогающий обращаться к запросу, но возвращать особые данные.
	* @param string $method Название разновидности метода get_data().
	* @return mixed|\Report В соответствии с методом, к которому идёт обращение.
	*/
	private function special_get_data($method)
	{
		$result=parent::$method();
		if (!($result instanceof \Report)) return $this->compose_data();
	}
	
	public function get_data()				{ return $this->special_get_data('get_data'); }
	public function get_data_or_dep()		{ return $this->special_get_data('get_data_or_dep'); }
	public function get_data_or_promise()
	{
		$result=$this->special_get_data('get_data_or_promise');
		if ($result instanceof \Report_promise) return $this->report_promise(); // перехват обещания.
		return $result;
	}
	public function get_data_or_fail()		{ return $this->special_get_data('get_data_or_fail'); }
	
	/**
	* @throws \Exception если особый метод получения данных не задан.
	*/
	public function compose_data()
	{
		throw new \Exception('no special compose_data()');
	}
}

?>