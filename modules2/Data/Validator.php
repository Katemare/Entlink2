<?
// интерфейс для двух классов - Validator и ValidationPlan_solo, которые проверяют действительность строго одного значения.
interface Validation
{	
	public function apply_validation();
}

abstract class Validator extends Task implements Validation, ValueLink, ValueModel
// унаследован от Task, а не Task_for_value потому, что его метод for_value гораздо.
{
	use Prototyper, ValueModel_owner;
	
	static
		$prototype_class_base='Validator_';
	
	public
		$value,
		$model;
		
	public static function for_value($keyword, $value, $model=null)
	{
		$validator=static::from_prototype($keyword);
		$validator->setup_by_value($value);
		if ($model!==null) $validator->model=array_merge($validator->model, $model);
		return $validator;
	}
	
	public function setup_by_value($value)
	{
		$this->value=$value;
		$this->model=$value->value_model();
	}
	
	public function get_value()
	{
		return $this->value;
	}
	
	public function finish($success=true)
	{
		$this->resolution=$success;
		$this->apply_validation();
		parent::finish($success);
	}
	
	public function apply_validation()
	{
		if ($this->value->valid===$this) $this->value->valid=$this->resolution;
	}
}

// применяется к значениям, которые нужно сначала заполнить, а потом проверять.
class Validator_delay extends Validator
{
	public
		$final=null;
		
	public function progress()
	{
		if ($this->final instanceof Task)
		{
			if ($this->final->failed()) $this->impossible('subvalidator_failed');
			elseif ($this->final->successful()) $this->finish();
			else die ('BAD SUBVALIDATOR RESULT');
		}
		elseif ($this->value->has_state(Value::STATE_FAILED))
		{
			$this->resolution=false;
			$this->finish();
		}
		elseif ($this->value->has_state(Value::STATE_FILLED))
		{
			$this->value->valid=null;
			$result=$this->value->is_valid(false);
			if ($result instanceof Report_task)
			{
				$result->register_dependancies_for($this);
				$this->final=$result->task;
			}
			elseif ($result instanceof Report_tasks) die ('BAD VALIDATION REPORT');
			elseif ($result===true) $this->finish();
			elseif ($result===false) $this->impossible('subvalidator_failed');
			else { vdump($result); die ('BAD VALIDATION RESULT'); }
		}
		elseif ($this->value->has_state(Value::STATE_FILLING))
		{
			$this->register_dependancy($this->value->filler_task);
		}
		else // UNFILLED, IRRELEVANT
		{
			$result=$this->value->fill();
			if ($result instanceof Report_tasks) $result->register_dependancies_for($this);
		}
	}
	
	public function dependancy_resolved($task, $identifier=null)
	{
		parent::dependancy_resolved($task, $identifier);
		if ( ($task instanceof Validator) && ($task->resolution===false) ) $this->impossible('subvalidator_failed');
	}
}

// если сюда попадают валидаторы от разных значений, то в случае провала одного из них другие валидаторы останутся недоделанными, а в значениях останутся задача валидаторов вместо результата (с другой стороны, если план выполняется в рамках старшего процесса, то валидаторы в качестве зависимостей уже переданы наверх). этот класс нужно использовать только в случае, если проверка всех значений нужна именно как целое, и провал любой проверки даёт провал цели.
class ValidationPlan extends Process_collection_absolute
{	

	// возвращает true (если проверки заведомо отработали), false (если хотя бы одна из проверок заведомо ложная) или ValidationPlan, если нужно завершить циклп проверок.
	public static function from_validators($validators)
	{
		if (empty($validators)) die ('NO VALIDATORS');
		elseif ( (is_array($validators)) && (count($validators)==1) )
		{
			$validator=reset($validators);
			if ($validator->value->valid===null) $validator->value->valid=$validator;
			return $validator;
		}
		$plan=new static($validators);
		if ($plan->completed()) return $plan->successful();
		return $plan;
	}
}

// набор валидаторов строго одного значения, все из которых должны завершиться успехом, чтобы значение было признано допустимым.
class ValidationPlan_solo extends ValidationPlan implements Validation
{
	public
		$value=null;
		
	public static function from_validators($validators)
	{
		if ( (is_array($validators)) && (count($validators)==1) )
		{
			$validator=reset($validators);
			if ($validator->completed()) return $validator->successful();
			return $validator;
		}
		return parent::from_validators($validators);
	}
		
	public function register_goal($task)
	{
		if ($this->value===null) $this->value=$task->value;
		elseif ($task->value!==$this->value) { vdump($task); die ('BAD VALIDATOR 1'); }
		if ($this->value->valid===null) $this->value->valid=$this;
		
		return parent::register_goal($task);
	}
	
	public function finish($success=true)
	{
		$this->resolution=$success;
		$this->apply_validation();
		parent::finish($success);
	}
	
	public function apply_validation()
	{
		if ($this->value->valid===$this) $this->value->valid=$this->resolution;
	}
}

abstract class Validator_comparison extends Validator
{
	use Task_steps;
	
	const
		STEP_RESOLVE_REFERENCES=0,
		STEP_SAVE_REFERENCES=1,
		STEP_COMPARE=2;

	public
		$reference_code='compare_to',
		$gap_code='comparison_gap',
		$references=null;
		
	public function gap()
	{
		$gap=$this->value_model_soft($this->gap_code);
		if ($gap instanceof Report_impossible) return 0;
		return $gap;
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_RESOLVE_REFERENCES)
		{
			$tasks=[];
			$this->references=$this->references();
			if (!is_array($this->references)) $this->references=[$this->references];
			if (empty($this->references)) return $this->sign_report(new Report_success());
			foreach ($this->references as &$reference)
			{
				$reference=$this->resolve_reference($reference);
				if ($reference instanceof Task)
				{
					if ($reference->failed()) return $reference->report();
					if ($reference->successful()) $reference=$reference->resolution;
					else $tasks[]=$reference;
				}
			}
			if (empty($tasks)) return $this->advance_step(static::STEP_COMPARE);
			return $this->sign_report(new Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_SAVE_REFERENCES)
		{
			foreach ($this->references as &$reference)
			{
				if ($reference instanceof Task)
				{
					if ($reference->failed()) return $reference->report();
					elseif ($reference->successful()) $reference=$reference->resolution;
					else die('BAD TASK STATE');
				}
			}
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_COMPARE)
		{
			$gap=$this->gap();
			foreach ($this->references as $reference)
			{
				if ($this->compare_to($reference, $gap)===false) return $this->sign_report(new Report_impossible('doesnt_compare'));
			}
			return $this->sign_report(new Report_success());
		}
	}
	
	public function references()
	{
		return $this->value_model($this->reference_code);
	}
	
	public function resolve_reference($reference)
	{
		if ($reference instanceof Compacter) $reference=$reference->extract_for($this);
		return $reference;
		
	}
	
	public abstract function compare_to($reference, $gap=0);
}

trait Validator_comparison_to_sibling
{
	public function resolve_reference($reference)
	{
		$reference=parent::resolve_reference($reference);
		if ($reference instanceof Task) die ('BAD SIBLING CODE');
		
		$result=$this->value->master->valid_content($reference, false);
		if ($result instanceof Report_task) return $result->task;
		elseif ($result instanceof Report_tasks) die ('BAD REQUEST RESULT');
		return $result;
	}
}

// строго больше.
class Validator_greater extends Validator_comparison
{
	public
		$reference_code='greater_than',
		$gap_code='greater_gap';
	
	public function compare_to($reference, $gap=0)
	{
		return $this->value->content()>$reference+$gap;
	}
}
class Validator_greater_than_sibling extends Validator_greater
{
	use Validator_comparison_to_sibling;
}

// больше либо равно.
class Validator_greater_or_equal extends Validator_comparison
{
	public
		$reference_code='greater_or_equal',
		$gap_code='greater_or_equal_gap';
	
	public function compare_to($reference, $gap=0)
	{
		return $this->value->content()>=$reference+$gap;
	}
}
class Validator_greater_or_equal_to_sibling extends Validator_greater_or_equal
{
	use Validator_comparison_to_sibling;
}

// строго меньше.
class Validator_less extends Validator_comparison
{
	public
		$reference_code='less_than',
		$gap_code='less_gap';
	
	public function compare_to($reference, $gap=0)
	{
		return $this->value->content()<$reference-$gap;
	}
}
class Validator_less_than_sibling extends Validator_less
{
	use Validator_comparison_to_sibling;
}

// меньше либо равно.
class Validator_less_or_equal extends Validator_comparison
{
	public
		$reference_code='less_or_equal',
		$gap_code='less_gap';
	
	public function compare_to($reference, $gap=0)
	{
		return $this->value->content()<=$reference-$gap;
	}
}
class Validator_less_or_equal_to_sibling extends Validator_less_or_equal
{
	use Validator_comparison_to_sibling;
}

// не равно.
class Validator_not_equal extends Validator_comparison
{
	public
		$reference_code='not_equal';
	
	public function compare_to($reference, $gap=0 /* не используется */)
	{
		return $this->value->content()!=$reference;
	}
}
class Validator_not_equal_to_sibling extends Validator_not_equal
{
	use Validator_comparison_to_sibling;
}

class Validator_not_equal_strict extends Validator_not_equal
{
	public
		$reference_code='not_equal_strict';
	
	public function compare_to($reference, $gap=0 /* не используется */)
	{
		return $this->value->content()!==$reference;
	}
}
class Validator_not_equal_to_sibling_strict extends Validator_not_equal_strict
{
	use Validator_comparison_to_sibling;
}

// содержимое значения должно быть из приводимого списка. используется тогда, когда необходимо наложить дополнительное ограничение помимо типа, при этом получив список, возможно, процедурно (например, спросив у родительского набора). Следует также помнить о поле модели 
// STUB: пока не предполагает отложенного получения списка.
class Validator_valid_list extends Validator
{
	public
		$strict=true;
		
	public function valid_list()
	{
		return $this->value_model_now('valid');
	}
	
	public function progress()
	{
		if (in_array($this->value->content(), $this->valid_list(), $this->strict)) $this->finish();
		else $this->impossible('invalid');
	}
}
?>