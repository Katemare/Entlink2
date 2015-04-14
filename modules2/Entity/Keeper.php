<?

// эта задача занимается как установкой значений для параметров, берущихся из БД (потенциально и другого хранилища), так и их записью.
abstract class Keeper extends Filler_for_entity
{
	use Prototyper;
	
	const MODE_LOAD=1, MODE_SAVE=2;
	
	static
		$prototype_class_base='Keeper_';
	
	public
		$id,
		$mode,
		$source_type=Value::BY_KEEPER;
	
	public static function for_value($value, $keeper_code=null /* для совместимости с родительским классом */ )
	{
		$keeper=static::from_prototype($keeper_code);
		$keeper->value=$value;
		$keeper->setup();
		return $keeper;
	}
	
	abstract public function load($master_filler=true); // либо заполняет значение, либо возвращает задачу (себя... или клон себя?), по выполнении которой значение будет заполнено.
	
	abstract public function save(&$request_data); // в отличие от загрузки, эта функция не выполняет задачу самостоятельно, а вызывается в рамках задачи Task_save_new_entity или Task_save_entity.
	
	public function progress()
	{
		if ($this->mode===static::MODE_LOAD) $this->progress_load();
		if ($this->mode===static::MODE_SAVE) die ('BAD MODE');
	}
	
	public abstract function progress_load();
	
	public function id()
	{
		if (!is_null($this->id)) return $this->id;
		
		$entity=$this->value->master->entity;
		if ($entity->state===Entity::STATE_VERIFIED_ID) $id=$entity->db_id;
		elseif ($entity->state===Entity::STATE_EXPECTED_ID) die ('UNIMPLEMENTED YET: expected id');
		elseif ($entity->state===Entity::STATE_FAILED) die ('UNIMPLEMENTED YET: failed entity');
		else die ('UNEXPECTED STATE: '.$entity->state);
		
		$this->id=$id;
		return $id;
	}
	
	public function id_group()
	{
		return $this->entity->id_group;
	}
}

// этот класс предполагает, что содержимое значение берётся из поля некой таблицы, и требуемая запись идентифицируется по полю id, равному айди сущности. Таблица и поле берутся из модели, а если нет - предполагаются.
class Keeper_db extends Keeper
{
	public
		$table=null,
		$field=null;
	
	public function table()
	{
		if ($this->table!==null) return $this->table;
		
		if ($this->in_value_model('table')) $table=$this->value_model_now('table');
		else $table=$this->value->master->default_table;
		$this->table=$table;
		return $table;
	}
	
	public function field()
	{
		if (!is_null($this->field)) return $this->field;
		
		if ($this->in_value_model('field')) $field=$this->value_model_now('field');
		else $field=$this->value->code;
		$this->field=$field;
		return $field;
	}

	public function prepare()
	{
		if ($this->table!==null) return;
		$this->table();
		$this->field();
		$this->id();	
	}
	
	public function prepare_result($result)
	{
		return $result;
	}
	
	public function load($master_filler=true)
	{
		$this->prepare();
		$report=$this->set_from_db(Request::GET_DATA_SOFT);
		
		if ( ($report instanceof Report_impossible) && (reset($report->errors)==='uncompleted') ) // STUB!
		{
			$this->mode=static::MODE_LOAD;
			$this->reset();
			return $this->sign_report(new Report_task($this));
		}
		return $report;
	}
	
	public $request=null;
	public function get_request()
	{
		if ($this->request===null)
		{
			if (Retriever()->is_common_table($table=$this->table())) $this->request=Request_by_id_and_group::instance($table, $this->entity->id_group);
			else $this->request=Request_by_id::instance($table);
		}
		return $this->request;
	}
	
	public function set_from_db($mode=Request::GET_DATA_SET)
	{	
		$data=$this->get_request()->get_data($this->id, $mode);
		if ($data instanceof Report) return $data; // если данные есть, Ретрирвер вернёт просто их. отчёт возвращается только в случае проблем.
		if (!array_key_exists($this->field, $data)) return $this->sign_report(new Report_impossible('no_field'));
	
		$this->resolution=$this->prepare_result($data[$this->field]);
		$this->finish();
		return $this->report();
	}
	
	public function progress_load()
	{
		$result=$this->set_from_db(Request::GET_DATA_SET);
		if ($result instanceof Report_tasks) $result->register_dependancies_for($this);
		elseif ($result instanceof Report_impossible)
		{
			$this->impossible($result->errors);
			return $result;
		}
	}
	
	public function impossible($errors=null)
	{
		if (($this->mode===static::MODE_LOAD)&&($this->value->filler_task===$this))
		{
			$this->value->set_state(Value::STATE_FAILED);
			$this->value->filler_task=null;
		}
		parent::impossible($errors);
	}
	
	public function save(&$request_data)
	{
		if (!$this->value->save_changes) return;
		if (!array_key_exists($table=$this->table(), $request_data)) $request_data[$table]=[];
		elseif (array_key_exists($this->field(), $request_data[$table])) die ('DOUBLE SAVE');
		$request_data[$table][$this->field()]=$this->value->for_db();	
	}
}

class Keeper_id_and_group extends Keeper_db
{
	public function id_group_field()
	{
		if ($this->in_value_model('id_group_field')) return $this->value_model_now('id_group_field');
		return $this->field().'_group';
	}
	
	public function prepare_result($id)
	{
		if (!is_numeric($id)) return $id;
		$data=$this->get_request()->get_data($this->id, $mode);
		$id_group=$data[$this->id_group_field()];
		$entity=$this->pool()->entity_from_db_id($id, $id_group);
		return $entity;
	}
	
	public function save(&$request_data)
	{
		parent::save($request_data);
		if (!$this->value->save_changes) return;
		$request_data[$table][$this->id_group_field()]=$this->value->get_entity()->id_group;	
	}
}

// хранит значение в качестве записи в отдельной таблице.
class Keeper_var extends Keeper
{
	const
		VAR_TABLE='entities_vars';
	
	public
		$code,
		$request,
		$ordered=null;
	
	public function ordered()
	{
		if ($this->ordered!==null) return $this->ordered;
		if ($this->in_value_model('var_ordered')) return $this->value_model_now('var_ordered');
		return false;
	}
	
	public function code()
	{
		if ($this->code===null)
		{
			if ($this->in_value_model('var')) $this->code=$this->value_model_now('var');
			else $this->code=$this->value->code;
			if (empty($this->code)) die('BAD VAR');
		}
		return $this->code;
	}
	
	public function get_request()
	{
		if ($this->request===null) $this->request=$this->create_request();
		return $this->request;
	}
	
	public function create_request()
	{
		return new RequestTicket_entity_var($this->id(), $this->id_group(),  $this->code());
	}
	
	public function prepare_result($result)
	{
		return reset($result);
	}
	
	public function load($master_filler=true)
	{
		$result=$this->get_request()->get_data_set();
		if ($result instanceof Report_impossible)
		{
			$this->no_data();
			return $this->report();
		}
		if ($result instanceof Report_tasks)
		{
			$this->mode=static::MODE_LOAD;
			$this->reset();
			$this->register_dependancies($result);
			return $this->sign_report(new Report_task($this));
		}
		return $this->prepare_result($result);
	}
	
	public function progress_load()
	{
		$result=$this->get_request()->get_data_set();
		if ($result instanceof Report_tasks) $result->register_dependancies_for($this);
		elseif ($result instanceof Report_impossible) $this->no_data();
		else $this->finish_with_resolution($this->prepare_result($result));
	}
	
	public function no_data()
	{
		if ($this->in_value_model('default')) $this->finish_with_resolution($this->value_model_now('default'));
		else $this->impossible('no_data');
	}
	
	public function save(&$request_data)
	{
		if (!$this->value->save_changes) return;
		if (!array_key_exists(static::VAR_TABLE, $request_data)) $request_data[static::VAR_TABLE]=[];
		
		if ( ($this->in_value_model('default')) && ($this->value->content()===$this->value_model_now('default')))
		{
			if (!array_key_exists('__delete', $request_data[static::VAR_TABLE])) $request_data[static::VAR_TABLE]['__delete']=[];
			$request_data[static::VAR_TABLE]['__delete'][]=$this->code();
		}
		else
		{
			$val=$this->value->for_db();
			$result=[];
			if (is_array($val)) $result=$val;
			if (is_numeric($val)) $result['number']=$val;
			else $result['str']=$val;
			$request_data[static::VAR_TABLE][$this->code()]=$result;
		}
	}
}

class Keeper_var_array extends Keeper_var
{
	public
		$ordered=null;
	
	public function ordered()
	{
		if ($this->ordered!==null) return $this->ordered;
		if ($this->in_value_model('var_ordered')) return $this->value_model_now('var_ordered');
		return false;
	}
	
	public function assotiative()
	{
		return ($this->in_value_model('assotiative')) && ($this->value_model_now('assotiative')===true);
	}
	
	public function prepare_result($result)
	{
		if (!is_array($result)) return $result;
		if ($this->assotiative())
		{
			$data=$result;
			$result=[];
			foreach ($data as $row)
			{
				$result[$row['str']]=(int)$row['number'];
			}
		}
		elseif ($this->ordered()) ksort($result);
		return $result;
	}
	
	public function no_data()
	{
		if ($this->in_value_model('default')) $this->finish_with_resolution($this->value_model_now('default'));
		else $this->finish_with_resolution([]);
	}
	
	public function save(&$request_data)
	{
		if (!$this->value->save_changes) return;
		if (!array_key_exists(static::VAR_TABLE, $request_data)) $request_data[static::VAR_TABLE]=[];
		if (!array_key_exists('__delete', $request_data[static::VAR_TABLE])) $request_data[static::VAR_TABLE]['__delete']=[];
		if (!array_key_exists($this->code(), $request_data[static::VAR_TABLE])) $request_data[static::VAR_TABLE][$this->code()]=[];
		$request_data[static::VAR_TABLE]['__delete'][]=$this->code();

		$array=$this->value->content();
		if (!is_array($array)) die ('UNIMPLEMENTED YET: non-array fallback');
		
		if ($this->assotiative())
		{
			$index=0;
			foreach ($array as $key=>$val)
			{
				$result=[];
				if (!is_numeric($val)) die('UNIMPLEMENTED YET: non-numeric assotiative values');
				$result['str']=$key;
				$result['number']=$val;
				$request_data[static::VAR_TABLE][$this->code()][$index++]=$result;
			}
		}
		else
		{
			foreach ($array as $index=>$val)
			{
				$result=[];
				if (is_array($val)) $result=$val;
				if (is_numeric($val)) $result['number']=$val;
				else $result['str']=$val;
				$request_data[static::VAR_TABLE][$this->code()][$index]=$result;
			}
		}
	}
}

class Request_entity_vars extends Request_by_id_and_group_multiple
{	
	public
		$vars_by_code=[];
	
	public function __construct($id_group=null)
	{
		parent::__construct(Keeper_var::VAR_TABLE, $id_group);
	}
	
	public function record_result($result)
	{
		parent::record_result($result);
		
		foreach ($result as $row)
		{
			$id=$row['id'];
			$code=$row['code'];
			if (!array_key_exists($id, $this->vars_by_code)) $this->vars_by_code[$id]=[];
			if ( ($row['number']!==null) &&  ($row['str']!==null) ) $value=['number'=>$row['number'], 'str'=>$row['str']];
			elseif ($row['number']!==null) $value=$row['number'];
			elseif ($row['str']!==null) $value=$row['str'];
			else $value=null;
			$index=$row['index'];
			if (array_key_exists($code, $this->vars_by_code[$id]))
			{
				if ( ($index===null) && (is_array($this->vars_by_code[$id][$code])) ) die('BAD VAR INDEX 1');
				elseif ( ($index!==null) && (!is_array($this->vars_by_code[$id][$code])) ) die('BAD VAR INDEX 2');
			}
			elseif ($index!==null) $this->vars_by_code[$id][$code]=[];
			if ($index===null) $this->vars_by_code[$id][$code]=$value;
			else $this->vars_by_code[$id][$code][$index]=$value;
		}
	}
	
	public function get_var($id, $code, $index=null)
	{
		if (!array_key_exists($id, $this->vars_by_code)) return $this->sign_report(new Report_impossible('no_var_data'));
		if (!array_key_exists($code, $this->vars_by_code[$id])) return $this->sign_report(new Report_impossible('no_var_data'));
		if ($index!==null)
		{
			if (!is_array($this->vars_by_code[$id][$code])) return $this->sign_report(new Report_impossible('no_array_data'));
			if (!array_key_exists($index, $this->vars_by_code[$id][$code])) return $this->sign_report(new Report_impossible('no_index_data'));
			return $this->vars_by_code[$id][$code][$index];
		}
		return $this->vars_by_code[$id][$code];
	}
}

class RequestTicket_entity_var extends RequestTicket_special
{
	public
		$id,
		$var_code,
		$index;
		
	public function __construct($id, $id_group, $var_code, $index=null)
	{
		parent::__construct('Request_entity_vars', [$id_group], [$id]);
		$this->id=$id;
		$this->var_code=$var_code;
		$this->index=$index;
	}
	
	public function compose_data()
	{
		return $this->get_request()->get_var($this->id, $this->var_code, $this->index);
	}
}
?>