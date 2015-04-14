<?

// запросы этой группы преимущественно создаются фабрикой Request, к которой обращаются через требуемый класс запроса. Например, Request_all::count_instance(аргументы конструктора).
abstract class Request_reuser extends Request
{
	use Request_get_data_no_args;
	
	public
		$ticket,
		$subrequest,
		$spawn_new=true;
	
	public function __construct($ticket)
	{
		parent::__construct();
		$this->ticket=$ticket;
	}
	
	// создаётся в обход обычных instance, потому что инстанциируется в случае необходимости посредник, то есть лишние запросы всё равно не создаются. также так обеспечивается эксклюзивность подзапроса, чтобы в него не попадали лишние данные.
	public function create_subrequest($ticket=null)
	{
		if ($ticket===null) $ticket=$this->ticket;
		if ($this->spawn_new) return $ticket->standalone();
		return $ticket->get_request();
	}
	
	public function get_subrequest()
	{
		if ($this->subrequest===null) $this->subrequest=$this->create_subrequest();
		return $this->subrequest;
	}
	
	public function make_query()
	{
		$query=$this->get_subrequest()->make_query();
		$query=$this->modify_query($query);
		return $query;
	}
	
	public abstract function modify_query($query);
	
	public function process_result($result)
	{
		return $this->get_subrequest()->process_result($result);
	}
	
	public function data_processed()
	{
		$this->get_subrequest()->data_processed();
	}
	
	public function set_data()
	{
		$this->get_subrequest(); // чтобы создать запрос в тикете правильным образом.
		return $this->ticket->set_data();
	}
	
	public function compose_data()
	{
		$this->get_subrequest(); // чтобы создать запрос в тикете правильным образом.
		return $this->ticket->compose_data();
	}
	
	public function finish($success=true)
	{
		parent::finish($success);
		$this->get_subrequest()->finish($success); // FIX: возможно, это не вполне правильно - нужно посмотреть, не будет ли глюков.
	}
}

trait Request_reuser_capture_result
{
	public function process_result($result)
	{
		if ($result instanceof Report_impossible) return false;
		$this->result=$result;
		return true;
	}
	
	// хотя какой-нибудь Request_by_field в ответ на этот вызов отдаёт только соответствующие ключи, данный запрос использует механизмы подзапроса только для хранения ключей и формирования SQL-запроса, а возвращает всё.
	public function compose_data()
	{
		if (!property_exists($this, 'result')) return $this->sign_report(new Report_impossible('no_data'));
		return $this->result;
	}
}

class RequestTicket_union extends RequestTicket
{		
	public function __construct($subtickets)
	{
		parent::__construct('Request_union', [$subtickets]);
	}
	
	public function combine($ticket)
	{
		if ($this->request!==null) die('NEW SUBTICKETS POSTCREATION');
		if (is_array($ticket))
		{
			foreach ($ticket as $subticket)
			{
				$this->combine($subticket);
			}
		}
		else $this->constructor_args[0][]=$ticket;
		
		return $this;
	}
}

class Request_union extends Request_reuser
{
	use Request_reuser_capture_result;
	
	public function __construct($tickets)
	{
		if (!is_array($tickets)) $tickets=[$tickets];
		parent::__construct($tickets);
	}
	
	public function create_subrequest($ticket=null)
	{
		if ($ticket!==null) return parent::create_subrequest($ticket); // техническое создание.
		$result=[];
		foreach ($this->ticket as $ticket)
		{
			if ($this->spawn_new) $result[]=$ticket->standalone();
			else $result[]=$ticket->get_request();
		}
		return $result;
	}
	
	public function make_query()
	{
		$query=['action'=>'select', 'union'=>[]];
		foreach ($this->get_subrequest() as $request)
		{
			$subquery=$request->make_query();
			$this->modify_query($subquery);
			$query['union'][]=$subquery;
		}
		return $query;
	}
	
	public function modify_query($query) { }
		
	public function data_processed()
	{
		foreach ($this->get_subrequest() as $request)
		{
			$request->data_processed();
		}
	}
	
	public function finish($success=true)
	{
		Request::finish($success);
		foreach ($this->get_subrequest() as $request)
		{
			$request->finish($success); // должно работать нормально потому, что подзапрос обычно является целиком контролируемым данным.
		}
	}
	
	public function set_data()
	{
		$uncompleted=false;
		$this->get_subrequest(); // чтобы создать запрос в тикете правильным образом.
		foreach ($this->ticket as $ticket)
		{
			if ($set=$ticket->set_data()) $uncompleted=true;
		}
		return $uncompleted;
	}
}

class Request_ordered extends Request_reuser
{
	use Multiton, Request_reuser_capture_result;
		
	public
		$order;
	
	public function __construct($ticket, $order='id')
	{
		parent::__construct($ticket);
		$this->order=$order;
	}
	
	public function modify_query($query)
	{
		$query['order']=$this->order;
		return $query;
	}
}

class Request_limited extends Request_ordered
{
	use Request_get_data_one_arg;
	
	static $instances=[];
	
	public
		$requested_limit,
		$completed_limit=false;
	
	public function modify_query($query)
	{
		$query=parent::modify_query($query);
		if ($this->completed_limit===false) $query['limit']=[0, $this->requested_limit];
		else $query['limit']=[$this->completed_limit+1, $this->requested_limit];
		return $query;
	}
	
	public function set_data($limit=null)
	{
		$uncompleted_keys=parent::set_data();
		if ($limit>$this->requested_limit) $this->requested_limit=$limit;
		if ($this->requested_limit>$this->completed_limit) return true;
		return $uncompleted_keys;
	}
	
	public function compose_data($limit=null)
	{
		if ($this->result instanceof Report_impossible) return $this->result;
		if ($limit>$this->completed_limit) return $this->sign_report(new Report_impossible('limit_uncompleted'));
		return array_slice($this->result, 0, $limit);
	}
	
	public function process_result($result)
	{
		if ($result instanceof Report_impossible) return false;
		$this->completed_limit=$this->requested_limit;
		if (empty($this->result)) $this->result=[];
		$this->result=array_merge($this->result, $result);
		return true;
	}
}

class Request_page extends Request_ordered
{
	static $instances=[];
	
	public
		$per_page=50,
		$page=1,
		$done=false;

	public function __construct($ticket, $order='id', $page, $per_page=50)
	{
		parent::__construct($ticket, $order);
		$this->per_page=$per_page;
		$this->page=$page;
	}
	
	public function modify_query($query)
	{
		$query=parent::modify_query($query);
		$query['limit']=[ ($this->page-1)*$this->per_page, $this->per_page];
		return $query;
	}
	
	public function set_data()
	{
		$uncompleted_keys=parent::set_data();
		if ( ($uncompleted_keys) && ($this->done) ) die('DONT ADD KEYS');
		return !$this->done;
	}
	
	public function compose_data()
	{
		if ($this->result instanceof Report_impossible) return $this->result;
		if (!$this->done) return $this->sign_report(new Report_impossible('page_uncompleted'));
		return $this->result;
	}
	
	public function process_result($result)
	{
		$this->done=true;
		if ($result instanceof Report_impossible) return false;
		$this->result=$result;
		return true;
	}
}

class Request_random extends Request_reuser
{
	use Request_reuser_capture_result;

	public
		$limit=null,
		$result=null;
	
	public function __construct($ticket, $limit=null)
	{
		parent::__construct($ticket);
		$this->limit=$limit;
	}
	
	public function modify_query($query)
	{
		$query['order']=[ ['expression'=>'RAND()'] ];
		if ($this->limit!==null) $query['limit']=[0, $this->limit];
		return $query;
	}
	
	public function set_data()
	{
		$result=parent::set_data();
		if ( ($result===true) && ($this->completed()) ) return $this->sign_report(new Report_impossible('new_keys_after_completion'));
		return $result;
	}
}

// прогоняет запрос через вызовы, модифицирующие его.
class Request_modify_by_calls extends Request_reuser
{
	use Request_reuser_capture_result;
	
	public
		$calls;
		
	public function __construct($ticket, ...$calls)
	{
		parent::__construct($ticket);
		$this->calls=$calls;
	}
	
	// вызовы должны модифицировать запрос как объект, а не возвращать новый.
	public function modify_query($query)
	{
		$query=Query::from_array($query);
		foreach ($this->calls as $call)
		{
			$call($query);
		}
		return $query;
	}
}

class RequestTicket_modify_query extends RequestTicket
{
	public
		$calls;
		
	public function __construct($ticket, ...$calls)
	{
		$args=$calls;
		array_unshift($args, $ticket);
		parent::__construct('Request_modify_by_calls', $args);
	}
}

class Request_search extends Request_reuser
{
	use Multiton, Request_reuser_capture_result;
	
	public
		$search_field,
		$search,
		$op='=';
	
	public function __construct($ticket, $search_field, $search)
	{
		parent::__construct($ticket);
		$this->search_field=$search_field;
		$this->search=$search;
	}
	
	public function modify_query($query)
	{
		$query=Query::from_array($query);
		$query->add_complex_condition(['field'=>$this->search_field, 'op'=>$this->op, 'value'=>$this->compare_to()]);
		return $query;
	}
	
	public function compare_to()
	{
		return $this->search;
	}
}
	
class Request_search_text extends Request_search
{
	static $instances=[];
	
	public
		$op='LIKE';
		
	public function compare_to()
	{
		$like=Retriever()->safe_text_like($this->search).'%';
		if ( (mb_strlen($this->search)>1) || (is_numeric($this->search)) ) $like='%'.$like;
		return $like;
	}
}

// берёт дочерний запрос и добавляет к нему групповые функции - сразу ко всему запросу. если нужна статистика по единственному ключу, то следует использовать класс после этого.
class Request_group_functions extends Request_reuser
{
	use Multiton, Request_get_data_one_arg
	{
		Multiton::make_Multiton_key as std_make_Multiton_key;
		Multiton::make_Multiton_class_name as std_make_Multiton_class_name;
	}
	
	static
		$good_functions=['count', 'count_distinct', 'sum', 'avg', 'min', 'max'],
		$default_fields=['count'=>'id'];
	
	public
		$requested=[],	// в формате ['функция'=>['поле', 'поле'...], 'функция'=>...]
		$stats=[],		// в том же формате.
		$tried_functions=[]; // в формате ['функция'=>true, 'функция'=>true...] - для быстрого исключения случаев, если функция вообще не запрашивалась.
	
	public static function is_ticket_groupable($ticket)
	{
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
		if (empty($this->requested)) return $this->sign_report(new Report_impossible('no_requested_functions'));
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
			else return $this->sign_report(new Report_impossible('no_function_field'));
		}
		else
		{
			$result=[];
			foreach ($functions as $function=>&$fields)
			{
				if ( (is_numeric($function)) && (!is_array($fields)) )
				{
					$normalize=$this->normalize_functions($fields);
					if ($normalize instanceof Report_impossible) return $normalize;
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
				if (array_key_exists($key, $this->stats)) continue;
				if (!array_key_exists($function, $new_keys)) $new_keys[$function]=[];
				$new_keys[$function][]=$field;
			}
		}
		return $new_keys;
	}
	
	public function set_data($functions=null) // функции подаются в формате 'функция' (если есть запись в static::$default_fields; 'функция=>поле'; или 'функция'=>['поле', 'поле'...].
	{
		$uncompleted=parent::set_data();
	
		$functions=$this->normalize_functions($functions);
		if ($functions instanceof Report_impossible) return $functions;
		
		if (count(array_diff(array_keys($functions), static::$good_functions))>0) return $this->sign_report(new Report_impossible('bad_functions'));
		
		$new_keys=$this->filter_done($functions);
		
		$has_new_keys=!empty($new_keys);
		if ($has_new_keys)
		{
			$this->requested=array_merge_recursive($this->requested, $new_keys);
		}
		
		return $has_new_keys || $uncompleted;
	}
	
	public function process_result($result)
	{
		if ($result instanceof Report_impossible) return false;
		$result=reset($result);
		foreach ($this->requested as $function=>$fields)
		{
			$this->tried_functions[$function]=true;
		}
		$this->stats=array_merge($this->stats, $result);
		return true;
	}
	
	public function data_processed()
	{
		$this->requested=[];
		parent::data_processed();
	}
	
	public function compose_data($functions=null)
	{		
		if (!is_array($functions)) $single_key=$functions;
		$functions=$this->normalize_functions($functions);
		if ($functions instanceof Report_impossible) return $functions;
		
		$result=[];
		foreach ($functions as $function=>$fields)
		{
			foreach ($fields as $field)
			{
				$key=$this->make_key($function, $field);
				if (array_key_exists($key, $this->stats)) $result[$key]=$this->stats[$key];
				else $result[$key]=$this->sign_report(new Report_impossible('no_function_result'));
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
		parent::__construct('Request_group_functions', [$ticket], [$functions]);
		
		$this->get_data_args[]=$ticket; // для подачи в Request_grouped_functions, а Request_group_functions дополнительный аргумент просто не воспринимает.
	}
}

class RequestTicket_count extends RequestTicket_group_functions
{
	public function __construct($class, $constructor_args=[], $get_data_args=[])
	{
		if ($class instanceof RequestTicket) $ticket=$class;
		else $ticket=new RequestTicket($class, $constructor_args, $get_data_args);

		RequestTicket::__construct('Request_group_functions', [$ticket], ['count']);
		
		$this->get_data_args[]=$ticket; // для подачи в Request_grouped_functions, а Request_group_functions дополнительный аргумент просто не воспринимает.
	}
}

class Request_grouped_functions extends Request_group_functions
{
	// не имеет собственной функциональности для -тона, потому что создаётся классом Request_group_functions.
	use Request_get_data_two_args;
	
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
	
	// FIX! будет неправильно работать, если нужные функции все опробованы, ключи тоже, но не все сочетания функций с ключами.
	public function set_data($functions=null, $ticket=null)
	{
		$uncompleted=parent::set_data($functions);
		
		if (!$this->ticket->coinstance($ticket)) die('BAD GROUPED TICKET 1');
		$ticket->set_request($this->ticket);
		$uncompleted_ticket=$ticket->set_data();
		
		return $uncompleted || $uncompleted_ticket;
	}
	
	public function process_result($result)
	{
		if ($result instanceof Report_impossible) return false;
		
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
	
	public function compose_data($functions=null, $ticket=null)
	{
		if ($functions===null) return $this->sign_report(new Report_impossible('no_functions_argument'));
		
		if (!is_array($functions)) $single_key=$functions;
		$functions=$this->normalize_functions($functions);
		if ($functions instanceof Report_impossible) return $functions;
		
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
					else $result[$subrequest_key][$key]=$this->sign_report(new Report_impossible('no_function_result'));
				}
			}
			if (!empty($single_key)) $result[$subrequest_key]=$result[$subrequest_key][$single_key];
		}
		if ($single_subrequest_key!==false) return $result[$single_subrequest_key];
		
		return $result;
	}
}
?>