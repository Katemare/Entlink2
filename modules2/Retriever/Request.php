<?
namespace Pokeliga\Retriever;

// ВАЖНО! запросы, работающие с Singleton и Multiton, добавляют фабрику instance(), а одноразовые запросы используют собственные фабрики.

class RequestTicket implements \Pokeliga\Entlink\Multiton_argument
{
	const
		SPAWN_NEW=0,
		SPAWN_INSTANCE=1;
	
	public
		$class,
		$constructor_args,
		$get_data_args,
		$request,
		$spawn_method;
	
	public function __construct($class, $constructor_args=[], $get_data_args=[])
	{
		$this->class=$class;
		$this->constructor_args=$constructor_args;
		$this->get_data_args=$get_data_args;
	}
	
	// создаёт объект запроса методом, предполагающимся по умолчанию. поскольку все запросы используют по крайней мере Noton, то метод instance() и есть этот метод, потому что Noton'ов просто создаёт новый объект.
	public function get_request()
	{
		if ($this->request!==null) return $this->request;
		return $this->instance();
	}
	
	public function instance()
	{
		if ($this->request!==null)
		{
			if ($this->spawn_method===static::SPAWN_INSTANCE) return $this->request;
			vdump($this); die('REQUEST DOUBLE 1');
		}
		
		$class=$this->class;
		$instance=$class::instance(...$this->constructor_args);
		// если запрос относится к Noton'ам, то будет создана новая копия в любом случае. Это также касается некоторых классов-мультитонов, например, некоторых Request_reuser'ов.
		if ($instance instanceof \Report) return $instance;
		
		$this->request=$instance;
		$this->spawn_method=static::SPAWN_INSTANCE;
		return $instance;
	}
	
	public function standalone()
	{
		if ($this->request!==null)
		{
			if ($this->spawn_method===static::SPAWN_NEW) return $this->request;
			vdump($this); xdebug_print_function_stack(); die('REQUEST DOUBLE 2');
		}
		
		$class_name=$this->class;
		$class=new ReflectionClass($class_name);
		$instance=$class->newInstanceArgs($this->constructor_args);
		
		$this->request=$instance;
		$this->spawn_method=static::SPAWN_NEW;
		return $instance;
	}
	
	// довольно грубый метод без проверок, но сейчас используется только в одном месте.
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
		if (!$ask::is_ton()) return false; // запросы и так все наследуют черту Noton, так что метод есть.
		return $ask::make_Multiton_key($this->constructor_args);
	}
	
	public function coinstance($ticket)
	{
		return ($this->class===$ticket->class) && (($multiton_key=$this->make_Multiton_key())!==null) && ($multiton_key===$ticket->make_Multiton_key());
	}
	
	public function get_data($mode=Request::GET_DATA_NOW)
	{
		$args=$this->get_data_args;
		$args[]=$mode;
		return $this->get_request()->get_data(...$args);
	}
	
	public function get_data_set()
	{
		return $this->get_data(Request::GET_DATA_SET);
	}
	
	public function get_data_soft()
	{
		return $this->get_data(Request::GET_DATA_SOFT);
	}
	
	public function set_data()
	{
		return $this->get_request()->set_data(...$this->get_data_args);
	}
	
	public function compose_data()
	{
		return $this->get_request()->compose_data(...$this->get_data_args);
	}
	
	public function make_query()
	{
		$source=null;
		if ($this->spawn_method===static::SPAWN_NEW) $source=$this;
		else
		{
			$source=clone $this;
			$source->standalone();
		}
		$source->set_data();
		$query=$source->get_request()->make_query();
		return $query;
	}
	
	public function by_unique_field()
	{
		return $this->get_request()->by_unique_field();
	}
	
	public function __clone()
	{
		$this->request=null;
		$this->clone_subtickets();
	}
	
	public function clone_subtickets()
	{
		$this->clone_subtickets_in_array($this->constructor_args);
		$this->clone_subtickets_in_array($this->get_data_args);
	}
	
	public function clone_subtickets_in_array(&$arr)
	{
		foreach ($arr as &$value)
		{
			if (is_array($value)) $this->clone_subtickets_in_array($value);
			elseif ($value instanceof RequestTicket) $value=clone $value;
			elseif ($value instanceof Query) $value=clone $value;
		}
	}
	
	public function combine($ticket)
	{
		if ($this->request!==null) die('NEW SUBTICKETS POSTCREATION');
		if (is_array($ticket)) $subtickets=array_merge([$this], $ticket);
		else $subtickets=[$this, $ticket];
		return new Request_union($subticket);
	}
	
	public function Multiton_argument()
	{
		return get_class($this).':'.array_reduce([$this->class, $this->constructor_args, $this->get_data_args], 'flatten_Multiton_args');
	}
	
	public function compose_query()
	{
		$query=$this->make_query();
		$query=Query::from_array($query);
		return $query->compose();
	}
}

// закладывает данные как обычно, а запрашивает у специального метода.
abstract class RequestTicket_special extends RequestTicket
{
	public function get_data($mode=Request::GET_DATA_NOW)
	{
		if ($mode!==Request::GET_DATA_SOFT)
		{
			$uncompleted_keys=$this->set_data();
			if ($uncompleted_keys)
			{
				if ($mode===Request::GET_DATA_SET) return $this->sign_report(new \Report_task($this->request));
				$this->request->complete();
			}
		}
		$result=$this->compose_data();
		return $result;
	}
	
	public function standard_compose_data()
	{
		return parent::compose_data();
	}
	
	public function compose_data()
	{
		die('INHERIT ME!');
	}
}

abstract class Request extends \Pokeliga\Task\Task
{
	use \Pokeliga\Entlink\Noton;

	const
		GET_DATA_NOW=1,	 // если нужные данные отсутствуют в результатах, сразу получить их и вернуть.
		GET_DATA_SET=2,  // если нужные данные отсутствуют в результатах, отдать \Report_impossible.
		GET_DATA_SOFT=3; // если нужные данные отсутствуют в результатах, то настроить запрос на их получение и вернуть \Report_task.
	
	static
		$get_data_analysis=[]; // для использования чертой Request_get_data_has_args
		
	public abstract function make_query();
	
	public function progress()
	{
		$result=$this->is_ready();
		if ($result instanceof \Report_impossible) return $this->impossible($result->errors);
		else if ($result!==true) return $this->impossible('unknown_error');
		
		$query=$this->make_query();
		if ($query===null)
		{
			$this->impossible('cant_make_query');
			return;
		}
		$result=Retriever()->run_query($query);
		$success=$this->process_result($result);
		$this->data_processed();
		if ($success) $this->finish();
		else $this->impossible('request_failed');
	}
	
	public function process_result($result)
	{
		return !($result instanceof \Report_impossible);
	}
	
	// главная цель этого метода - записать, что текущие ключи были выполнены (и, возможно, зафиксировать, что они не были найдены). чтобы в следующий раз set_data() точно знал, повторять ли запрос.
	public function data_processed() { }
	
	public function is_ready()
	{
		return true;
	}
	
	public static function accepts_keys() { die('USE ACCEPT KEYS TRAIT!'); }
	public function accepts_keys_dyn() { return static::accepts_keys(); }
	public function by_unique_field() { } // по умолчанию null - "не знаю", но в большинстве проверок заменяет "нет".
	
	// следующие методы - то, как следует обращаться к запросам. не нужно вручную запускать add_keys(), например. лучше всего - вызвать get_data_set с требуемыми параметрами, которое вернёт либо невозможность (в случае плохих ключей или же если по этим ключам ничего не найдено), либо задачу (добавив ключи в очередь!), либо готовый результат. при этом сам запрос может быть общим и иметь куда больше ключей, но данные методы ограничивают ответ именно запрошенными ключами.
	
	// требуется подключение одной из нескольких черт, представленных ниже, в зависимости от числа аргументов. можно было сделать автоматическую работу с аргументами с func_get_args() и прочим, но это чревато лишними операциями с массивами и call_user_func_array(), которые довольно медленные. FIX! это больше не проблема с распаковкой и запаковкой аргументов.
	
	// добавляет ключи. возвращает true если добавлены новые ключи, false если нет.
	public function set_data() { die('INHERIT GET DATA TRAIT'); }
	
	// запрашивает данные с указанным режимом  (MODE_NOW, MODE_SET или MODE_SOFT). NOW сразу выполняет запрос и возвращает результат либо невозможность. SET при необходимости добавляет ключи и возвращает \Report_tasks. SOFT либо возвращает результат, либо невозможность, не добавляя ключей. режим должен быть последним аргументом, по умолчанию NOW.
	public function get_data()      { die('INHERIT GET DATA TRAIT'); }
	public function get_data_set()  { die('INHERIT GET DATA TRAIT'); }	// обращение к get_data с указанным режимом SET.
	public function get_data_soft() { die('INHERIT GET DATA TRAIT'); } 	// обращение к get_data с указанным режимом SOFT. по сути обращение к следующему методу.
	
	public function compose_data() { die('INHERIT GET DATA TRAIT'); }	// возвращает готовые данные, касающиеся приведённых ключей, либо же невозможность. не добавляет ключи и не запускает запрос.
}

trait Request_get_data_no_args
{
	public static function accepts_keys() { return false; }

	public function set_data() { return empty($this->processed); }
	
	public function get_data($mode=Request::GET_DATA_NOW)
	{
		$uncompleted=$this->set_data();
		if ($uncompleted instanceof \Report_impossible) return $uncompleted;
		if ($uncompleted)
		{
			if ($mode===Request::GET_DATA_SET) return $this->sign_report(new \Report_task($this));
			if ($mode===Request::GET_DATA_SOFT) return $this->sign_report(new Request_impossible('uncompleted'));
			$this->complete();
		}
		if ($this->failed()) return $this->sign_report(new \Report_impossible('bad_request'));
		
		return $this->compose_data();
	}

	public function get_data_soft()
	{
		return $this->get_data(Request::GET_DATA_SOFT);
	}
	
	public function get_data_set()
	{
		return $this->get_data(Request::GET_DATA_SET);
	}
	
	public function process_result($result)
	{
		if ($result instanceof \Report_impossible) return false;
		$this->result=$result;
		return true;
	}
	
	public function data_processed()
	{
		$this->processed=true;
	}
	
	public function compose_data()
	{
		return $this->result;
	}
}

trait Request_get_data_one_arg
{
	public static function accepts_keys() { return 1; }
	
	public function get_data($arg=null, $mode=Request::GET_DATA_NOW)
	{
		$uncompleted_keys=$this->set_data($arg);
		if ($uncompleted_keys instanceof \Report_impossible) return $uncompleted_keys;
		if ($uncompleted_keys)
		{
			if ($this->completed()) $this->reset();
			if ($mode===Request::GET_DATA_SET) return $this->sign_report(new \Report_task($this));
			if ($mode===Request::GET_DATA_SOFT) return $this->sign_report(new \Report_impossible('uncompleted'));
			$this->complete();
		}
		if ($this->failed()) return $this->sign_report(new \Report_impossible('bad_request'));
		
		return $this->compose_data($arg);
	}

	public function get_data_soft($arg=null)
	{
		return $this->get_data($arg, Request::GET_DATA_SOFT);
	}
	
	public function get_data_set($arg=null)
	{
		return $this->get_data($arg, Request::GET_DATA_SET);
	}
	
	// public abstract function set_data($arg=null);
	// public abstract function compose_data($arg=null);
}

trait Request_get_data_two_args
{
	public static function accepts_keys() { return 2; }
	
	public function get_data($arg1=null, $arg2=null, $mode=Request::GET_DATA_NOW)
	{
		$uncompleted_keys=$this->set_data($arg1, $arg2);
		if ($uncompleted_keys instanceof \Report_impossible) return $uncompleted_keys;
		if ($uncompleted_keys)
		{
			if ($this->completed()) $this->reset();
			if ($mode===Request::GET_DATA_SET) return $this->sign_report(new \Report_task($this));
			if ($mode===Request::GET_DATA_SOFT) return $this->sign_report(new Request_impossible('uncompleted'));
			$this->complete();
		}
		if ($this->failed()) return $this->sign_report(new \Report_impossible('bad_request'));
		
		return $this->compose_data($arg1, $arg2);
	}

	public function get_data_soft($arg1=null, $arg2=null)
	{
		return $this->get_data($arg1, $arg2, Request::GET_DATA_SOFT);
	}
	
	public function get_data_set($arg1=null, $arg2=null)
	{
		return $this->get_data($arg1, $arg2, Request::GET_DATA_SET);
	}
	
	// public abstract function set_data($arg1=null, $arg2=null);
	// public abstract function compose_data($arg1=null, $arg2=null);
}

// для классов, чьё количество принимаемых аргументов зависит от состояния (в частности, подзапроса в Request_proxy).
trait Request_get_data_variable_args
{
	public static function accepts_keys() { return true; }

	public function get_data()
	{
		$keys=$this->accepts_keys_dyn();
		$args=func_get_args();
		$set_args=array_slice($args, 0, $keys);
		if (array_key_exists($keys, $args)) $mode=$args[$keys]; else $mode=static::GET_DATA_NOW;
		
		$uncompleted_keys=$this->set_data(...$set_args);
		if ($uncompleted_keys instanceof \Report_impossible) return $uncompleted_keys;
		if ($uncompleted_keys)
		{
			if ($this->completed()) $this->reset();
			if ($mode===Request::GET_DATA_SET) return $this->sign_report(new \Report_task($this));
			if ($mode===Request::GET_DATA_SOFT) return $this->sign_report(new Request_impossible('uncompleted'));
			$this->complete();
		}
		if ($this->failed()) return $this->sign_report(new \Report_impossible('bad_request'));
		
		return $this->compose_data(...$set_args );
	}

	public function get_data_soft()
	{
		$keys=$this->accepts_keys_dyn();
		$args=func_get_args();
		$args[$keys]=Request::GET_DATA_SOFT;
		return $this->get_data(...$args);
	}
	
	public function get_data_set()
	{
		$keys=$this->accepts_keys_dyn();
		$args=func_get_args();
		$args[$keys]=Request::GET_DATA_SET;
		return $this->get_data(...$args);
	}
	
	public function accepts_keys_dyn() { vdump($this); die ('UNKNOWN KEYS COUNT'); }
	// public abstract function set_data();
	// public abstract function compose_data();
}

class Request_single extends Request
{
	use Request_get_data_no_args;
	
	public
		$query,
		$result=null;

	public static function instance($query=null)
	{
		if (empty($query)) die ('EMPTY QUERY');
		if ($query['action']==='update') $request=new Request_update($query);
		elseif ($query['action']==='delete') $request=new Request_delete($query);
		elseif (in_array($query['action'], ['insert','replace'])) $request=new Request_insert($query);
		else $request=new static($query);
		return $request;
	}
	
	public function __construct($query)
	{
		$this->query=$query;
		parent::__construct();
	}
	
	// COMP
	public static function from_query($query)
	{
		return static::instance($query);
	}
	
	public function make_query()
	{
		return $this->query;
	}
}

class Request_insert extends Request_single
{
	public
		$insert_id=null;
	
	public function process_result($result)
	{
		if (is_numeric($result)) // FIX: не учитывает возможный результат false в случае insert_ignore, не затронувшего ни одной записи.
		{
			$this->insert_id=$result;
		}
		return parent::process_result($result);
	}
}

// WIP: пусть ещё сохраняет affected_rows и т.д.
class Request_update extends Request_single
{
}

class Request_delete extends Request_single
{
}

// запрашивает все данные из таблицы.
class Request_all extends Request
{
	use \Pokeliga\Entlink\Multiton, Request_get_data_no_args
	{
		Request_get_data_no_args::compose_data as std_compose_data;
	}
	
	public
		$table=null;
	
	public function make_query()
	{
		$query=array(
			'action'=>'select',
			'table'=>$this->table
		);
		return $query;
	}
	
	// нули нужны для совместимости со стандартным конструктором, а так эти аргументы обязательны.
	public function __construct($table=null)
	{
		$this->table=$table;
		parent::__construct();
	}
	
	public function compose_data()
	{
		return Retriever()->data_by_table($this->table); // вернёт \Report_impossible, если таблица не была найдена.
	}
}

// смысл этого интерфейса состоит в сообщении информации модификатору запроса (Request_reuser), который хочет получить значения групповых функций. Логика следующая: если запрос не имеет интерфейса Request_groupable, то даже если исходный запрос действует как мультитон, то групповой запрос выполняется индивидуально. Если же интерфейс есть и отличает положительно на is_groupable, то групповые запросы могут быть скомпанованы в один вида SELECT функции, ключ `group_key` FROM... GROUP BY группировка. Каждая отдельная группа (это обязательно!) отвечает за набор, который бы исходный запрос выдал по отдельному ключу для get_data.
interface Request_groupable
{
	public function group_fields(); // то, что в Query нужно вставить в элемент 'group'
	
	public function group_key(); // то, что нужно ставить в поля помимо групповых функций, чтобы идентифицировать группы в формате поля для 'fields' в SELECT.
	
	public function group_key_value_from_get_data_args($get_data_args); // какому значению группового ключа соответствуют аргументы get_data.
	
	public static function is_groupable($get_data_args);
}

class Request_by_field extends Request implements Request_groupable
{
	use Request_get_data_one_arg, \Pokeliga\Entlink\Multiton
	{
		\Pokeliga\Entlink\Multiton::make_Multiton_class_name as std_make_Multiton_class_name;
		\Pokeliga\Entlink\Multiton::make_Multiton_key as std_make_Multiton_key;
	}
	
	public 
		$table=null, // STUB - должно подгружаться из настроек.
		$field=null,
		$conditions=null,
		$data=[],
		$requested=[];
	
	// для краткости в случае, если данные по запрошенным ключам уже получены.
	public function already_done_report()
	{
		return $this->sign_report(new \Report_success());
	}
	
	public function add_keys($keys)
	{
		if (!is_array($keys)) $keys=[$keys];
		$this->prepare_keys($keys);
		$keys=$this->filter_done($keys);
		if (empty($keys)) return $this->already_done_report();
		
		$keys=$this->filter_requested($keys);
		if (!empty($keys))
		{
			$keys=array_unique($keys);
			$this->requested=array_merge($this->requested, $keys);
			$this->reset();
		}
	}
	
	public function prepare_keys(&$keys) { }
	public function prepare_key(&$key) { }
	
	public function filter_done($ids)
	{
		$this->prepare_keys($ids);

		return array_diff($ids, array_keys($this->data));
	}
	
	public function is_done($id)
	{
		if (is_object($id)) $this->prepare_key($id);
		return array_key_exists($id, $this->data);
	}
	
	public function filter_requested($ids)
	{
		$this->prepare_keys($ids);
		return array_diff($ids, $this->requested);
	}
	
	public function make_query()
	{
		if (empty($this->requested)) return null;
		$query=
		[
			'action'=>'select',
			'table'=>$this->table,
			'where'=>[]
		];
		if (is_array($this->field)) $query['where'][]=['field'=>$this->field, 'value'=>$this->requested];
		else $query['where'][$this->field]=$this->requested;
		if ($this->conditions!==null) $query['where']=array_merge($query['where'], $this->conditions);
		return $query;
	}
	
	// нули нужны для совместимости со стандартным конструктором, а так эти аргументы обязательны.
	public function __construct($table=null, $field=null, $additional_conditions=null)
	{
		$this->table=$table;
		$this->field=$field;
		$this->conditions=$additional_conditions;
		parent::__construct();
	}
	
	public static function make_Multiton_class_name($args)
	{
		if (get_called_class()==='Request_by_field')
		{
			if ( ($args[1]==='id') && (empty($args[2])) && (!Retriever()->is_common_table($args[0])) ) return 'Request_by_id';
			if (is_array($args[1])) return 'Request_by_field_spectrum';
		}
		return static::std_make_Multiton_class_name($args);
	}
	
	public static function make_Multiton_key($args)
	{
		if (empty($args[2])) return static::std_make_Multiton_key( [$args[0], ((array_key_exists(1, $args))?($args[1]):(null)) ] );
		return static::std_make_Multiton_key($args);
	}
	
	public function process_result($result)
	{
		if ($result instanceof \Report_impossible)
		{
			$this->data=false;
			return false;
		}
		
		$this->record_result($result);
		
		return true;
	}
	
	public function data_processed()
	{
		if ($this->data===false) return;
		$not_found=array_diff($this->requested, array_keys($this->data));
		foreach ($not_found as $val)
		{
			$this->data[$val]=false;
		}
		$this->requested=[];
	}
	
	public function record_result($result)
	{
		foreach ($result as $row)
		{
			$key=$this->make_data_key($row);
			if (!array_key_exists($key, $this->data)) $this->data[$key]=[];
			$this->data[$key][$row['id']]=$row;
		}
	}
	
	public function make_data_key($row)
	{
		return $row[$this->field];
	}
	
	public function set_data($value=null)
	{
		if ($this->data===false) return $this->sign_report(new \Report_impossible('no_table'));
		if ($value instanceof \Report_impossible) return $this->sign_report(new \Report_impossible('bad_key'));
		if (is_array($value))
		{
			$missing=$this->filter_done($value);
			if (empty($missing)) return false;
			
			$this->add_keys($missing);
			return true;
		}
		else
		{
			if ($this->is_done($value)) return false;
			$this->add_keys($value);
			return true;
		}
	}
	
	public function compose_data($value=null)
	{
		if (is_array($value))
		{
			if ($this->data===false) return array_fill_keys($value, $this->sign_report(new \Report_impossible('no_table')));
			$result=[];
			foreach ($value as $val)
			{
				$result[$val]=$this->compose_data($val);
			}
			return $result;
		}
		else
		{
			$this->prepare_key($value);
			if ($this->data===false) return $this->sign_report(new \Report_impossible('no_table'));
			if (!array_key_exists($value, $this->data)) return $this->sign_report(new \Report_impossible('not_found'));
			if ($this->data[$value]===false) return $this->sign_report(new \Report_impossible('not_found'));
			return $this->data[$value];
		}
	}
	
	public function compose_data_case_insensitive($value, $prepared_data=null)
	{
		if (is_array($value))
		{
			$prepared_data=[];
			foreach ($this->data as $key=>$data)
			{
				$prepared_data[mb_strtolower($key)]=$data;
			}
		
			$result=[];
			foreach ($value as $val)
			{
				$result[$val]=$this->compose_data_case_insensitive($value, $prepared_data);
			}
			return $result;
		}
		else
		{
			$lower=mb_strtolower($value);
			if ($prepared_data!==null) $data=$prepared_data;
			else $data=$this->data;
			
			$result=null;
			foreach ($data as $key=>$row)
			{
				if ( (mb_strtolower($key)===$lower)&&(is_array($row)) ) $result=$row;
			}
			if (!is_array($result)) return $this->sign_report(new \Report_impossible('not_found'));
			return $result;
		}
	}
	
	public function group_fields() { return [$this->field]; }
	
	public function group_key() { return $this->field; }
	
	public function group_key_value_from_get_data_args($get_data_args)
	{
		return $get_data_args[0];
	}
	
	public static function is_groupable($get_data_args)
	{
		if (static::accepts_keys()!==1) return false; // на случай изменения логики при наследовании.
		return !is_array($get_data_args[0]); // если просят данные по объединённому набору, то их следует получать отдельным запросом.
	}
}

class RequestTicket_case_insensitive extends RequestTicket_special
{
	public function compose_data()
	{
		return $this->get_request()->compose_data_case_insensitive(...$this->get_data_args);
	}
}

// предназначена для добавления к классам-наследникам Request_by_field, чтобы сделать разбор данных рассчитывающим на уникальное значение поля.
// допускает, чтобы в таблице были записи, у которых требуемое поле равно NULL (таким образом поле не обязано быть уникальным ключом, достаточно быть уникальным среди не-NULL), но не позволяет найти их по значению NULL и, следовательно, не хранит их.
trait Request_field_is_unique
{
	public function record_result($result)
	{
		foreach ($result as $row)
		{
			$key=$row[$this->field];
			$this->data[$key]=$row;
		}
	}
	
	public function by_unique_field() { return true; }
}

// для этого класса можно не делать отдельного статического массива мультитонов, потому что поле не может быть одновременно уникальным и не уникальным, так что пересекаться эти наборы не должны.
// следует заметить, однако, что правильность обращения с полем как с уникальным лежит исключительно на коде, использующим эти запросы! У модуля Ретривера нет возможности самостоятельно определить, является ли то или иное поле уникальным.
class Request_by_unique_field extends Request_by_field
{
	use Request_field_is_unique;
}

class Request_by_id extends Request_by_unique_field
// такая реализация создаёт лишнюю копию кэша БД, но поскольку php использует принцип "copy on write", а содержимое записей не меняется, то лишний расход памяти должен быть небольшим.
{	
	public function __construct($table=null)
	{
		parent::__construct($table, 'id');
		$retriever=Retriever();
		$data=$retriever->data_by_table($table);
		if (!($data instanceof \Report_impossible)) $this->data=$data;
		
		// благодаря этому данный запрос будет получать данные по айди даже в случае, если они были получены другим запросом.
		
		$call=$this->data_hook_call();
		if ($call!==null) $retriever->add_call($call, 'stored_'.$this->table);
	}
	
	public function data_hook_call()
	{
		return
			function()
			{
				$this->data+= Retriever()->data[$this->table]; // совпадающие ключи не будут переписаны.
			};
	}

	public static function make_Multiton_class_name($args)
	{
		return static::std_make_Multiton_class_name($args);
	}
	
	// записывать результаты специально не требуется, потому что это уже делается при срабатывании крючка.
	public function record_result($result)
	{
	}
}

trait Request_using_id_and_group
{
	public function make_query()
	{
		$query=parent::make_query();
		$query['where']['id_group']=$this->id_group;
		return $query;
	}
}

class Request_by_id_and_group extends Request_by_id
{
	use
		Request_using_id_and_group,
		Request_field_is_unique; // восстанавливаем стандартное поведение.
		
	static $instances=[];
	
	public
		$field='id', // для хранения результатов
		$id_group;

	public function __construct($table=null, $id_group=null)
	{
		parent::__construct($table);
		$this->id_group=$id_group;
	}
	
	public function data_hook_call() { } // не требуется.
}

// класс для работы с общими таблицами, у которых больше одной записи для каждого идентификатора сущности.
class Request_by_id_and_group_multiple extends Request_by_field
{
	use Request_using_id_and_group;
	
	static $instances=[];
	
	public
		$id_group;
		
	public function __construct($table=null, $id_group=null)
	{
		parent::__construct($table, 'id');
		$this->id_group=$id_group;
	}
	
	public function data_hook_call() { } // не требуется.
}

class_alias('\Pokeliga\Retriever\Request_by_field', '\Pokeliga\Retriever\Request_links'); // реализация запросов к таблицам вроде "эволюция покемонов" никак не отличается от обычного запроса к таблице по значениям полей.

class Request_by_field_spectrum extends Request_by_field
{
	public function make_query()
	{
		$backup=$this->field;
		$this->field='%placeholder%';
		$query=parent::make_query();
		$query=Query::from_array($query);
		
		$this->field=$backup;
		$union_query=
		[
			'action'=>'select',
			'table'=>$query['table'],
			'union'=>[]
		];
		$additional_fields=[];
		foreach ($this->field as $field)
		{
			$subquery=clone $query;
			if (is_array($field))
			{
				$subquery->add_complex_condition(['field'=>$field, 'value'=>$subquery['where']['%placeholder%']]);
				$additional_fields[]=$field;
			}
			else $subquery->add_simple_condition($field, $subquery['where']['%placeholder%']);
			$subquery->remove_simple_condition('%placeholder%');
			$union_query['union'][]=$subquery;
		}
		
		foreach ($union_query['union'] as $subquery)
		{
			foreach ($additional_fields as $field)
			{
				$subquery->add_simple_field($field[1], $field[0]);
			}
		}
		$union_query=Query::from_array($union_query);
		
		return $union_query;
	}
	
	public function record_result($result)
	{
		foreach ($result as $row)
		{
			foreach ($this->field as $field)
			{
				if (is_array($field)) $field=$field[1];
				$key=$row[$field];
				if (!array_key_exists($key, $this->data)) $this->data[$key]=[];
				$this->data[$key][$row['id']]=$row;
			}
		}
	}
	
	public function make_data_key($row)
	{
		die('UNUSED');
	}
}

// по умолчанию подразумевает RequestTicket, куда все данные для работы закладываются в момент создания. но подходит также для старого подхода, когда создаётся объект класса Request, и данные для работы распределены между create_request() и get_data_set().
trait Task_processes_request
{
	public function progress()
	{	
		$data=$this->get_data_set();
		if ($data instanceof \Report_impossible)
		{
			if (reset($data->errors)==='not_found') $data=[]; // FIX! здесь не должно быть строкового сравнения.
			else return $this->impossible('no_data');
		}
		if ($data instanceof \Report_tasks) return $data->register_dependancies_for($this);
		
		$this->apply_data($data);
		$this->finalize();
	}
	
	public $request=null;
	public function get_request()
	{
		if ($this->request===null) $this->request=$this->create_request();
		return $this->request;
	}
	
	public function get_data_set()
	{
		return $this->get_request()->get_data_set();
	}
	
	abstract public function create_request();
	abstract public function apply_data($data);
}
?>