<?
namespace Pokeliga\Entity;

// родительский класс для проверок содержимого значений, которые находятся в DataSet у сущности.
abstract class Validator_for_entity_value extends \Pokeliga\Data\Validator
{
	use Task_for_entity_methods;
	
	public
		$entity;
	
	public function setup_by_value($value)
	{
		parent::setup_by_value($value);
		$this->entity=$value->master->entity;
	}
}

// интерфейс для любого значения, которое может указать пальцем на сущность.
interface Value_links_entity extends \Pokeliga\Data\Value_has_registers // регистры должны иметь 'id' и 'id_group'
{
	public function get_entity();
	// возвращает либо сущность; либо \Report_task с задачей, разрешением которой станет сущность; либо \Report_impossible. возвращённая сущность не обязательно является подтверждённой. Если возвращается отчёт с задачей, то после выполнения задачи следующий запрос к get_entity() должен вернуть либо сущность, либо невозможность.
	// FIX: хорошо бы во всех случаях возвращать Entity, пусть и в состоянии EXPECTED_ID.
}

// используется при клонировании сущности в другой пул.
interface Value_contains_pool_member { }

interface Value_contains_entity extends Value_contains_pool_member { }

interface Value_handles_cloning
{
	public function cloned_from_pool($pool);
}

// это Value, соответствующие интерфейсу Value_searchable_options, находит сущности и возвращает соответствующую опцию выпадающего списка.
trait Value_searchable_entity
{
	public function option_by_value($value, $line=[])
	{
		$id_group=$this->value_model_now('id_group');
		$entity=$this->pool()->entity_from_db_id($value, $id_group);
		if (empty($entity)) return $this->sign_report(new \Report_impossible('no_entity'));
		
		$template=Template_entity_option::for_entity($entity);
		return $template;
	}
}

class ValueType_id_group extends \Pokeliga\Data\ValueType_keyword
{
	const
		GOOD_CHARACTERS='a-zA-Z\d_\\\\',
		MIN=1,
		MAX=200;
}

class ValueType_entity extends \Pokeliga\Data\ValueType
	implements Value_links_entity, \Pokeliga\Data\Value_searchable_options, Value_contains_entity
{
	use Value_searchable_entity, \Pokeliga\Data\Value_registers;
	
	const
		DEFAULT_SCALAR_REGISTER='id',
		RAW_CONTENT='raw',
		DEFAULT_SEARCH_LIMIT=30,
		DEFAULT_SEARCH_ORDER='id';

	public
		$reg_model=
		[
			'id'=>
			[
				'type'=>'unsigned_int',
				'extract_call'=>['extract_entity_property', 'db_id']
			],
			'id_group'=>
			[
				'type'=>'id_group',
				'extract_call'=>['extract_entity_property', 'id_group']
			],
		];
	
	public function apply_model($model)
	{
		parent::apply_model($model);
		
		$id_group=$this->get_preset_id_group();
		// в результате может придти как \Report_impossible (в модели данных нет), так и название группы, и null - что означает произвольную сущность. но произвольные сущности пока не реализованы.
		if ( (!empty($id_group)) && (!($id_group instanceof \Report_impossible)) ) $this->reg_model['id_group']['const']=$id_group;
		else unset($this->reg_model['id_group']['const']);
		
		if (!empty($this->RegSet)) $this->RegSet->change_model('id_group', $this->reg_model['id_group']);
	}
	
	public function get_preset_id_group()
	{
		if ($this->in_value_model('id_group')) return $this->value_model_now('id_group');
		return $this->sign_report(new \Report_impossible('no_preset_id_group'));
	}
	
	public function extract_entity_property($prop_name)
	{
		$content=$this->content();
		if ($content===null) return $content;
		return $content->$prop_name;
	}
	
	public function settings_based_conversion($content)
	{
		if ($content instanceof Entity)
		{
			$id_group=$this->get_preset_id_group();
			if ( (!($id_group instanceof \Report_impossible)) && (!$content->in_id_group($id_group)) ) $this->sign_report(new \Report_impossible('bad_entity'));
			return $content;
		}
		if (is_numeric($content))
		{
			//return $this->compose_from_regs(['id'=>$content]); // можно обратиться через RegSet для порядка, но довольно легко сделать проще.
			$id_group=$this->get_preset_id_group();
			if ($id_group instanceof \Report_impossible) return $id_group;
			return $this->pool()->entity_from_db_id($content, $id_group);
		}
		if (is_array($content)) return $this->compose_from_regs($content);
		return $this->sign_report(new \Report_impossible('not_entity'));
	}
	
	public function compose_from_regs($data)
	{
		if (!array_key_exists('id', $data)) return $this->sign_report(new \Report_impossible('no_entity_id'));
		else $id=$data['id'];
		
		if (!array_key_exists('id_group', $data))
		{
			$id_group=$this->get_preset_id_group();
			if ($id_group instanceof \Report_impossible) return $this->sign_report(new \Report_impossible('no_id_group')); // в модели нет данных о группе.
		}
		else $id_group=$data['id_group']; // может быть null, что означает произвольную сущность.
		return $this->pool()->entity_from_db_id($id, $id_group);
	}
	
	public function compose_change_by_reg($register, $content)
	{
		return $this->compose_change_by_test_RegSet($register, $content, ['id', 'id_group']);
	}
	
	public function get_entity()
	{
		$report=$this->request();
		if ($report instanceof \Report_resolution) return $report->resolution;
		return $report;
	}
	
	public function template_for_filled($code, $line=[])
	{
		$entity=$this->get_entity();
		if ($entity instanceof \Report) return;
		return $entity->template($code, $line);
	}
	
	public function follow_track($track, $line=[])
	{
		$entity=$this->get_entity();
		if ($entity instanceof \Report_impossible) return $entity;
		if ($entity instanceof \Report_tasks) return $entity->force_again();
		return $entity->follow_track($track, $line=[]);
	}
	
	public function list_validators()
	{
		$validators=parent::list_validators();
		
		$existing=true;
		// могут возникнуть трудности с обязательной проверкой существования сущности: например, мы можем добавить в БД неофита и вместе с ним новую атаку, айди которой мы заранее знаем, но которой ещё нет в БД, однако он уже должен на неё ссылаться - она же вот-вот попадёт в БД. если требовать обязательного существования, то нельзя будет добавить сущности с обязательной взаимной связью. если даже не проверять само существование, не понятно, как быть, если от связанной сущности требуется проверка значений, которые в любом случае будут запрошены и не получены.
		if ($this->in_value_model('existing')) $existing=$this->value_model_now('existing');
		if ($existing!==null)
		{
			$validators=(array)$validators;
			if ($existing) $validators[]='subentity_exists';
			else $validators[]='subentity_doesnt_exist';
		}
		
		if ($this->in_value_model('range'))
		{
			$validators=(array)$validators;
			$validators[]='id_in_range';
		}
		
		return $validators;
	}
	
	public function API_search_arguments()
	{
		$result='group='.$this->value_model_now('id_group');
		if ($this->in_value_model('range'))
		{
			$result.='&range='.$this->value_model_now('range');
			$range_model=$this->produce_range_model();
			if (!empty($range_model))
			{
				foreach ($range_model as $key=>$value)
				{
					if (is_array($value)) die('UNIMPLEMENTED YET: array range model');
					$result.='&'.$key.'='.urlencode($value);
				}
			}
		}
		return $result;
	}
	
	public function found_options_template($search=null, $line=[])
	{
		$id_group=$this->value_model_now('id_group');
		$range_select=$this->produce_range_select();
		$template=Template_found_options::for_search($search, $this, $range_select, $line);
		return $template;
	}
	
	public function produce_range_model()
	{
		if (!$this->in_value_model('range_model')) return;
		
		$model=[];
		foreach ($this->value_model_now('range_model') as $key=>$value)
		{
			// STUB: пока что страхует от ошибок, но не оптимизировано.
			if (Compacter::recognize_mark($value)) $value=Compacter::by_mark_and_extract($this, $value);
			if ($value instanceof \Pokeliga\Task\Task) $value->complete();
			$model[$key]=$value;
		}
		return $model;
	}
	public function produce_range_select()
	{
		if (!$this->in_value_model('range')) return;
		$class='Select_'.$this->value_model_now('range');
		$model=$this->value_model();
		$range_model=$this->produce_range_model();
		if (!empty($range_model)) $model=array_merge($model, $range_model);
		$select=$class::from_model($model);
		return $select;
	}
	
	public function ValueHost_request($code)
	{
		$entity=$this->get_entity();
		if ($entity instanceof \Report_tasks)
		{
			$task=Task_delayed_call::with_call(new Call( [$this, 'ValueHost_request'], $code), $entity);
			return $this->sign_report(new \Report_task($task));
		}
		if ($entity instanceof \Report_impossible) return $entity;
		return $entity->request($code);
	}
}

class ValueType_ids extends \Pokeliga\Data\ValueType_int_array
{
	public function list_validators()
	{
		$validators=parent::list_validators();
		$existing=true;
		if ($this->in_value_model('existing')) $existing=$this->value_model_now('existing');
		if ($existing!==null)
		{
			$validators=(array)$validators;
			if ($existing) $validators[]='all_ids_exist';
			else $validators[]='all_ids_dont_exist';
		}
		return $validators;
	}
	
	public function entities()
	{
		if (!$this->has_state(Value::STATE_FILLED)) die('UNIMPLEMENTED YET');
		$id_group=$this->value_model_now('id_group');
		
		$entities=[];
		foreach ($this->content() as $id)
		{
			$entities[]=$this->pool()->entity_from_db_id($id, $id_group);
		}
		return $entities;
	}
}

// это импортированные значения: например, название вида у сущности-покемона (ссылается на название вида у сущности-вида). В отличие от большинства объектов Value, этот содержит реализацию заполнения, а не рассчитывает на ValueSet.
class ValueType_reference extends \Pokeliga\Data\ValueType implements Value_contains_pool_member
{
	const
		DEFAULT_TEMPLATE_CLASS='Template_value_reference';

	public
		// $content содержит ссылку на другое Value в рамках того же пула.
		$connected=false;
	
	public function set($content, $source=Value::BY_OPERATION)
	{
		if (!$this->connected) parent::set($content, $source);
		else die('SETTING REFERENCE');
	}
	
	public function to_good_content($content)
	{
		if ($content instanceof \Pokeliga\Data\Value) return $content;
		if ($content===null) return $content; // если оригинала нет.
		vdump('BAD CONTENT 2:'); vdump($content); vdump($this); debug_dump(); exit;
	}
	
	public function connected()
	{
		return $this->content instanceof \Pokeliga\Data\Value;
	}
	
	public function content()
	{
		if ($this->connected()) return $this->content->content();
		return parent::content();
	}
	
	public function valid_content($now=true)
	{
		if ($this->connected()) return $this->content->valid_content($now);
		return parent::valid_content($now);
	}
	
	public function request($code=null)
	{
		if ($code!==null) return parent::value($code);
		if ($this->connected()) return $this->content->request();
		else return parent::request();
	}
	
	public function value($code=null)
	{
		if ($code!==null) return parent::value($code);
		if ($this->connected()) return $this->content->value();
		else return parent::value();
	}
	
	public function get_generator_task()
	{
		$filler=parent::get_generator_task();
		if (!empty($filler)) return $filler;
		$filler=Filler_for_entity_reference::for_value($this);
		return $filler;
	}
	
	public function for_display($format=null, $line=[])
	{
		die('SHOULDNT BE HERE');
	}
	
	public function template_for_filled($name, $line=[])
	{
		return $this->content->template($name, $line);
	}
	
	public function ValueHost_request($code)
	{
		if (!$this->has_state(static::STATE_FILLED)) die('UNIMPLEMENTED YET: delayed subvalue');
		return $this->content()->request($code);
	}
}

// в этом наборе место значений занимают объекты класса Entity, а не Value.
class EntitySet extends \Pokeliga\Data\MonoSet
{
	public
		$pool=null;
	
	public function pool()
	{
		if ($this->pool===null) $this->pool=EntityPool::default_pool();
		return $this->pool;
	}
	
	public function create_value($ord, $id=null)
	{
		$model=$this->model($ord);
		if (empty($model['id_group'])) die ('UNIMPLEMENTED YET: empty id group');
		if ($id===null) $entity=$this->pool->new_entity($model['id_group']);
		else $entity=$this->pool->entity_from_db_id($id, $model['id_group']);
		return $entity;
	}
	
	public function fill_value($value)
	{
		die('SHOULDNT FILL ENTITY');
	}
	
	// STUB
	public function has_id($id)
	{
		foreach ($this->values as $entity)
		{
			if ($entity->db_id==$id) return true;
		}
		return false;
	}
	
	public function has_entity($entity)
	{
		foreach ($this->values as $element)
		{
			if ($entity===$element) return true;
			if ( ($entity->id_group===$element->id_group) && ($entity->db_id==$element->db_id) ) return true; // FIX: не учитывает пулы.
		}
		return false;
	}
	
	public function has_subvalue($code, $value)
	{
		$tasks=[];
		foreach ($this->values as $entity)
		{
			$report=$entity->request($code);
			if ( ($report instanceof \Report_resolution) && ($report->resolution==$value) ) return true;
			elseif ($report instanceof \Report_tasks) $tasks=array_merge($tasks, $report->tasks);
		}
		if (empty($tasks)) return false;
		else return Task_delayed_call::with_call(new Call([$this, 'has_subvalue'], $code, $value), $tasks);
	}
}

class Validator_subentity_exists extends \Pokeliga\Data\Validator
{
	use \Pokeliga\Task\Task_steps;
	
	const
		STEP_GET_ENTITY=0,
		STEP_ANALYZE_ENTITY=1,
		STEP_FINISH=2;
	
	public
		$entity;
	
	public function run_step()
	{
		if ($this->step===static::STEP_GET_ENTITY)
		{
			if ( ($this->value->content()===null) && ($this->in_value_model('null')) && ($this->value_model_now('null')) ) return $this->sign_report(new \Report_success());
			if (!(duck_instanceof($this->value, '\Pokeliga\Entity\Value_links_entity'))) return $this->sign_report(new \Report_impossible('bad_value'));
			$entity=$this->value->get_entity();
			if ($entity instanceof Entity) return $this->advance_step();
			if (empty($entity)) return $this->sign_report(new \Report_impossible('no_entity'));
			return $entity; // \Report_impossible или \Report_task.
		}
		elseif ($this->step===static::STEP_ANALYZE_ENTITY)
		{
			$entity=$this->value->get_entity();
			if ($entity instanceof \Report_impossible) return $entity;
			
			$this->entity=$entity;
			$result=$entity->exists(false);
			return $this->bool_to_report($result);
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			$result=$this->entity->exists(); // на этот раз с $now=true
			return $this->bool_to_report($result);
		}
	}
	
	public function bool_to_report($result)
	{
		if ($result===true) return $this->sign_report(new \Report_success());
		if ($result===false) return $this->sign_report(new \Report_impossible('entity_doesnt_exist'));
		return $result;
	}
}

class Validator_subentity_not_self extends Validator_subentity_exists
{
	public function run_step()
	{
		if ($this->step===static::STEP_ANALYZE_ENTITY)
		{
			$entity=$this->value->get_entity();
			if ($entity instanceof \Report_impossible) return $entity;
			
			$this->entity=$entity;
			$ego=null;
			
			if (empty($this->value->master)) return $this->sign_report(new \Report_impossible('bad_value_master'));
			elseif ($this->value->master instanceof DataSet) $ego=$this->value->master->entity;
			elseif ($this->value->master instanceof FieldSet_provides_entity) $ego=$this->value->master->entity();
			else return $this->sign_report(new \Report_impossible('bad_value_master'));
			
			if ($this->entity->equals($ego)) return $this->sign_report(new \Report_impossible('subentity_is_self'));
			return $this->sign_report(new \Report_success());
		}
		return parent::run_setup();
	}
}

// проверяет группу айди заключённой сущности. не путать с типом!
class Validator_subentity_group_is extends Validator_subentity_exists
{
	public function run_step()
	{
		if ($this->step===static::STEP_ANALYZE_ENTITY)
		{
			$entity=$this->value->get_entity();
			if ($entity instanceof \Report_impossible) return $entity;
			
			$good_group=$this->value_model_now('subentity_group_is');
			if ($entity->id_group!==$good_group) return $this->sign_report(new \Report_impossible('bad_id_group'));
			return $this->sign_report(new \Report_success());
		}
		return parent::run_setup();
	}
}

// смотрит заключённую в значение сущность и проверяет, имеет ли её поле подходящее содержимое.
class Validator_subentity_value_is extends Validator_subentity_exists
{	
	const
		// STEP_GET_ENTITY=0,
		// STEP_ANALYZE_ENTITY=1,
		STEP_COMPARE_VALUES=2, // вместо STEP_FINISH=2;
		
		MODEL_CODE='subentity_value_is';
	
	public
		$target_value,
		$strict=true;
	
	public function run_step()
	{
		if ($this->step===static::STEP_ANALYZE_ENTITY)
		{
			$entity=$this->value->get_entity();
			if ($entity instanceof \Report_impossible) return $entity;
			$this->entity=$entity;
			// проверки существования не требуется, потому что она делается в рамках valid_content_request()

			$tasks=[];
			foreach ($this->subentity_values() as $code=>$content)
			{
				$result=$this->entity->valid_content_request($code);
				if ($result instanceof \Report_tasks) $tasks=array_merge($tasks, $result->tasks);
				if ($result instanceof \Report_impossible) return $result;
				
				if ($content instanceof \Pokeliga\Task\Task) $tasks[]=$content;
			}
			if (empty($tasks)) return $this->advance_step();
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_COMPARE_VALUES)
		{
			foreach ($this->subentity_values() as $code=>$content)
			{
				if ($content instanceof \Pokeliga\Task\Task)
				{
					if ($content->failed()) return $content->report();
					$content=$content->resolution;
				}
				$current_content=$this->entity->valid_content($code);
				if ($current_content instanceof \Report_impossible) return $current_content;
				$result=$this->compare_content($current_content, $content);
				if ($result===false)
				{
					return $this->sign_report(new \Report_impossible('bad_strict_content'));
				}
			}
			return $this->sign_report(new \Report_success());
		}
		else return parent::run_step();
	}
	
	public $checked_values=false;
	public function subentity_values()
	{
		$values=&$this->model[static::MODEL_CODE];
		if (!$this->checked_values)
		{	
			foreach ($values as $key=>&$value)
			{
				if (Compacter::recognize_mark($value)) $value=Compacter::by_mark_and_extract($this->value, $value);
			}
			$this->checked_values=true;
		}
		return $values;
	}
	
	public function compare_content($current_content, $target_content)
	{
		if (is_array($target_content))
		{
			foreach ($target_content as $variant)
			{
				if ($this->compare_content($current_content, $variant)) return true;
			}
			return false;
		}
		
		if ( ($this->strict) && ($target_content!==$current_content) ) return false;
		elseif ( (!$this->strict) && ($target_content!=$current_content) ) return false;
		return true;
	}
}

// проверяет, что сущность, заключённая в данном значении, имеет отсылку в сущности-хозяйке данного значения в определённом поле. 
// FIX! кажется, не работает, но пока не используется.
class Validator_subentity_backlinks extends Validator_subentity_value_is
{
	const
		MODEL_CODE='backlink_code';

	public function subentity_values()
	{
		return [$this->value_model_now(static::MODEL_CODE)=>$this->entity];
	}
	
	public function compare_content($current_content, $target_content)
	{
		if (!($current_content instanceof Entity)) return false;
		return $current_content->equals($target_content);
	}
}

class Validator_all_ids_exist extends \Pokeliga\Data\Validator
{
	use \Pokeliga\Task\Task_inherit_dependancy_failure;
	
	public
		$providers_set=false;
		
	public function progress()
	{
		if ($this->providers_set)
		{
			$this->finish();
			return;
		}
		
		if (!($this->value instanceof Value_ids)) die ('UNIMPLEMENTED YET: not Value_ids');
		$entities=$this->value->entities();
		
		foreach ($entities as $entity)
		{
			$result=$entity->exists(false);
			if ($result===false)
			{
				$this->impossible('invalid_player');
				return;
			}
			elseif ($result instanceof \Report_tasks)
			{
				$result->register_dependancies_for($this);
				$this->providers_set=true;
			}
		}
		if (!$this->providers_set) $this->finish();
	}
}

interface Select_has_range_validator
{
	public function get_range_validator($value);
}

class Validator_id_in_range extends \Pokeliga\Data\Validator
{
	public
		$subvalidator;

	public function progress()
	{
		if ($this->subvalidator!==null)
		{
			if ($this->subvalidator->successful()) $this->finish_with_resolution($this->subvalidator->resolution);
			elseif ($this->subvalidator->failed()) $this->impossible($this->subvalidator->errors);
			else die('SUBVALIDATOR ERROR');
			return;
		}
	
		$range_select=$this->value->produce_range_select();
		if ($range_select instanceof Select_has_range_validator)
		{
			$this->subvalidator=$range_select->get_range_validator($this->value);
			if (empty($this->subvalidator)) die('BAD RANGE VALIDATOR');
			$this->register_dependancy($this->subvalidator);
			return;
		}
		
		die('UNIMPLEMENTED YET: generic range validator');
	}
}
?>