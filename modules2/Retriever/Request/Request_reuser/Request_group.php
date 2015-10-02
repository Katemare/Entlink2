<?
namespace Pokeliga\Retriever;

/**
* Берёт дочерний запрос и добавляет к нему групповые функции - сразу ко всему запросу.
* Например, превращает запрос "получить все записи" в запрос "получить количество записей".
*/
class Request_group_functions extends Request_reuser
{
	use \Pokeliga\Entlink\Multiton
	{
		\Pokeliga\Entlink\Multiton::make_Multiton_key as std_make_Multiton_key;
		\Pokeliga\Entlink\Multiton::make_Multiton_class_name as std_make_Multiton_class_name;
	}
	
	/**
	* @var array $good_functions Список приемлемых групповых функций.
	* @var array $default_fields Поля по умолчанию для групповых функций.
	*/
	static
		$good_functions=['count', 'count_distinct', 'sum', 'avg', 'min', 'max'],
		$default_fields=['count'=>'id'];
	
	/**
	* @var array $requested Запрошенные функции в формате ['функция'=>['поле', 'поле'...], 'функция'=>...].
	* @var array $tried_functions Уже готовые функции в формате ['функция'=>true, 'функция'=>true...] - для быстрого исключения случаев, если функция вообще не запрашивалась.
	*/
	protected
		$requested=[],
		$tried_functions=[];
	
	public function keys_number() { return 1; }
	
	public static function is_ticket_groupable($ticket)
	{
		return false; // FIXME
		$subrequest_class=$ticket->class;
		if  (!$subrequest_class::is_ton()) return false;
		if (!string_instanceof($subrequest_class, 'Request_groupable')) return false;
		if (!$subrequest_class::is_groupable($ticket->get_data_args)) return false;
		return true;
	}
	
	public static function make_Multiton_key($args)
	{
		if (static::is_ticket_groupable($args[0])) return $args[0]->make_Multiton_key();
		else return static::std_make_Multiton_key($args);
	}
	
	public static function make_Multiton_class_name($args)
	{
		if (static::is_ticket_groupable($args[0])) return 'Request_grouped_functions';
		return static::std_make_Multiton_class_name($args);
	}
	
	public function is_ready()
	{
		if (empty($this->requested)) return $this->sign_report(new \Report_impossible('no_requested_functions'));
		return true;
	}
	
	public function modify_query($query)
	{
		$query['fields']=[];
		foreach ($this->requested as $function=>&$fields)
		{
			$fields=array_unique($fields);
			foreach ($fields as $field)
			{
				// STUB
				if ($function==='count_distinct') $query['fields'][]=['function'=>'count', 'distinct'=>true, 'field'=>$field, 'alias'=>$this->make_key($function, $field)];
				else $query['fields'][]=['function'=>$function, 'field'=>$field, 'alias'=>$this->make_key($function, $field)];
			}
		}
		return $query;
	}
	
	public function normalize_functions($functions)
	{
		if (!is_array($functions))
		{
			if (array_key_exists($functions, static::$default_fields)) $functions=[$functions=>[static::$default_fields[$functions]]];
			else return $this->sign_report(new \Report_impossible('no_function_field'));
		}
		else
		{
			$result=[];
			foreach ($functions as $function=>&$fields)
			{
				if ( (is_numeric($function)) && (!is_array($fields)) )
				{
					$normalize=$this->normalize_functions($fields);
					if ($normalize instanceof \Report_impossible) return $normalize;
					$result=array_merge_recursive($result, $normalize);
				}
				elseif (!is_array($fields)) $result=array_merge_recursive($result, [$function=>[$fields]]);
				else $result=array_merge_recursive($result, [$function=>$fields]);
			}
			return $result;
		}
		return $functions;
	}
	
	public function make_key($function, $field)
	{
		if ( (array_key_exists($function, static::$default_fields)) && (static::$default_fields[$function]===$field) ) return $function;
		return $function.'_'.$field;
	}
	
	public function filter_done($functions)
	{
		$new_keys=[];
		foreach ($functions as $function=>$fields)
		{
			if (!array_key_exists($function, $this->tried_functions))
			{
				$new_keys[$function]=$fields;
				continue;
			}
			foreach ($fields as $field)
			{
				$key=$this->make_key($function, $field);
				if (array_key_exists($key, $this->data)) continue;
				if (!array_key_exists($function, $new_keys)) $new_keys[$function]=[];
				$new_keys[$function][]=$field;
			}
		}
		return $new_keys;
	}
	
	public function set_data($functions=null, ...$keys) // функции подаются в формате 'функция' (если есть запись в static::$default_fields; 'функция=>поле'; или 'функция'=>['поле', 'поле'...].
	{
		$uncompleted=parent::set_data();
	
		$functions=$this->normalize_functions($functions);
		if ($functions instanceof \Report_impossible) return $functions;
		
		if (count(array_diff(array_keys($functions), static::$good_functions))>0) return $this->sign_report(new \Report_impossible('bad_functions'));
		
		$new_keys=$this->filter_done($functions);
		
		$has_new_keys=!empty($new_keys);
		if ($has_new_keys)
		{
			$this->requested=array_merge_recursive($this->requested, $new_keys);
		}
		
		return $has_new_keys || $uncompleted;
	}
	
	protected function process_result($result)
	{
		if ($result instanceof \Report_impossible) return false;
		$result=reset($result);
		foreach ($this->requested as $function=>$fields)
		{
			$this->tried_functions[$function]=true;
		}
		if ($this->data===null) $this->data=[];
		$this->data=array_merge($this->data, $result);
		return true;
	}
	
	protected function data_processed()
	{
		$this->requested=[];
		parent::data_processed();
	}
	
	public function compose_data($functions=null, ...$keys)
	{		
		if (!is_array($functions)) $single_key=$functions;
		$functions=$this->normalize_functions($functions);
		if ($functions instanceof \Report_impossible) return $functions;
		
		$result=[];
		foreach ($functions as $function=>$fields)
		{
			foreach ($fields as $field)
			{
				$key=$this->make_key($function, $field);
				if (array_key_exists($key, $this->data)) $result[$key]=$this->data[$key];
				else $result[$key]=$this->sign_report(new \Report_impossible('no_function_result'));
			}
		}
		
		if (!empty($single_key)) return $result[$single_key];
		return $result;
	}
}

// обращение к запросу Request_group_functions и его наследникам следует производить только через следующие билеты.
class RequestTicket_group_functions extends RequestTicket
{
	public function __construct($ticket, $functions)
	{
		parent::__construct('\Pokeliga\Retriever\Request_group_functions', [$ticket], [$functions]);
		
		$this->get_data_args[]=$ticket; // для подачи в Request_grouped_functions, а Request_group_functions дополнительный аргумент просто не воспринимает.
	}
}

class RequestTicket_count extends RequestTicket_group_functions
{
	public function __construct($class, $constructor_args=[], $get_data_args=[])
	{
		if ($class instanceof RequestTicket) $ticket=$class;
		else $ticket=new RequestTicket($class, $constructor_args, $get_data_args);

		RequestTicket::__construct('\Pokeliga\Retriever\Request_group_functions', [$ticket], ['count']);
		
		$this->get_data_args[]=$ticket; // для подачи в Request_grouped_functions, а Request_group_functions дополнительный аргумент просто не воспринимает.
	}
}

/*
// пока не используется!

class Request_grouped_functions extends Request_group_functions
{
	public function keys_number() { return 2; }
	
	// не имеет собственной функциональности для -тона, потому что создаётся классом Request_group_functions.	
	public function modify_query($query)
	{
		$query=parent::modify_query($query);
		$query['fields'][]=$this->group_key();
		$query['group']=$this->group_fields();
		
		$query=Query::from_array($query);
		return $query;
	}
	
	public function group_key()
	{
		$group_key=$this->get_subrequest()->group_key();
		if (!is_array($group_key)) $group_key=['field'=>$group_key];
		$group_key['alias']='group_key';
		return $group_key;
	}
	
	public function group_fields()
	{
		return $this->get_subrequest()->group_fields();
	}
	
	// FIXME! будет неправильно работать, если нужные функции все опробованы, ключи тоже, но не все сочетания функций с ключами.
	public function set_data($functions=null, $ticket=null, ...$keys)
	{
		$uncompleted=parent::set_data($functions);
		
		if (!$this->ticket->coinstance($ticket)) die('BAD GROUPED TICKET 1');
		$ticket->set_request($this->ticket);
		$uncompleted_ticket=$ticket->set_data();
		
		return $uncompleted || $uncompleted_ticket;
	}
	
	protected function process_result($result)
	{
		if ($result instanceof \Report_impossible) return false;
		
		foreach ($result as $row)
		{
			$group_key=$row['group_key'];
			if (!array_key_exists($group_key, $this->stats)) $this->stats[$group_key]=[];
			foreach ($this->requested as $function=>$fields)
			{
				$this->tried_functions[$function]=true;
				foreach ($fields as $field)
				{
					$key=$this->make_key($function, $field);
					
					$value=$row[$key];
					if (ctype_digit($value)) $value=(int)$value;
					elseif ($value!==null) $value=(float)$value;
					
					$this->stats[$group_key][$key]=$value;
				}
			}
		}
		return true;
	}
	
	public function compose_data($functions=null, $ticket=null, ...$keys)
	{
		if ($functions===null) return $this->sign_report(new \Report_impossible('no_functions_argument'));
		
		if (!is_array($functions)) $single_key=$functions;
		$functions=$this->normalize_functions($functions);
		if ($functions instanceof \Report_impossible) return $functions;
		
		$result=[];
		
		$this->get_subrequest();
		if (!$this->ticket->coinstance($ticket)) die('BAD GROUPED TICKET 2');
		$ticket->set_request($this->ticket);
		
		$subrequest_keys=$ticket->get_request()->group_key_value_from_get_data_args($ticket->get_data_args);
		$single_subrequest_key=false;
		if (!is_array($subrequest_keys))
		{
			$single_subrequest_key=$subrequest_keys;
			$subrequest_keys=[$subrequest_keys];
		}
		
		foreach ($subrequest_keys as $subrequest_key)
		{
			$result[$subrequest_key]=[];
			foreach ($functions as $function=>$fields)
			{
				foreach ($fields as $field)
				{
					$key=$this->make_key($function, $field);
					if ( (array_key_exists($subrequest_key, $this->stats)) && (array_key_exists($key, $this->stats[$subrequest_key])) )
						$result[$subrequest_key][$key]=$this->stats[$subrequest_key][$key];
					else $result[$subrequest_key][$key]=$this->sign_report(new \Report_impossible('no_function_result'));
				}
			}
			if (!empty($single_key)) $result[$subrequest_key]=$result[$subrequest_key][$single_key];
		}
		if ($single_subrequest_key!==false) return $result[$single_subrequest_key];
		
		return $result;
	}
}
*/

?>