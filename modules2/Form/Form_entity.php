<?
interface FieldSet_provides_entity
{
	public function entity();
}

// FIX: возможно, следует сделать это трейтом или же форму трейтом, потому что потенциально редактировать сущность может как целая форма, так и отдельные наборы полей.
trait FieldSet_about_entity
{
	public
		$entity=null,
		$id_group=null,
		$entity_fields=null,
		$target_page='profile_page';
		
	public function make_tracks()
	{
		parent::make_tracks();
		$this->tracks[static::ENTITY_TRACK]=$this->entity();
	}
	
	public function create_valid_processor()
	{
		$entity=$this->entity();
		if ($entity instanceof Report_impossible) return $entity;
		$entity=$this->prepare_entity($entity);
		if ($entity instanceof Report) return $entity;
		
		$report=$entity->save();
		if ($report instanceof Report_final) return $report;
		elseif ($report instanceof Report_task) return $report->task;
		else die('BAD TASK');
	}
	
	public function entity()
	{
		if ($this->entity!==null) return $this->entity;
		$this->entity=$this->create_entity();
		return $this->entity;
	}
	
	public abstract function create_entity();
	
	public function entity_fields()
	{
		if ($this->entity_fields===null) return array_keys($this->model);
		return $this->entity_fields;
	}
	
	// следует вызывать только при валидности формы, потому что здесь она дополнительно не проверяется.
	public function prepare_entity($entity)
	{
		$relevant_fields=$this->entity_fields();
		foreach ($relevant_fields as $code)
		{
			$model=$this->model($code);
			if (!array_key_exists('for_entity', $model)) continue;
			$value=$this->produce_value($code);
			$content=$value->content();
			if ($content instanceof Report_impossible) continue; // закончи и не нужно было: к данномому моменту все проверки уже выполнены, и если мы дошли сюда, то всё нормально.
			
			if ( (array_key_exists('convert', $model)) && (array_key_exists('for_entity', $model['convert'])) )
			{
				$args=$model['convert']['for_entity'];
				$keyword=array_shift($args);
				$converter=Converter::with_args($keyword, $content, $args);
				$converter->complete();	// конвертеры обычно не требуют запросов в БД, так что ничего страшного, что они выполняются накатом.
				if ($converter->failed()) continue;
				$content=$converter->resolution;
			}
			
			if ($model['for_entity']===true) $entity_field=$code;
			else $entity_field=$model['for_entity'];
			$entity->set($entity_field, $content);
		}
		return $entity;
	}
}

// последщие черты применяются также на FieldSet'ах - главное чтобы у родителя был применён трейт FieldSet_about_entity. Именно у родителя, иначе возникнут конфликты из-за пересечения. Обычно делается абстрактная форма о такой-то сущности, а затем унаследованные - новая и редактировать.
trait Form_new_entity
{
	public function create_entity()
	{
		// FIX: следует предусмотреть загрузку значений по умолчанию из новой сущности.
		return $this->pool()->new_entity($this->id_group);
	}
}

trait Form_edit_entity
{
	public function create_entity()
	{
		$id=$this->entity_db_id();
		if ($id instanceof Report) return $id;
		return $this->pool()->entity_from_db_id($id, $this->id_group);
	}
	
	public abstract function entity_db_id();
	
	// FIX! запросы не ставятся в очередь, так что при показе нескольких форм будут выполняться не оптимально.
	public function fill_defaults($codes=null, $rewrite=true)
	{
		$class=static::DEFAULTS_FROM_ENTITY_CLASS;
		$task=$class::for_entity($this->entity());
		$task->field=$this;
		$task->codes=$codes;
		$task->rewrite=$rewrite;
		return $this->sign_report(new Report_task($task));
	}
	
	public function session_name()
	{
		return parent::session_name().$this->entity_db_id();
	}
	
	public function create_valid_processor()
	{
		$task=Task_save_entity_from_fieldset::for_fieldset($this);
		return $task;
	}
}

trait Form_edit_entity_simple
{
	use Form_edit_entity;
	
	public
		$entity_id;
	
	public function entity_db_id()
	{
		if ($this->entity_id!==null) return $this->entity_id;
		if (array_key_exists('entity_id', $this->super_model)) return $this->super_model['entity_id'];
		if ($this->mode===static::MODE_PROCESS)
		{
			$this->input_value('id');
			return $this->content_of('id');
		}
		else return $this->page->entity_id(); // STUB
	}
}

abstract class Form_entity extends Form implements FieldSet_provides_entity
{
	use FieldSet_about_entity;

	// константы приходится продублировать, потому что черта не может их навешивать, а интерфейс запрещает их менять у наследников. возможно, впрочем, что нет необходимости делать это константами, а можно просто статичными или даже обычными переменными.
	const
		ENTITY_TRACK='master',
		DEFAULTS_FROM_ENTITY_CLASS='Task_fill_fieldset_from_entity';
	
	public
		// этот же ключ следует предусмотреть в формах, содержащих FieldSet_entity; или же предусмотреть возможным его указание в полях ввода, дочерних к FieldSet_entity.
		$template_db_key='form.standard_no_autocomplete';
		
	public function redirect_successful()
	{
		Router()->redirect($this->entity()->url($this->target_page));
	}
}

abstract class FieldSet_entity extends FieldSet implements FieldSet_provides_entity
{
	use FieldSet_about_entity;
	
	const
		ENTITY_TRACK='master',
		DEFAULTS_FROM_ENTITY_CLASS='Task_fill_fieldset_from_entity';
}

class Template_entity_form extends Template_from_db
{		
	public function entity()
	{
		return $this->form->entity;
	}
}

// FIX: возможно, эту задачу лучше посвятить исключительно сохранению сущностей, не касаясь форм? ведь форма только подготавливает значение, это может сделать и собственно форма, а шаги со сравнением можно включить в Task_save_entity.
class Task_save_entity_from_fieldset extends Task_for_fieldset
{
	use Task_steps;
	
	const
		STEP_PREPARE_ENTITY=0,
		STEP_FILL_TEST_ENTITY=1,
		STEP_MATCH_ENTITY=2,
		STEP_SAVE_ENTITY=3,
		STEP_FINISH=4;
	
	public
		$entity,
		$changed_values=[],
		$test_entity,
		$test_pool,
		$final;
	
	public function run_step()
	{
		if ($this->step===static::STEP_PREPARE_ENTITY)
		{
			if ($this->entity!==null) return $this->advance_step();
			$entity=$this->inputset->entity();
			if ($entity instanceof Report_impossible) return $entity;
			$entity=$this->inputset->prepare_entity($entity);
			if ($entity instanceof Report) return $entity;
			
			$this->entity=$entity;
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_FILL_TEST_ENTITY)
		{
			$this->test_pool=new EntityPool(EntityPool::MODE_READ_ONLY);
			$this->test_entity=$this->test_pool->entity_from_db_id($this->entity->db_id, $this->entity->id_group);
			
			$tasks=[];
			foreach ($this->entity->dataset->values as $value)
			{
				if (!$value->save_changes) continue;
				if ( ($value->in_value_model('keeper')) && ($value->value_model_now('keeper')===false)) continue; // FIX: исключает не все случаи, когда значение не сохраняется. и вообще должно делаться в едином месте.
				$this->changed_values[]=$value->code;
				
				$result=$this->test_entity->request($value->code);
				if ($result instanceof Report_impossible) return $result;
				if ($result instanceof Report_tasks) $tasks=array_merge($tasks, $result->tasks);
			}
			if (empty($tasks)) return $this->advance_step();
			return $this->sign_report(new Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_MATCH_ENTITY)
		{
			foreach ($this->changed_values as $code)
			{
				$test_value=$this->test_entity->value($code);
				if ($test_value instanceof Report_impossible) return $test_value;
				$matched_value=$this->entity->value($code);
				if ($test_value===$matched_value) $this->entity->dataset->produce_value($code)->save_changes=false;
				// FIX: можно было бы использовать value_object, но такой запрос нуждается в оптимизации, и на данном этапе мы точно знаем, что значения есть.
			}
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_SAVE_ENTITY)
		{
			$report=$this->entity->save();
			if ($report instanceof Report_task) $this->final=$report->task;
			elseif ($report instanceof Report_tasks) die ('BAD SAVE REPORT');
			return $report;
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			return $this->final->report();
		}
	}
}

class Task_fill_fieldset_from_entity extends Task_for_entity
{
	use Task_steps;
	
	const
		STEP_REQUEST=0,
		STEP_CONVERT=1,
		STEP_SET=2,
		STEP_STANDARD_DEFAULTS=3;
	
	public
		$codes,
		$codes_left=[],
		$entity_source=[],
		$converters=[],
		$rewrite,
		$field,
		$code_suffix='';
		
	public function run_step()
	{
		if ($this->step===static::STEP_REQUEST)
		{
			if ($this->codes===null) $this->codes=array_keys($this->field->model);
			
			$tasks=[];
			foreach ($this->codes as $code)
			{
				$model=$this->field->model($code);
				if (array_key_exists('for_entity'.$this->code_suffix, $model))
					$source_code = ( ($model['for_entity'.$this->code_suffix]===true) ? ($code) : ($model['for_entity'.$this->code_suffix]) );
				elseif (array_key_exists('from_entity'.$this->code_suffix, $model))
				$source_code = ( ($model['from_entity'.$this->code_suffix]===true) ? ($code) : ($model['from_entity'.$this->code_suffix]) );
				else
				{
					$this->codes_left[]=$code;
					continue;
				}
				$this->entity_source[$code]=$source_code;
				$report=$this->entity->request($source_code);
				if ($report instanceof Report_tasks) $tasks=array_merge($tasks, $report->tasks);
			}
			
			if (!empty($tasks)) return $this->sign_report(new Report_tasks($tasks));
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_CONVERT)
		{
			foreach ($this->entity_source as $code=>$source_code)
			{
				$content=$this->entity->value($source_code);
				if ($content instanceof Report_impossible)
				{
					unset($this->entity_source[$code]);
					$this->codes_left[]=$code;
					continue;
				}
				$model=$this->field->model($code);
				if ( (array_key_exists('convert', $model)) && (array_key_exists('from_entity', $model['convert'])) )
				{
					$args=$model['convert']['from_entity'];
					$keyword=array_shift($args);
					$converter=Converter::with_args($keyword, $content, $args);
					$this->converters[$code]=$converter;
				}
			}
			if (empty($this->converters)) return $this->advance_step();
			else return $this->sign_report(new Report_tasks($this->converters));
		}
		elseif ($this->step===static::STEP_SET)
		{
			$content=[];
			foreach ($this->entity_source as $code=>$source_code)
			{
				if (array_key_exists($code, $this->converters))
				{
					$converter=$this->converters[$code];
					$report=$converter->report();
					if ($report instanceof Report_impossible)
					{
						unset($this->entity_source[$code]);
						$this->codes_left[]=$code;
					}
					else $content[$code]=$converter->resolution;
					continue;
				}
				
				$content[$code]=$this->entity->value($source_code);
			}
			$this->field->set_by_array($content, Value::NEUTRAL_CHANGE, $this->rewrite);
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_STANDARD_DEFAULTS)
		{
			$this->field->fill_defaults_from_model($this->codes_left, $this->rewrite);
			return $this->sign_report(new Report_success());
		}
	}
}
?>