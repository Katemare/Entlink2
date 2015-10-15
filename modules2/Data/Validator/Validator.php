<?
namespace Pokeliga\Data;

// интерфейс для двух классов - Validator и ValidationPlan_single_value, которые проверяют действительность строго одного значения.
interface Validation
{	
	public function apply_validation();
}

trait Task_validates
{	
	public function on_finish()
	{
		parent::on_finish();
		$this->apply_validation();
	}
	
	public function apply_validation()
	{
		if ($this->value->valid===$this) $this->value->valid=$this->successful();
	}
}

abstract class Validator extends \Pokeliga\Task\Task implements Validation, ValueLink, ValueModel
// унаследован от Task, а не Task_for_value потому, что его метод for_value имеет другой набор параметров, а также имеет собственную модель.
{
	use \Pokeliga\Entlink\Shorthand, ValueModel_owner, Task_validates, \Pokeliga\Task\Task_binary;
	
	public
		$value,
		$model;
		
	public static function for_value($keyword, $value, $model=null)
	{
		$validator=static::from_shorthand($keyword);
		$validator->value=$value;
		$validator->setup();
		if ($model!==null) $validator->model=array_merge($validator->model, $model);
		return $validator;
	}
	
	public function get_value() { return $this->value; }
	
	public function setup()
	{
		$this->model=$this->value->value_model();
	}
}

// если внутренняя задача проваливается или получает ложь, то действительность не подтверждается. в противном случае она подтверждается.
abstract class Validator_by_task extends Validator
{
	public
		$task;
		
	public function task()
	{
		if ($this->task===null) $this->task=$this->get_task();
		return $this->task;
	}
	
	public function get_task()
	{
		return new \Report_impossible('no_validator_task', $this);
	}
	
	public function progress()
	{
		if ($this->task instanceof \Report_final) $this->finish($this->task);
		elseif (!$this->task->completed()) $this->register_dependancy($this->task);
		else $this->finish($task);
	}
}

// применяется к значениям, которые нужно сначала заполнить, а потом проверять. отличается тем, что позволяет заполнение значения, в отличие ото всех остальных проверщиков.
class Validator_delay extends Validator
{
	public
		$subvalidator=null;
		
	public function progress()
	{
		if ($this->subvalidator!==null)
		{
			$this->finish($this->subvalidator);
			return;
		}
		
		$estimate=$this->value->estimate_validity();
		if ($estimate===null)
		{
			if ($this->value->is_final()) die('BAD VALIDATION ESTIMATE');
			$report=$this->value->request();
			if ($report instanceof \Report_final) return; //при следующем прогоне оценка действительности изменится.
			$report->register_dependancies_for($this);
		}
		elseif ($estimate instanceof \Pokeliga\Task\Task)
		{
			$this->subvalidator=$estimate;
			$this->register_dependancy($this->subvalidator);
		}
	}
}

// если сюда попадают валидаторы от разных значений, то в случае провала одного из них другие валидаторы останутся недоделанными, а в значениях останутся задача валидаторов вместо результата (с другой стороны, если план выполняется в рамках старшего процесса, то валидаторы в качестве зависимостей уже переданы наверх). этот класс нужно использовать только в случае, если проверка всех значений нужна именно как целое, и провал любой проверки даёт провал цели.
class ValidationPlan extends \Pokeliga\Task\Process_collection_absolute
{	
	// возвращает true (если проверки заведомо отработали), false (если хотя бы одна из проверок заведомо ложная) или ValidationPlan, если нужно завершить циклп проверок.
	public static function from_validators($validators)
	{
		if (empty($validators)) return true;
		elseif ( is_array($validators) and count($validators)==1 ) return reset($validators);
		$plan=new static($validators);
		if ($plan->completed()) return $plan->successful();
		return $plan;
	}
}

// набор валидаторов строго одного значения, все из которых должны завершиться успехом, чтобы значение было признано допустимым.
class ValidationPlan_single_value extends ValidationPlan implements Validation
{
	use Task_validates;
	
	public
		$value=null;
		
	public function register_goal($task)
	{
		if ($this->value===null) $this->value=$task->value;
		elseif ($task->value!==$this->value) { vdump($task); die ('BAD VALIDATOR 1'); }
		return parent::register_goal($task);
	}
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