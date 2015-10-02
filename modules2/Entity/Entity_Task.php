<?
namespace Pokeliga\Entity;

load_debug_concern(__DIR__, 'Task_for_entity');

trait Task_for_entity_methods
{
	public function request_data($codes)
	{
		if (!is_array($codes)) $codes=[$codes];
		$tasks=[];
		foreach ($codes as $code)
		{
			$result=$this->entity->request($code);
			if ($result instanceof \Report_impossible) return $result;
			if ($result instanceof \Report_tasks) $tasks=array_merge($tasks, $result->tasks);
		}
		if (empty($tasks)) return new \Report_success(, $this);
		return new \Report_tasks($tasks, $this);
	}
	
	public function pre_request($codes, $entity=null)
	{
		if ($entity===null) $entity=$this->entity;
		$task=Task_entity_value_request::to_request($entity, $codes);
		return $task;
	}
}

// для задач, работающих с той или иной сущностью.
abstract class Task_for_entity extends \Pokeliga\Task\Task
{
	use Task_for_entity_methods;
	
	public
		$entity,
		$args=[];
	
	public static function for_entity($entity, $args=[])
	{
		$task=new static();
		$task->setup($entity, $args);
		return $task;
	}
	
	public function setup($entity, $args=[])
	{
		$this->entity=$entity;
		$this->args=$args;
		$this->apply_arguments();
	}
	
	public function apply_arguments() { }
	
	public function pool()
	{
		return $this->entity->pool;
	}
}

// это задача собирает запросы, необходимые для сохранения данных сущности, и выполняет их. предполагает уже подтверждённую сущность, потому что для новую задача другая, в неподтверждённую не могли быть внесены изменения, а виртуальную не сохраняют.
class Task_save_entity extends Task_for_entity
{
	use \Pokeliga\Task\Task_steps;
	
	const
		STEP_VERIFY_ENTITY=0,
		STEP_COLLECT_KEEPERS=1,	// собирает киперы значений, которые нужно сохранить.
		STEP_FILL=2,			// заполняет незаполненные значения.
		STEP_VALIDATE=3,		// проверяет сохраняемые значения и сущность в целом
		STEP_SAVE=4,			// сохранение.
		STEP_CACHE_RESET=5,		// сбрасывает связанный кэш.
		STEP_FINISH=6;			// сюда скрипт заходит только в случае удачного выполнения.
	
	public
		$request_data=[],
		$validation=null;
	
	public function run_step()
	{
		if ($this->step===static::STEP_VERIFY_ENTITY)
		{
			if ($this->entity->is_to_verify())
			{
				$report=$this->entity->verify(false);
				if ($report instanceof \Report_successful) return $this->advance_step();
				return $report;
			}
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_COLLECT_KEEPERS)
		{
			// этот блок пока не учитывает значения, которые по умолчанию отсутствуют. хотя их могут учесть их киперы.
			// FIXME: кроме того, этот блок сам вычисляет кипера и разбирает параметры значения, хотя скорее всего либо оно должно само это делать, либо датасет за  него.
			
			if ($this->entity->state===Entity::STATE_FAILED) return new \Report_impossible('saving_failed_entity', $this);
			
			$type=$this->entity->type;
			foreach ($this->entity->dataset->values as $value)
			{
				// echo 'CODE '.$value->code.': save '.(int)$value->save_changes.', state '.$value->state.', content '; vdump($value->content);
				if (!$value->save_changes) continue;
				if ( ($value->in_value_model('keeper')) && ($value->value_model_now('keeper')===false) ) continue;
				$keeper=$value->get_keeper_task($value);
				if (empty($keeper)) continue;
				$this->keepers[]=$keeper;
			}
			if (empty($this->keepers)) return new \Report_success(, $this);
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_FILL)
		{		
			$fillers=[];
			$keepers=$this->get_relevant_keepers();
			foreach ($keepers as $keeper)
			{
				// vdump(get_class($this->entity->type).'::'.$keeper->value->code);
				$value=$keeper->value;
				$report=$value->request();
				// vdump($report);
				if ($report instanceof \Report_impossible) return $report;
				if ($report instanceof \Report_tasks) $fillers=array_merge($fillers, $report->tasks);
			}
			if (!empty($fillers)) return new \Report_tasks($fillers, $this);
			return $this->advance_step();
		}
		// строго говоря, валидаторы и так получают значения прежде, чем их проверить. но кажется более правильным и безопасным сначала получить данные, а потом - сохранить.
		elseif ($this->step===static::STEP_VALIDATE)
		{
			$validators=[];
			foreach ($this->entity->dataset->values as $value)
			{
				if (!$value->save_changes) continue; // важно: этот параметр будет выставлен "true" даже у значений, которые не должны сохраняться в БД, однако потеряли актуальность из-за изменившихся значений, которые БУДУТ сохранены в БД. их всё равно нужно проверить на действительность для того, чтобы не сохранить значения, которые приведут в негодность другие.
				$v=$value->is_valid(false);
				if ($v===false) return new \Report_impossible('invalid_value: '.$value->code, $this);
				elseif ($v instanceof \Report_tasks) $validators=array_merge($validators, $v->tasks);
				elseif ($v!==true) { vdump('BAD VALIDATION STATE 2'); vdump($v); vdump('CODE '.$value->code); exit; }
			}
			if (empty($validators))
			{
				$this->validation=true;
				return $this->advance_step();
			}
			else $plan=\Pokeliga\Data\ValidationPlan::from_validators($validators);
			$this->validation=$plan;
			if ($plan instanceof \Pokeliga\Task\Task) return new \Report_task($plan, $this);
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_SAVE)
		{
			if ($this->validation===null) die ('BAD VALIDATION');
			if
			(
				($this->validation!==true) &&
				!( (is_object($this->validation)) && ($this->validation->successful()))
			)
				return new \Report_impossible('invalid_entity', $this);	
			
			$request_data=[];
			foreach ($this->keepers as $keeper)
			{
				$keeper->save($request_data);
			}

			$requests=[];
			foreach ($request_data as $table=>$fields)
			{
				$request=$this->create_request($fields, $table);
				if (empty($request)) continue;
				if (is_array($request)) $requests=array_merge($requests, $request);
				else $requests[]=$request;
			}
			if (empty($requests)) return $this->advance_step();
			$process=new \Pokeliga\Task\Process_collection($requests); // чтобы запросы выполнялись строго один за другим.
			return new \Report_task($process, $this);
		}
		elseif ($this->step===static::STEP_CACHE_RESET)
		{
			$task=$this->entity->task_request('postedit_reset_cache');
			// ничего, если задачи не предусмотрено - это не заставит сохранение прекратиться.
			if ($task instanceof \Report_final) return $this->advance_step();
			if ($task instanceof \Report_tasks) return $task;
			die('BAD TASK REPORT');
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			foreach ($this->entity->dataset->values as $code=>$value)
			{
				$value->save_changes=false;
			}
			return new \Report_success(, $this);
		}
	}
	
	public function get_relevant_keepers()
	{
		return $this->keepers;
	}
	
	public function create_request($fields, $table)
	{
		if ($table===Keeper_var::VAR_TABLE) return $this->create_var_request($fields); // FIXME! Пока нет времени делать как следует.
		$query=
		[
			'action'=>'update',
			'table'=>$table,
			'set'=>$fields,
			'where'=>['id'=>$this->entity->db_id]
		];
		if (Retriever()->is_common_table($table)) $query['where']['id_group']=$this->entity->id_group;
		$request=\Pokeliga\Retriever\Request_update::from_query($query);
		return $request;
	}
	
	// FIXME! Пока нет времени делать как следует.
	public function create_var_request($fields)
	{
		$result=[];
		if (array_key_exists('__delete', $fields))
		{
			$request=
			[
				'action'=>'delete',
				'table'=>Keeper_var::VAR_TABLE,
				'where'=>['id'=>$this->db_id(), 'id_group'=>$this->entity->id_group, 'code'=>$fields['__delete']]
			];
			$result[]=\Pokeliga\Retriever\Request_delete::from_query($request);
		}
		
		$more=false;
		$request=
		[
			'action'=>'replace',
			'table'=>Keeper_var::VAR_TABLE,
			'values'=>[]
		];
		foreach ($fields as $field_code=>$data)
		{
			if ($field_code==='__delete') continue;
			$more=true;
			$base=['id'=>$this->db_id(), 'id_group'=>$this->entity->id_group, 'code'=>$field_code, 'index'=>0, 'number'=>null, 'str'=>null];
			if ((is_array($data)) && (is_numeric(key($data))))
			{
				foreach ($data as $index=>$subdata)
				{
					$request['values'][]=array_merge($base, ['index'=>$index], $subdata);
				}
			}
			else $request['values'][]=array_merge($base, $data);
		}
		if ($more) $result[]=\Pokeliga\Retriever\Request_insert::from_query($request);
		
		return $result;
	}
	
	public function db_id()
	{
		return $this->entity->db_id;
	}
	
	public function completed_dependancy($task, $identifier=null)
	{
		if ($task->failed()) { vdump($task); $this->impossible('subtask_failed'); }
	}
}

// сохранение данных новой сущности.
class Task_save_new_entity extends Task_save_entity
{
	const
		// шаг подтверждения сущности не требуется.
		STEP_GET_ASPECTS=0,	// нужно получить все аспекты, чтобы сделалась правильная модель. здесь ведь мы спрашиваем модель у датасета.
		STEP_COLLECT_KEEPERS=1,	// собирает киперы значений, которые нужно сохранить.
		STEP_FILL=2,		// заполняет незаполненные значения.
		STEP_VALIDATE=3,	// проверяет сохраняемые значения и сущность в целом
		STEP_RECEIVE_ID=4,	// новая сущность получает айди.
		STEP_SAVE=5,	// сохраняются остальные данные.
		STEP_CACHE_RESET=6, // сброс кэша на случай, если этот кэш касается не только самой сущности, но и тех, с которой она связана.
		STEP_FINISH=7;		// сюда скрипт заходит только в случае удачного выполнения.
	
	public
		$received_id,
		$basic_keeper=null; // это кипер, отвечающий за поле 'id' базового аспекта.
	
	public function run_step()
	{
		// отличия от родительского класса в этом шаге кажутся слишком частными, чтобы выделить их и унаследовать красиво.
		if ($this->step===static::STEP_GET_ASPECTS)
		{
			$type=$this->entity->type;
			$tasks=[];
			foreach ($type::$base_aspects as $aspect_code=>$basic_aspect)
			{
				$aspect=$this->entity->get_aspect($aspect_code, false);
				if ($aspect instanceof \Report_impossible) return $aspect;
				elseif ($aspect instanceof \Report_tasks) $tasks=array_merge($tasks, $aspect->tasks);
			}
			
			if (empty($tasks)) return $this->advance_step();
			return new \Report_tasks($tasks, $this);
		}
		elseif ($this->step===static::STEP_COLLECT_KEEPERS)
		{
			// этот блок пока не учитывает значения, которые по умолчанию отсутствуют. хотя их могут учесть их киперы.
			// FIXME: кроме того, этот блок сам вычисляет кипера и разбирает параметры значения, хотя скорее всего либо оно должно само это делать, либо датасет за  него.
			
			foreach ($this->entity->dataset->model as $code=>$model)
			{				
				$value=$this->entity->dataset->produce_value($code);
				$keeper=$value->get_keeper_task();
				if (empty($keeper)) continue;
				
				$keeper=Keeper::for_value($value, $keeper_code);
				if ($code==='id') $this->basic_keeper=$keeper;
				else $this->keepers[]=$keeper;
			}
			if ($this->basic_keeper===null) return new \Report_impossible('no_basic_keeper', $this);
			return $this->advance_step();
		}
		// elseif ($this->step===static::STEP_FILL) // ...
		// elseif ($this->step===static::STEP_VALIDATE) // ...
		elseif ($this->step===static::STEP_RECEIVE_ID)
		{
			// STUB: пока правильно работает только с хранением по обычному типу - в виде поля записи в специальной таблице.
			$keepers=$this->get_relevant_keepers();
			$request_data=[];
			foreach ($keepers as $keeper)
			{
				if (duck_instanceof($keeper->value, '\Pokeliga\Entity\Value_own_id')) continue;
				$keeper->save($request_data);
			}
			if (count($request_data)>1) die ('BAD BASIC REQUEST');
			$basic_table=$this->basic_keeper->table();
			$request=$this->create_request($request_data[$basic_table], $basic_table);
			return new \Report_task($request, $this);
		}
		// elseif ($this->step===static::STEP_SAVE) // ...
		elseif ($this->step===static::STEP_FINISH)
		{
			$this->entity->receive_db_id($this->received_id);
			return parent::run_step();
		}
		else return parent::run_step();
	}
	
	public function get_relevant_keepers()
	{
		if ($this->step===static::STEP_RECEIVE_ID)
		{
			$more_keepers=[];
			$relevant_keepers=[$this->basic_keeper];
			$basic_table=$this->basic_keeper->table();
			foreach ($this->keepers as $key=>$keeper)
			{
				$relevant=false;
				if (!($keeper instanceof Keeper_db))
				{
					$more_keepers[]=$keeper;
					// на этом этапе задача - сделать базовую запись, а не сохранить экзотические данные.
					continue; 
				}
				if ( ($keeper===$this->basic_keeper) && ($keeper->value->content()===null) ) continue; // такой айди присваивается после сохранения.
				if ($keeper->table()===$basic_table) $relevant_keepers[]=$keeper;
				else $more_keepers[]=$keeper;
			}
			$this->keepers=$more_keepers;
			return $relevant_keepers;
		}
		return parent::get_relevant_keepers();
	}
	
	public function create_request($fields, $table)
	{
		if ($table===Keeper_var::VAR_TABLE) return parent::create_request($fields, $table); // FIXME! пока нет времени делать по-правильному.
		if ($this->step===static::STEP_SAVE)
		{
			$fields['id']=$this->received_id;
			if (Retriever()->is_common_table($table)) $fields['id_group']=$this->entity->id_group;
		}

		$query=
		[
			'action'=>'insert',
			'table'=>$table,
			'value'=>$fields
		];
		$request=\Pokeliga\Retriever\Request_insert::from_query($query);
		return $request;
	}
	
	public function db_id()
	{
		return $this->received_id;
	}
	
	public function completed_dependancy($task, $identifier=null)
	{
		if ( ($this->step===static::STEP_RECEIVE_ID) && ($task instanceof \Pokeliga\Retriever\Request) )
		{
			$this->received_id=$task->insert_id;
		}
	}
}

class Task_save_generic_links extends \Pokeliga\Task\Task
{
	use \Pokeliga\Task\Task_inherit_dependancy_failure;
	
	public
		$relation,
		$linked_id_group=null,
		$host_id_group=null,
		
		$position=\Pokeliga\Retriever\Request_generic_links::FROM_OBJECT,
		$linked_type_field,
		$host_type_field,
		$linked_id_field,
		$host_id_field,
		
		$setup=false,
		$linked,
		$host,
		$request;
	
	public static function for_linkage($position, $host_id_group, $linked_id_group, $relation)
	{
		$task=new static();
		$task->position=$position;
		$task->host_id_group=$host_id_group;
		$task->linked_id_group=$linked_id_group;
		$task->relation=$relation;
		return $task;
	}
	
	public function progress()
	{
		if (!$this->setup)
		{
			if ($this->position===\Pokeliga\Retriever\Request_generic_links::FROM_OBJECT)
			{
				$this->host_type_field='entity1_type';
				$this->host_id_field='entity1_id';
				$this->linked_type_field='entity2_type';
				$this->linked_id_field='entity2_id';
			}
			else
			{
				$this->host_type_field='entity2_type';
				$this->host_id_field='entity2_id';
				$this->linked_type_field='entity1_type';
				$this->linked_id_field='entity1_id';
			}
			$this->setup=true;
		}
		if ($this->request===null)
		{
			$query=
			[
				'action'=>'delete',
				'table'=>'info_links',
				'where'=>[$this->host_type_field=>$this->host_id_group, $this->host_id_field=>$this->host->db_id, 'relation'=>$this->relation]
			];
		}
		elseif ($this->request instanceof \Pokeliga\Retriever\Request_delete) // не работает с тикетами! FIXME?
		{
			if (empty($this->linked))
			{
				$this->finish();
				return;
			}
			$query=
			[
				'action'=>'insert',
				'table'=>'info_links',
				'values'=>[]
			];
			foreach ($this->linked as $id)
			{
				if ($id instanceof Entity) $id=$id->db_id;
				$query['values'][]=[$this->linked_type_field=>$this->linked_id_group, $this->linked_id_field=>$id, $this->host_type_field=>$this->host_id_group, $this->host_id_field=>$this->host->db_id, 'relation'=>$this->relation];
			}
		}
		elseif ($this->request instanceof \Pokeliga\Retriever\Request_insert) return $this->finish(); // не работает с тикетами!
		
		$this->request=\Pokeliga\Retriever\Request_single::from_query($query);
		$this->register_dependancy($this->request);
	}
}

class Task_resolve_entity_call extends Task_for_entity implements \Pokeliga\Task\Task_proxy
{
	use \Pokeliga\Task\Task_steps, Logger_Task_for_entity;
	
	const
		STEP_VERIFY_ENTITY=0,	// запрос на подтверждение сущности.
		STEP_GET_ASPECT=1,		// получение аспекта, если требуется.
		STEP_CALL=2,			// собственно запрос.
		STEP_FINISH=3;			// дополнтельный этап на случай, если запрос создал зависимости.
	
	public
		$storable_response_key;
	
	public function resolve()
	{
		return new \Report_task($this, $this);
	}
	
	public function record_analysis($analysis)
	{
		foreach ($analysis as $name=>$value)
		{
			$this->$name=$value;
		}
	}
	
	public function requires_verification()
	{
		$type=$this->entity->type;
		return !array_key_exists($this->name, $type::$no_verify);
	}
	
	public function calls_dataset()
	{
		return $this->default_mode===EntityType::VALUE_NAME;
	}
	
	public function calls_type()
	{
		return $this->aspect_code===false;
	}
	
	public function requires_aspect()
	{
		return !$this->calls_dataset();
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_VERIFY_ENTITY)
		{		
			if (!$this->entity->is_to_verify()) return $this->advance_step();
			if (!$this->requires_verification()) return $this->advance_step();
			
			$report=$this->entity->verify(false);
			if ($report instanceof \Report_final) return $this->advance_step();
			return $report;
		}
		// STAB: вызовы могут проходить и по сущностям, которые не были найдены в БД, например, чтобы получить по запросу 'profile' шаблон "профиль ненайденного игрока".
		elseif ($this->step===static::STEP_GET_ASPECT)
		{
			if ($this->aspect_code===false) return $this->advance_step();
			// аспект необходимо получить даже перед обращением к датасету, поскольку установление аспекта влияет на модель датасета.
			$aspect=$this->entity->get_aspect($this->aspect_code, false);
			if ($aspect instanceof Aspect) return $this->advance_step();
			return $aspect; // \Report_impossible, \Report_tasks
		}
		elseif ($this->step===static::STEP_CALL)
		{
			if ($this->calls_type()) $callback=[$this->entity->type, $this->name];
			elseif ($this->calls_dataset()) $callback=[$this->entity->dataset, $this->name];
			else
			{
				$aspect=$this->entity->get_aspect($this->aspect_code);
				$callback=[$aspect, $this->name];
			}
			$result=$callback (...$this->args);
			if (! ($result instanceof \Report)) $this->make_calls('proxy_resolved', $result);
			
			if ( ($result instanceof \Pokeliga\Task\Task) && ($this->mode!==EntityType::PAGE_NAME) ) return new \Report_task($result, $this);
			if ( ($result instanceof \Report_impossible) && ($this->mode===EntityType::PAGE_NAME)) return new \Report_resolution(null, $this);
			if ( !($result instanceof \Report)) return new \Report_resolution($result, $this); // единственный случай, когда возвращается не отчёт - это возвращение готового значения.
			return $result;
		}
	}
	
	public function finish($success=true)
	{
		$this->log('resolved_call');
		parent::finish($success);
	}
	
	public function completed_dependancy($task, $identifier=null)
	{
		if ($this->step===static::STEP_FINISH)
		{
			if ($task->failed()) $this->impossible($task);
			else $this->finish_with_resolution($task->resolution);
		}
	}
}


// подтверждает сущность по указанному в ней id. Переводит её из состояния PROVIDED_ID в VERIFIED_ID.
class Task_for_entity_verify_id extends Task_for_entity
{
	public
		$table=null,
		$id=null;
	
	public function prepare()
	{
		if ($this->id!==null) return;
		
		$this->id=$this->entity->db_id;
		$type=$this->entity->type;
		$basic_aspect=$type::$base_aspects['basic'];
		$this->table=$basic_aspect::$default_table; // STUB?
	}

	public function progress()
	{
		$this->prepare();
		$data=\Pokeliga\Retriever\Request_by_id::instance($this->table)->get_data_set($this->id);
		if ($data instanceof \Report_impossible)
		{
			$this->entity->state=Entity::STATE_FAILED;
			$this->impossible('unexistent');
		}
		elseif ($data instanceof \Report_tasks) $data->register_dependancies_for($this);
		else
		{
			$this->entity->verified();
			$this->finish();		
		}
	}
}

// в результате завершения этой задачи поля сущности должны быть заполнены. Хотя бы одна ошибка означает ошибку задачи.
class Task_entity_value_request extends Task_for_entity
{
	use \Pokeliga\Task\Task_inherit_dependancies_success, \Pokeliga\Task\Task_inherit_dependancy_failure;
	
	public
		$to_request=[];
	
	public static function to_request($entity, $to_request)
	{
		$task=static::for_entity($entity);
		if (!is_array($to_request)) $to_request=[$to_request];
		$task->to_request=$to_request;
		return $task;
	}
	
	public function progress()
	{
		if (empty($this->entity))
		{
			$this->impossible('no_entity');
			return;
		}
		foreach ($this->to_request as $code)
		{
			if (is_array($code))
			{
				$task=new \Pokeliga\Data\Task_resolve_value_track($code, $this->entity);
				$this->register_dependancy($task);
				continue;
			}
			$report=$this->entity->request($code);
			if ($report instanceof \Report_tasks) $this->register_dependancies($report->tasks);
		}
		if (empty($this->subtasks)) $this->finish();
	}
}


abstract class Task_determine_aspect extends Task_for_entity
{
	public
		$aspect_code,
		$task_model=[];
	
	public static function aspect_for_entity($code, $entity, $model=[])
	{
		$task=static::for_entity($entity);
		$task->aspect_code=$code;
		$task->entity->aspect_determinators[$code]=$task;
		$task->task_model=$model;
		return $task;
	}
	
	public function finish($success=true)
	{
		if ($success)
		{
			$previous_aspect=null;
			if (array_key_exists($this->aspect_code, $this->entity->aspects)) $previous_aspect=$this->entity->aspects[$this->aspect_code];
			if (is_object($previous_aspect)) $previous_aspect=get_class($previous_aspect);
			if ($previous_aspect!==$this->resolution)
			{
				$this->entity->type->record_aspect_dependancy($this->aspect_code, $this->requested); // STUB!
				$this->entity->aspects[$this->aspect_code]=$this->resolution;
				$this->modify_model($previous_aspect);
			}
			unset($this->entity->aspect_determinators[$this->aspect_code]);
			$this->resolution=$this->entity->get_aspect($this->aspect_code);
		}
		return parent::finish($success);
	}
	
	public function modify_model($previous_aspect)
	{
		$aspect_class=$this->resolution;
		$type=$this->entity->type;
		$type::init_aspect($this->aspect_code, $aspect_class);
		
		foreach ($aspect_class::$common_model as $code=>$data)
		{
			$this->entity->dataset->change_model($code, $data);
		}
		
		/*
		if
		(
			(is_string($previous_aspect)) &&
			(property_exists('modify_model', $previous_aspect)) &&
			(count($diff=array_diff($previous_aspect::$modify_model, $aspect_class::$modify_model))>0)
			// если у прошлого аспекта тоже были модификации других значений, чем у текущего.
		)
		{
			$type=$this->entity->type;
			foreach ($diff as $code=>$data)
			{
				$this->entity->dataset->change_model($code, $type::$data_model); // возвращение к стандарту
			}
		}
		*/
	}
}

class Task_determine_aspect_by_param extends Task_determine_aspect
{	
	public function progress()
	{
		$param=$this->task_model['param'];
		$this->requested=[$param];
		$result=$this->entity->request($param);
		if ($result instanceof \Report_resolution)
		{
			$result=$result->resolution;
			$resolution=null;
			if (array_key_exists($result, $this->task_model['by_value'])) $resolution=$this->task_model['by_value'][$result];
			elseif
			(
				(array_key_exists('default_aspect_base', $this->task_model) or array_key_exists('default_aspect_suffix', $this->task_model))
				and class_exists
				(
					$class=
						(empty($base=$this->task_model['default_aspect_base']) ? '' : $base)
						.$result.
						(empty($suff=$this->task_model['default_aspect_suffix']) ? '' : $suff)
				)
			)
				$resolution=$class;
			elseif (array_key_exists('default_aspect', $this->task_model)) $resolution=$this->task_model['default_aspect'];
			if ($resolution===null) $this->impossible('bad_aspect_param');
			else
			{
				$this->resolution=$resolution;
				$this->finish();
			}
		}
		elseif ($result instanceof \Report_tasks) $result->register_dependancies_for($this);
		elseif ($result instanceof \Report_impossible) $this->impossible('no_aspect_param');
	}
}

// опрашивает аспекты на предмет разрешения.
class Task_calc_user_right extends Task_for_entity
{
	use \Pokeliga\Task\Task_steps;
	
	const
		STEP_GATHER_ASPECTS=0,
		STEP_PREPARE_RIGHTS=1,
		STEP_GATHER_RIGHTS=2,
		STEP_ANALYZE_RIGHTS=3;

	public
		$right,
		$args=[],
		$aspects=[],
		$results=[]; // сохраняет задачи и результаты.

	public static function for_user_from_args($entity, $args=[])
	{
		$task=static::for_entity($entity, $args);
		return $task;
	}
	
	public static function for_current_user_from_args($entity, $args=[])
	{
		$user=User::current_user();
		$args=array_merge([$args[0], $user], array_slice($args, 2));
		return static::for_user_from_args($entity, $args);
	}
	
	public function apply_arguments()
	{
		// нужно чтобы получить данные о праве, а пользователь и так будет передан куда надо в составе аргументов.
		$this->right=$this->args[0];
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_GATHER_ASPECTS)
		{
			$result=$this->gather_aspects(false);
			if ($result===true) return $this->advance_step();
			return $result;
		}
		elseif ($this->step===static::STEP_PREPARE_RIGHTS)
		{
			$result=$this->gather_aspects(true);
			if ($result instanceof \Report_impossible) return $result;
			
			$tasks=[];
			foreach ($this->aspects as $code=>$aspect)
			{
				$result=$aspect->supply_right(...$this->args);
				if ($result instanceof \Report_impossible) return $result; // если происходит ошибка и право выяснить невозможно, это считается за отказ.
				elseif ($result===EntityType::RIGHT_FINAL_ALLOW) return new \Report_resolution(true, $this);
				elseif ($result===EntityType::RIGHT_FINAL_DENY) return new \Report_resolution(false, $this);
				elseif ($result instanceof \Report_task)
				{
					$tasks[]=$result->task;
					$this->rights[$code]=$result->task;
				}
				elseif ($result instanceof \Report_tasks) { vdump($result); die('BAD RIGHT TASKS'); }
				else $this->rights[$code]=$result;
			}
			if (empty($tasks)) return $this->advance_step(static::STEP_ANALYZE_RIGHTS);
			return new \Report_tasks($tasks, $this);
		}
		elseif ($this->step===static::STEP_GATHER_RIGHTS)
		{
			foreach ($this->rights as &$right_data)
			{
				if ($right_data instanceof \Pokeliga\Task\Task)
				{
					if ($right_data->failed()) return $right_data->report();
					$right_data=$right_data->resolution;
				}
			}
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_ANALYZE_RIGHTS)
		{
			$allow=null;
			foreach ($this->rights as $right_data)
			{
				if ($right_data===EntityType::RIGHT_FINAL_ALLOW) return new \Report_resolution(true, $this);
				if ($right_data===EntityType::RIGHT_FINAL_DENY) return new \Report_resolution(false, $this);
				if ($right_data===EntityType::RIGHT_WEAK_DENY) $allow=false;
				if ( ($right_data===EntityType::RIGHT_WEAK_ALLOW) && ($allow===null) ) $allow=true;
			}
			
			if ($allow===true) return new \Report_resolution(true, $this);
			return new \Report_resolution(false, $this);
		}
	}
	
	public function gather_aspects($now=true)
	{
		$type=$this->entity->type;
		$aspect_codes=$type::$rights[$this->right];
			
		$tasks=[];
		foreach ($aspect_codes as $code)
		{
			$report=$this->entity->get_aspect($code, $now);
			if ($report instanceof \Report_impossible) return $report;
			if ($report instanceof \Report_tasks) $tasks=array_merge($tasks, $report->tasks);	// этот ответ возможен только при $now==false.
			$this->aspects[$code]=$report; // если не эти два отчёта, то аспект.
		}
		 
		if (empty($tasks)) return true;
		return new \Report_tasks($tasks, $this);
	}
}

// спрашивает конкретный аспект на предмет разрешения.
class Task_calc_aspect_right extends Task_for_entity
{
	public
		$aspect,
		$user,
		$right,
		$args,
		$pre_request=[],
		$pre_request_from_user=[],
		$requested=null;

	public static function for_right($args, $aspect)
	{
		$task=static::for_entity($aspect->entity);
		$task->aspect=$aspect;
		$task->args=$args;
		$task->right=reset($args);
		$task->user=next($args);
		if ( (!empty($task->user)) && (!($task->user instanceof Entity)) ) die('BAD USER');
		return $task;
	}
		
	public static function from_data($data, $args, $aspect)
	{
		$task=static::for_right($args, $aspect);
		$task->apply_data($data);
		return $task;
	}
	
	public function apply_data($data)
	{
		if (array_key_exists('pre_request', $data)) $this->pre_request=$data['pre_request'];
		if (array_key_exists('pre_request_from_user', $data)) $this->pre_request_from_user=$data['pre_request_from_user'];
	}
	
	public function progress()
	{
		if ($this->requested===true)
		{
			$this->resolve();
			return;
		}
		if ( (empty($this->pre_request)) && (empty($this->pre_request_from_user)) )
		{
			$this->resolve();
			return;
		}
		
		if (!empty($this->pre_request))
		{
			$task=$this->pre_request($this->pre_request);
			$this->register_dependancy($task);
		}
		
		if ( (!empty($this->pre_request_from_user)) && (!empty($this->user)) )
		{
			$task=$this->pre_request($this->pre_request_from_user, $this->user);
			$this->register_dependancy($task);
		}
		$this->requested=true;
	}
	
	public function resolve()
	{
		$result=$this->aspect->has_right(...$this->args);
		if ($result instanceof \Report_impossible)
		{
			$this->impossible($result);
			return;
		}
		if ($result instanceof \Report) die('BAD RIGHT REPORT');
		$this->finish_with_resolution($result);
	}
}

?>