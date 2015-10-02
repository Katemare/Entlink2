<?
namespace Pokeliga\Retriever;

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
	
	public function create_query()
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

class_alias('\Pokeliga\Retriever\Request_by_field', '\Pokeliga\Retriever\Request_links'); // реализация запросов к таблицам вроде "эволюция покемонов" никак не отличается от обычного запроса к таблице по значениям полей.

class Request_by_field_spectrum extends Request_by_field
{
	public function create_query()
	{
		$backup=$this->field;
		$this->field='%placeholder%';
		$query=parent::create_query();
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

?>