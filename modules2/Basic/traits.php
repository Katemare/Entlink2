<?

// черта, облегчающая создание объектов класса, когда мы знаем только часть названия класса. Например, классы валидаторов имеют названия Validator_такой-то и Validator_сякой, но модели и методы возвращают только части "такой-то" и "сякой". Эта черта позволяет избежать операции 'Validator_'.$keyword, сохраняя прототипы в массив с ключами $keyword и клонируя их.
trait Prototyper
{
	static
		// $prototype_class_base='replace_me',
		$prototypes=[];
	
	public static function get_prototype($keyword)
	{
		if (!array_key_exists($keyword, static::$prototypes))
		{
			$class=static::compose_prototype_class($keyword);
			$prototype=new $class();
			static::$prototypes[$keyword]=$prototype;
		}
		return static::$prototypes[$keyword];
	}
	
	public static function from_prototype($keyword)
	{
		return clone static::get_prototype($keyword);
	}
	
	public static function compose_prototype_class($keyword)
	{
		return static::$prototype_class_base.$keyword;
	}
}

// то же, что предыдущая черта, но когда название класса совпадает с ключевыми словом.
trait Prototyper_bare
{
	use Prototyper;
	
	public static function compose_prototype_class($keyword)
	{
		return $keyword;
	}
}

// всякий раз, когда нам нужно вернуть данные о прошедшей операции вместо обычного ответа, мы возвращаем объект класса, унаследованного от этого. Если вернулся объект класса Report, то это всегда отчёт об операции и никогда - не посредственный результат выполнения метода. Это избавляет от неоднозначности таких ответов как, например, false (результат - false или же операция не удалась?).
class Report
{
	public
		$source=null;
		
	public function human_readable()
	{
		return get_class($this);
	}
}

class Report_tasks extends Report
{
	public
		$tasks=[],
		$process=null;
	
	public function __construct($tasks)
	{
		$this->tasks=$tasks;
	}
	
	public function create_process()
	{
		if ($this->process===null) $this->process=new Process_collection_absolute($this->tasks);
		return $this->process;	
	}
	
	public function complete()
	{
		return $this->create_process()->complete();
	}
	
	public function register_dependancies_for($task, $identifier=null)
	{
		$task->register_dependancies($this->tasks, $identifier);
	}
	
	public function report()
	{
		if ($this->process===null) return new Report_impossible('no_process');
		return $this->process->report();
	}
	
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

// облегчает возвращение отчёта с одной операцией и не вынуждает прибегать к методам отчёта для его обработки. Движок постоянно обменивается отчётами и обрабатывает их, так что анализ отчёта следует свести до базовых операций принадлежности к классу (instanceof) и извлечения параметров.
class Report_task extends Report_tasks
{
	public $task=null;
	
	public function __construct($task)
	{
		parent::__construct([$task]);
		$this->task=$task;
	}
	
	public function create_process()
	{
		if (is_null($this->process)) $this->process=$this->task->create_process();
		return $this->process;
	}
	
	public function complete()
	{
		return $this->create_process()->complete();
	}
	
	public function register_dependancies_for($task, $identifier=null)
	{
		$task->register_dependancy($this->task, $identifier);
	}
}

class Report_final extends Report
{
}

class Report_impossible extends Report_final
{
	public
		$errors=[];
		
	public function __construct($errors=null)
	{
		if (is_null($errors)) return;
		if (is_array($errors)) $this->errors=$errors;
		else $this->errors=[$errors];
	}
	
	public function human_readable()
	{
		return parent::human_readable().': '.var_export($this->errors, true);
	}

}

class Report_success extends Report_final
{
}

class Report_in_progress extends Report {}

class Report_resolution extends Report_success
{
	public $resolution;
	
	public function __construct($resolution=null)
	{
		$this->resolution=$resolution;
	}
	
	public function human_readable()
	{
		if (is_object($this->resolution)) $details=get_class($this->resolution);
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
}

trait Report_spawner
{
	public function sign_report($report)
	{
		global $debug;
		if (!$debug) return $report;
		if ($report->source!==null) {vdump($this); die ('SIGNED REPORT'); } // в будущем, возможно, будет клонировать и переподписывать отчёт.
		$report->source=$this;
		return $report;
	}
}

// функционал классов, существующих в единственном экземпляре.
trait Singleton
{
	static $instance=null;
	// ВАЖНО! у каждого класса, наследующего Singleton (то есть если один из родителей использовал Singleton), нужно повторить строчку выше, иначе обращение static::$instance будет к ближайшему объявлению родительских классов.
	
	public static function instance()
	{
		if (static::$instance!==null) return static::$instance;
		static::$instance=new static;
		return static::$instance;
	}
	
	public static function deinstance()
	{
		static::$instance=null;
	}
	
	public static function instance_exists()
	{
		return static::$instance!==null;
	}
	
	public static function is_ton() { return true; }
}

// функционал классов, существующих во многих экземплярах, но идентифицируемых по ключу, а не создаваемых заново каждый раз.
// не все подобные классы используют эту черту, потому что не всегда индентификация идёт по ключу.
trait Multiton
{
	static $instances=[];
	// ВАЖНО! см. примечание к Singleton'у, выше.
	
	public static function make_Multiton_key($args)
	{
		if (!is_array($args)) { die('BAD MULTITON ARGS'); }
		elseif (count($args)==0) $key='';
		else $key=array_reduce($args, 'flatten_Multiton_args');
		return $key;
	}
	
	public static function instance()
	{
		$args=func_get_args();
		$key=static::make_Multiton_key($args);
		if ( ($key!==null) && (array_key_exists($key, static::$instances)) )
		{
			$instance=static::$instances[$key];
		}
		else
		{
			$class_name=static::make_Multiton_class_name($args);
			$class=new ReflectionClass($class_name);
			$instance=$class->newInstanceArgs($args);
			if ($key!==null)
			{
				static::$instances[$key]=$instance;
			}
		}
		static::check_instance($instance, $args);
		return $instance;
	}
	
	// выполняется всякий раз при запросе инстанса, не важно, извлечён он из кэша или создан новый.
	public static function check_instance($instance, $args) { }
	
	public static function make_Multiton_class_name($args)
	{
		return get_called_class();
	}
	
	public static function instance_exists()
	{
		$args=func_get_args();
		$key=static::make_Multiton_key(...$args);
		if ($key===null) return false;
		return array_key_exists($key, static::$instances);
	}
	
	public static function is_ton() { return true; }
}

function flatten_Multiton_args($carry, $item)
{
	if ($item===true) $flat='true';
	elseif ($item===false) $flat='false';
	elseif ($item===null) $flat='null';
	elseif (is_array($item)) $flat='['.array_reduce($item, 'flatten_Multiton_args').']';
	elseif (is_object($item))
	{
		if ($item instanceof Multiton_argument) $flat=$item->Multiton_argument();
		elseif ($item instanceof Closure) $flat=spl_object_hash($item); // и остаётся надеяться, что не появится другая анонимная функция с таким же хэшем... увы, функционал расширить не получится.
		else { vdump($item); die('UNIMPLEMENTED YET: Multiton object keys'); }
	}
	else $flat=$item;
	
	if ($carry===null) return $flat;
	return $carry.'|'.$flat;
}

// эти объекты могут быть частью аргументов для Мультитона.
interface Multiton_argument
{
	public function Multiton_argument();
}

trait Noton // реагирует на instance, но не сохраняет копию в кэше.
{
	// действует точно так же, как new Request...(аргументы). не дублирует функционал make_Multiton_class_name (например, возвращающий Request_by_id вместо Request_by_field, когда поле id), но потери пока что невелики.
	public static function instance()
	{
		$class_name=get_called_class();
		$args=func_get_args();
		$instance=new $class_name(...$args);
		return $instance;
	}
	
	public static function is_ton() { return false; }
}

// проверять на актуальные механизмы Мультитона и Синглтона нужно конструкцией (method_exists($имя_класса_или_объект, 'is_ton')) && ($имя_класса_или_объект->is_ton())

// даёт каждому объекту данного класса и его наследников уникальный айди. Полезно для того, чтобы проверять наличие объекта в списках - вместо поиска в массиве достаточно проверить наличие числового ключа.
trait Object_id
{
	static $next_object_id=1; // если у унаследованного класса переобъявить этот параметр, то у него и его наследников будет собственная область айди!
	
	public $object_id;
	
	public function generate_object_id()
	{
		$this->object_id=static::$next_object_id++;
	}
}

// это оболочка для Closure, позволяющая указать добавочные аргументы и запомнить дополнительные параметры вызова. В принципе может работать и с массивами-callback'ами, но Closure быстрее и поэтому предпочтительней. А там, где можно обойтись Closure без Call или вообще прямым вызовом, следует обходиться ими.
class Call
{
	use Object_id;
	
	public
		$callback,
		$standard_args=[],
		// $strip_arguments=0
		$post_args=[];
		
	public function __construct($callback, ...$standard_args)
	{
		$this->callback=$callback;
		$this->standard_args=$standard_args;
		$this->generate_object_id();
	}
	
	public function __invoke(...$args)
	{
		$this->before_invoke();
		//if ($this->strip_arguments>0) array_slice($args, $this->strip_arguments);
		$this->process_args();		
		
		$callback=$this->callback;
		return $callback (...$this->standard_args, ...$args, ...$this->post_args);
	}
	
	public function process_args()
	{
	}
	
	public function before_invoke()
	{
	}
	
	public function bindTo($object) // повторяет одноимённый метод callback, но не полностью, а в рамках, использующихся в данном движке.
	{
		$new_call=clone $this;
		if ($new_call->callback instanceof Closure) $new_call->callback=$this->callback->bindTo($object);
		elseif ( (is_array($new_call->callback)) && (is_object($new_call->callback[0])) ) $new_call->callback[0]=$object;
		else die ('CANT BIND OBJECT');
		return $new_call;
	}
	
	public function __clone()
	{
		$this->generate_object_id();
	}
}

// эта черта навешивается на классы, которые накапливают вызовы и совершают их после некоторого события.
// эта альтернативная система крючкам, гарантирующая, что функции будут вызваны после конкретных процедур конкретного объекта.
// крючки же создаются и обрабатываются с некоторой неопределённостью того, кто ответит и в каком порядке.
trait Caller
{
	public $caller_calls=[];

	public function make_calls($pool='default', ...$args)
	{
		if (!array_key_exists($pool, $this->caller_calls)) return;
		foreach ($this->caller_calls[$pool] as $call)
		{
			$this->make_call($call, $args);
		}
	}

	public function make_final_calls($pool='default', ...$args)
	{
		$this->make_calls($pool, ...$args);
		unset($this->caller_calls[$pool]);
	}
	
	public function make_call($call, $args=[])
	{
		$call(...$args);
	}
	
	public function add_call($call, $pool='default')
	{
		if (empty($call)) return;
		if (! ($call instanceof Call) ) $call=new Call($call);
		if (!array_key_exists($pool, $this->caller_calls)) $this->caller_calls[$pool]=[$call->object_id=>$call];
		else $this->caller_calls[$pool][$call->object_id]=$call;
		return $call->object_id;
	}
	
	public function remove_call($id, $pool)
	{
		if (!array_key_exists($pool, $this->caller_calls)) return;
		if ($id instanceof Call) $id=$id->object_id;
		unset($this->caller_calls[$pool][$id]);
	}
	
	// $calls в качестве ключей должно иметь айди задач!
	public function add_calls($calls, $pool='default')
	{
		if (empty($call)) return;
		if (!array_key_exists($pool, $this->caller_calls)) $this->caller_calls[$pool]=$calls;
		else $this->caller_calls[$pool]+=$calls;
	}
}

// дополнительно передаёт в вызовы ссылку на себя.
trait Caller_backreference
{
	use Caller;
	
	public function make_call($call, $args=[])
	{
		$call($this, ...$args);
	}
}

trait Page_spawner
{
	public static function spawn_page_by_data($page_data, $parts=null, $route=null, $standard_class='Page_view_from_db')
	{
		$class=null;
		$db_key=null;
		if (is_array($page_data))
		{
			if (array_key_exists('db_key', $page_data)) $db_key=$page_data['db_key'];
			if (array_key_exists('page_class', $page_data)) $class=$page_data['page_class'];
			$generic_keys=[0, 1];
			if ($db_key===null)
			{
				foreach ($generic_keys as $index)
				{
					if (!array_key_exists($index, $page_data)) continue;
					if ($page_data[$index]{0}==='#')
					{
						$db_key=substr($page_data[$index], 1);
						$page_data['db_key']=$db_key;
						unset($page_data[$index]);
						break;
					}
				}
			}
			if ($class===null)
			{
				foreach ($generic_keys as $index)
				{
					if ($page_data[$index]{0}!=='#')
					{
						$class=$page_data[$index];
						$page_data['page_class']=$class;
						unset($page_data[$index]);
						break;
					}
				}
			}
		}
		elseif ($page_data{0}==='#')
		{
			$db_key=substr($page_data, 1);
			$page_data=['db_key'=>$db_key];
		}
		else
		{
			$class=$page_data;
			$page_data=[];
		}
		if ($class===null) $class=$standard_class;
		$page_data['page_class']=$class;
		
		if ($db_key!==null) $page=$class::with_db_key($db_key);
		else $page=new $class();
		if ($route!==null) $page->record_route($route);
		$page->apply_model($page_data);
		if ($parts!==null) $page->apply_query($parts);
		return $page;
	}
}

?>