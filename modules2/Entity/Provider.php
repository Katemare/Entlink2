<?
abstract class Provider extends Task_for_entity
{
	use Prototyper;
	
	static
		$prototype_class_base='Provide_';
	
	public function subprovider($type_keyword, $args, $master)
	{
		$provider=static::from_prototype($type_keyword);
		$provider->entity=$master->entity;
		$provider->setup_by_args($args);
		return $provider;
	}
	
	public function setup($entity, $function_args=[])
	{
		parent::setup($entity, $function_args);
		$args=array_slice($entity->provider, 1);
		$this->setup_by_args($args);
	}
	
	public function setup_by_args($args) {}
	
	public static function filler_for_value($value) // когда вызывается в попытке создать Филлер для значения.
	{
		$provider=new static(); // обычно делается из прототипа,
		$filler=Fill_by_provider::for_provider($provider, $value);
		return $filler;
	}
	
	public function setup_by_value($value) {}
	
	public function finish($success=true)
	{
		if ( ($success) && ($this->resolution===null) ) { debug_dump(); die ('NO PROVIDER RESOLUTION: '.get_class($this)); }
		if ($this->entity->provider===$this)
		{
			$this->entity->provider=null;
			if ($success===true)
			{
				$this->entity->receive_db_id($this->resolution);
				$this->entity->verified();
			}
			else $this->entity->failed_db_id();
		}
		parent::finish($success);
	}
}

class Fill_by_provider extends Filler
{
	public
		$provider,
		$entity;
	
	public static function for_provider($provider, $value)
	{
		$filler=static::for_value($value);
		$filler->setup_by_provider($provider);
		return $filler;
	}
	
	public function setup_by_provider($provider)
	{
		$this->provider=$provider;
		$pool=$this->pool();
		if ($this->in_value_model('id_group')) $id_group=$this->value_model_now('id_group');
		else $id_group=null;
		$this->entity=$pool->entity_from_provider($provider, $id_group);
		$this->provider->entity=$this->entity;
		$provider->setup_by_value($this->value);
	}
	
	public function progress()
	{
		if ($this->provider->successful()) $this->finish_with_resolution($this->entity);
		elseif ($this->provider->failed()) $this->impossible('failed_provider');
		else $this->register_dependancy($this->provider);
	}
}

abstract class Provide_by_single_request extends Provider
{
	use Task_processes_request;
	
	public function apply_data($data)
	{
		if (empty($data)) $this->impossible();
		else
		{
			if ($this->get_request()->by_unique_field()) $id=$data['id'];
			else $id=reset($data)['id'];
			
			$this->finish_with_resolution($id);
		}
	}
	
	/*
	abstract public function create_request();
	*/
}

class Provide_by_unique_field extends Provide_by_single_request
{
	public
		$field,
		$code,
		$table=null,
		$value_content;
	
	public function setup_by_args($args)
	{
		$this->code=$args[0];
		$this->value_content=$args[1];			
	}
	
	public function field()
	{
		if ($this->field===null)
		{
			$type=$this->entity->type;
			$model=$type::$data_model[$this->code];
			if (array_key_exists('field', $model)) $this->field=$model['field'];
			else $this->field=$this->code;
		}
		return $this->field;
	}
	
	public function value_content()
	{
		return $this->value_content;
	}
	
	public function table()
	{
		if ($this->table===null)
		{
			$aspect=$this->entity->get_aspect('basic');
			$this->table=$aspect::$default_table; // STUB
		}
		return $this->table;
	}
	
	public function create_request()
	{
		return new RequestTicket('Request_by_unique_field', [$this->table(), $this->field()], [$this->value_content()]);
	}
}

class Provide_by_field /* non-unique */extends Provide_by_unique_field
{
	public function apply_data($data)
	{
		if (count($data)>1) return $this->impossible('multiple_entries');
		$data=reset($data);
		parent::apply_data($data);
	}
	
	public function create_request()
	{
		return new RequestTicket('Request_by_field', [$this->table(), $this->field()], [$this->value_content()]);
	}
}

class Provide_by_unique_field_case_insensitive extends Provide_by_unique_field
{
	public function create_request()
	{
		return new RequestTicket_case_insensitive('Request_by_unique_field', [$this->table(), $this->field()], [$this->value_content()]);
	}
}

class Provide_by_field_case_insensitive extends Provide_by_field
{
	public function create_request()
	{
		return new RequestTicket_case_insensitive('Request_by_field', [$this->table(), $this->field()], [$this->value_content()]);
	}
}

class Provide_sibling extends Provide_by_single_request
{
	const
		DIR_NEXT=1,
		DIR_PREV=2,
		DIR_DEFAULT=Provide_sibling::DIR_NEXT;
	
	static
		$op=[Provide_sibling::DIR_NEXT=>['>', 'ASC'], Provide_sibling::DIR_PREV=>['<', 'DESC']];
	
	public
		$direction=Provide_sibling::DIR_DEFAULT,
		$reference;
	
	public function setup_by_args($args)
	{
		$this->reference=$args[0];
		if (array_key_exists(1, $args)) $this->direction=$args[1];
		if (!array_key_exists($this->direction, static::$op)) $this->direction=static::DIR_DEFAULT;
	}
	
	public
		$table;
		
	public function table()
	{
		if ($this->table===null)
		{
			$type=$this->entity->type;
			$class=$type::$base_aspects['basic'];
			$this->table=$class::$default_table; // STUB
		}
		return $this->table;
	}
	
	public function progress()
	{
		if ($this->reference->is_to_verify())
		{
			$result=$this->reference->exists(false);
			if ($result instanceof Report_tasks) return $result->register_dependancies_for($this);
		}
		if (!$this->reference->exists()) return $this->impossible('reference_failed');
		parent::progress();
	}
	
	public function create_request()
	{
		return new RequestTicket('Request_single', [$this->make_query()]);
	}
	
	public function make_query()
	{
		$query=
		[
			'action'=>'select',
			'table'=>$this->table(),
			'where'=>[ ['field'=>'id', 'op'=>static::$op[$this->direction][0], 'value'=>$this->reference->db_id] ],
			'order'=>[ ['id', 'dir'=>static::$op[$this->direction][1]] ],
			'limit'=>[0, 1]
		];
		return $query;
	}
	
	public function get_data_set()
	{
		$rows=$this->get_request()->get_data_set();
		if ($rows instanceof Report) return $rows;
		if (empty($rows)) return $this->sign_report(new Report_impossible('not_found'));
		return $rows;
	}
}

class Provide_random extends Provide_by_single_request
{
	public
		$table,
		$request_class='Request_all';
		
	public function table()
	{
		if ($this->table===null)
		{
			$type=$this->entity->type;
			$class=$type::$base_aspects['basic'];
			$this->table=$class::$default_table; // STUB
		}
		return $this->table;
	}
	
	public function create_request()
	{
		$range=new RequestTicket($this->request_class, [$this->table()]);
		return new RequestTicket('Request_random', [$range, 1]);
	}
	
	public function get_data_set()
	{
		$data=$this->get_request()->get_data_set();
		return $data;
	}
}
?>