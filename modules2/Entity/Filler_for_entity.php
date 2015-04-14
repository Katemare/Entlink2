<?

// специальный Filler для значений в составе Aspect, чтобы иметь быстрый доступ к управляющей сущности.
abstract class Filler_for_entity extends Filler
{
	use Task_for_entity_methods;
	
	public
		$entity,
		$pool;
	
	public function setup()
	{
		parent::setup();
		if (!empty($this->value->master->entity))
		{
			$this->entity=$this->value->master->entity;
			$this->pool=$this->entity->pool;
		}
	}
}

class Filler_for_entity_dice extends Filler_for_entity
{
	public $max=6;
	
	public function fill()
	{
		$this->resolution=rand(1, $this->max);
		$this->finish();
		return $this->report();
	}
	
	public function progress()
	{
		die('SHOULDNT GET HERE');
	}
}

// этот класс отвечает за заполнение значения сущности, за исключением особенных (reference). Именно он разбирается, нужно ли прежде удостовериться в существовании сущности, есть ли Keeper и следует ли его использовать, в каком порядке устанавливать значения по умолчанию...
class Filler_for_entity_generic extends Filler_for_entity
{
	use Task_steps;
	
	const
		STEP_VERIFY_ENTITY=0,
		STEP_CHECK_ASPECT=1,
		STEP_DETERMINE_FILLER=2,
		STEP_APPLY_FILLER=3;

	public
		$master_capable=true,
		$filler;
	
	public function run_step()
	{
		if ($this->step===static::STEP_VERIFY_ENTITY) // этот шаг при необходимости запрашивает подтверждение сущности.
		{
			if (!$this->entity->is_to_verify()) return $this->advance_step(); // здесь и далее такое обращение - это не необходимость в возвращении ответа advance_step(), а досрочный выход из метода, типа, закончили обрабатывать шаг.
			$report=$this->verify_entity();
			if ($report instanceof Report_final) return $this->advance_step();
			return $report;
		}
		elseif ($this->step===static::STEP_CHECK_ASPECT) // анализирует подтверждённую сущность. вдруг она провалилась? а также получает аспект, если его нет.
		{
			if ($this->entity->state===Entity::STATE_FAILED) return $this->sign_report(new Report_impossible('entity_failed')); // эта задача может выполняться и для несуществующей сущности, например, новой или виртуальной.

			$type=$this->entity->type;
			$aspect_code=$type::locate_name($this->value->code);
			$aspect=$this->entity->get_aspect($aspect_code, false);
			if ($aspect instanceof Report_tasks) return $aspect;
			
			return $this->advance_step(); // во всех остальных случаях можно действовать дальше.
		}
		elseif ($this->step===static::STEP_DETERMINE_FILLER) // этот шаг, наконец, пытается заполнить содержиое сущности. WIP! выбор генератора или генераторов может быть более сложный: попытка прочесть, попытка вычислить, попытка заполнить по умолчанию...
		{
			if
			(
				($this->entity->not_loaded()) ||						// если сущность новая или виртуальная...
				($this->original_state===Value::STATE_IRRELEVANT) ||	// или если значение устарело...
				(($keeper_code=$this->determine_keeper_code())===false)		// или если значение не является хранимым.
			)
			{
				$result=$this->determine_generator();
				if ($result instanceof Report_task) $this->filler=$result->task; // метод должен возвращать строго одну задачу!
				return $result;
			}			
			else
			// если значение хранится, а не генерируется...
			{
				// ...то надо достать его из места, где оно хранится.
				$keeper=$this->value->determine_keeper();
				$this->source_type=Value::BY_KEEPER;
				$this->filler=$keeper;
				$report=$keeper->load();
				return $report;
			}
		}
		elseif ($this->step===static::STEP_APPLY_FILLER)
		{
			if ( ($this->filler->failed()) && ( ($fallback=$this->fallback())!==null) && ($fallback instanceof Report_resolution) ) return $fallback;
			else
			{
				$this->source_type=$this->filler->source_type;
				return $this->filler->report();
			}
		}
	}
	
	public function determine_keeper_code()
	{
		return $this->value->keeper_code();
	}
	
	public function determine_generator()
	{
		if ($this->value->mode===Value::MODE_CONST)
		{
			$this->source_type=Value::NEUTRAL_CHANGE;
			return $this->sign_report(new Report_resolution($this->value_model_now('const')));
		}
		
		$filler=$this->value->determine_generator();
		if (!empty($filler))
		{
			$filler->source_type=Value::NEUTRAL_CHANGE;
			return $filler->fill_with_master($this);
		}
		
		if ( ($fallback=$this->fallback())!==null) return $fallback;
		
		return $this->sign_report(new Report_impossible('no_generator: '.$this->value->code));
	}
	
	public function fallback()
	{
		if ($this->in_value_model('default'))
		{
			$this->source_type=Value::NEUTRAL_CHANGE;
			return $this->sign_report(new Report_resolution($this->value_model_now('default')));
			// когда понадобится, вполне возможно включить адекватную обработку компактора-задачи, только нужно изменить поведение также в шаге STEP_APPLY_FILLER
		}
	}
	
	public function verify_entity()
	{
		return $this->entity->verify(false); // этот метод тоже по возможности пытается подтвердить сущность не прибегая к процессу, но может вернуть Report_task, если требуется процесс.
	}
}


// для значения из класса Value_entity
class Filler_for_entity_value extends Filler_for_entity
{
	use Task_steps;

	const
		STEP_REQUEST_SOURCE_ID=0, // заменяет STEP_DETERMINE_FILLERS=2;
		STEP_CREATE_ENTITY=1,
		STEP_NO_ID=10;
		
	public function run_step()
	{
		if ($this->step===static::STEP_REQUEST_SOURCE_ID)
		{
			$id_source_code=$this->value_model_now('id_source');
			$report=$this->entity->request($id_source_code);
			if ($report instanceof Report_resolution)
			{
				$resolution=$this->generate_entity($report->resolution);
				return $this->sign_report(new Report_resolution($resolution));
			}
			return $report; // если возвращена задача, то она будет зарегистрирована в зависимостях.
		}
		elseif ($this->step===static::STEP_CREATE_ENTITY) // зависимость разрешена - значение, содержащее айди, было получено.
		{
			$id_source_code=$this->value_model_now('id_source');
			$id=$this->entity->value($id_source_code);
			if ($id instanceof Report) return $id;
			if (empty($id)) return $this->advance_step(static::STEP_NO_ID);
			
			$resolution=$this->generate_entity($id);
			return $this->sign_report(new Report_resolution($resolution));
		}
		elseif ($this->step===static::STEP_NO_ID)
		{
			return $this->sign_report(new Report_resolution(null)); // если айди пустой (в частности, null, а не сообщение об ошибке), то результат - null.
		}
	}
	
	public function generate_entity($id)
	{
		if (empty($id)) return;
		if ($this->in_value_model('id_group')) $id_group=$this->value_model_now('id_group');
		else $id_group=null;
		$entity=$this->pool->entity_from_db_id($id, $id_group);
		return $entity;
	}
}

// для значения из класса Value_reference
class Filler_for_entity_reference extends Filler_for_entity
{
	use Task_steps;
	
	const
		STEP_REQUEST_SOURCE_ENTITY=0,
		STEP_REQUEST_SOURCE_VALUE=1,
		STEP_CONNECT=2,
		STEP_FINISH=3;
	
	public
		$source_entity,
		$target_object;
	
	public function run_step()
	{
		if ($this->step===static::STEP_REQUEST_SOURCE_ENTITY)
		{
			$source_entity_code=$this->value_model_now('source_entity');
			$report=$this->entity->request($source_entity_code);
			if ($report instanceof Report_resolution)
			{
				$this->source_entity=$report->resolution;
				return $this->advance_step();
			}
			return $report;
		}
		elseif ($this->step===static::STEP_REQUEST_SOURCE_VALUE)
		{
			$source_entity_code=$this->value_model_now('source_entity');
			$entity=$this->entity->value($source_entity_code);
			if ($entity instanceof Report) return $entity;
			elseif (empty($entity)) return $this->sign_report(new Report_resolution(null)); // если исходная сущность пуста (а не ошибочна), то результат тоже null.
			$this->source_entity=$entity;
			
			$source_code=$this->value_model_now('source_code');
			$report=$entity->value_object_request($source_code);
			
			if ($report instanceof Report_success) return $this->advance_step();
			return $report;
		}
		elseif ($this->step===static::STEP_CONNECT)
		{
			$source_code=$this->value_model_now('source_code');
			$value_object=$this->source_entity->value_object($source_code);
			if ($value_object instanceof Report) return $value_object;
			$this->target_object=$value_object;
			
			$report=$value_object->request();
			if ($report instanceof Report_tasks) return $report;
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			return $this->sign_report(new Report_resolution($this->target_object));
		}
	}
}

class Filler_for_entity_imported extends Filler_for_entity_reference
{
	public function run_step()
	{
		if ($this->step===static::STEP_FINISH)
		{
			$imported_value=$this->target_object->value();
			if ($imported_value instanceof Report) return $imported_value;
			return $this->sign_report(new Report_resolution($imported_value));
		}
		else return parent::run_step();
	}
}

abstract class Fill_entity_producal_value extends Filler_for_entity
{
	public
		$pre_request=null,
		$requested=null;

	public function setup()
	{
		parent::setup();
		if ($this->pre_request===null) return;
		if (!$this->in_value_model('dependancies')) return;
		$this->pre_request=$this->value_model_now('dependancies');
	}
	
	public function progress()
	{
		if ( (empty($this->pre_request)) || ($this->requested===true) ) return $this->resolve();
		
		$pre_request=Task_entity_value_request::for_entity($this->entity);
		$pre_request->to_request=$this->pre_request;
		$this->register_dependancy($pre_request);
		$this->requested=true;
	}
	
	public abstract function resolve();
}

class Fill_entity_proceducal_value_with_callback extends Fill_entity_producal_value
{
	const
		CALL_VALUE=0,
		CALL_VALUESET=1,
		CALL_ENTITY=2,
		CALL_ASPECT=3;
	
	public
		$final=null;
	
	public function fill()
	{
		if ($this->in_value_model('pre_request')) $this->pre_request=$this->value_model_now('pre_request');
		return parent::fill();
	}
	
	public function progress()
	{
		if ($this->final===null) return parent::progress();
		else $this->process_final_task();
	}
	
	public function process_final_task()
	{
		if ($this->resolution!==null)
		{
			$this->finish();
			return;
		}
		
		$report=$this->final->report();
		if ($report instanceof Report_success)
		{
			if ($report instanceof Report_resolution) $this->resolution=$report->resolution;
			$this->finish();
		}
		elseif ($report instanceof Report_impossible)
		{
			$this->impossible($report->errors);
		}
		else die ('BAD CALLBACK REPORT');
	}
	
	public function resolve()
	{
		if ($this->final!==null) return;
		
		$call_data=$this->value_model_now('call');
			
		$args=[];
		if (is_array($call_data))
		{
			$who=array_shift($call_data);
			
			static
				$special=
				[
					'_value'	=>Fill_entity_proceducal_value_with_callback::CALL_VALUE,
					'_set'		=>Fill_entity_proceducal_value_with_callback::CALL_VALUESET,
					'_entity'	=>Fill_entity_proceducal_value_with_callback::CALL_ENTITY,
					'_aspect'	=>Fill_entity_proceducal_value_with_callback::CALL_ASPECT
				];
			
			if  ( (is_string($who)) && (array_key_exists($who, $special)) )
			{
				$special_code=$special[$who];
				if ($special_code===static::CALL_VALUE) $who=$this->value;
				elseif ($special_code===static::CALL_VALUESET) $who=$this->value->master;
				elseif ($special_code===static::CALL_ENTITY) $who=$this->value->master->entity;
				elseif ($special_code===static::CALL_ASPECT)
				{
					$aspect_code=array_shift($call_data);
					$who=$this->value->master->entity->get_aspect($aspect_code);
				}
			}
			$method=array_shift($call_data);
			$args=$call_data;			
			$call_data=[$who, $method];
		}
		
		$result=$call_data(...$args);
		
		$this->process_call_result($result);
	}
	
	public function process_call_result($result)
	{
		if ($result instanceof Report_resolution) $result=$result->resolution;
		
		if ($result instanceof Entity)
		{
			$report=$result->verify(false);
			if ($report instanceof Report_task)
			{
				$this->final=$report->task;
				$this->resolution=$result;
				$report->register_dependancies_for($this);
				return;
			}
			elseif ($report instanceof Report_tasks) die('BAD VERIFY REPORT');
		}
		if (! ($result instanceof Report))
		{
			$this->resolution=$result;
			$this->finish();
		}
		elseif ($result instanceof Report_impossible)
		{
			$this->impossible($result->errors);		
		}
		elseif ($result instanceof Report_task)
		{
			$this->final=$result->task;
			$result->register_dependancies_for($this);
		}
		else die ('UNKNOWN REPORT');
	}
}

class Fill_entity_by_provider extends Fill_entity_proceducal_value_with_callback
{
	public
		$entity;

	public function process_call_result($result)
	{
		if (!($result instanceof Report))
		{
			$this->entity=$this->pool()->entity_from_provider($result, $this->value_model_now('id_group') /* STUB! */ );
			$verify=$this->entity->verify(false);
			parent::process_call_result($verify);
		}
	}
	
	public function process_final_task()
	{
		$report=$this->final->report();
		if ($report instanceof Report_success) $this->finish_with_resolution($this->entity);
		elseif ($report instanceof Report_impossible)
		{
			if ($this->in_value_model('default')) $this->finish_with_resolution($this->value_model_now('default'));
			else $this->impossible($report->errors);
		}
		else die ('BAD CALLBACK REPORT');
	}
}
?>