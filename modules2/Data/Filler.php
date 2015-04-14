<?
load_debug_concern('Data', 'Filler');

abstract class Task_for_value extends Task implements ValueLink, ValueModel
{
	use ValueModel_from_link;
	
	public
		$value;
	
	public static function for_value($value)
	{
		$task=new static();
		$task->value=$value;
		$task->setup();
		return $task;
	}
	
	public function get_value()
	{
		return $this->value;
	}
	
	public function setup()
	{
	}
}

// эта задача в результате своей работы переводит значение из состояния FILLING в FILLED или FAILED. 
abstract class Filler extends Task_for_value
{
	use Logger_Filler;

	public
		$original_state,
		$source_type=Value::BY_OPERATION,
		$master_capable=true;
	
	// этот метод сначала пытается получить значение без того, чтобы создавать процесс. ќн вызывается, когда значение ещЄ находится в состоянии UNFILLED или IRRELEVANT. ≈сли удаЄтся выполнить задачу без того, чтобы создавать процесс, замечательно. ≈сли же нет - то есть, если для заполнения нужны дополнительные задачи или запросы к Ѕƒ - то этот метод возвращает Report_tasks с задачей (данной или клоном), выполнение которой в рамках процесса заполнит запись. ¬ажно! —остояние FILLING задаЄт ValueSet или другой объект, который вызвал данный метод - вдруг если заполнение не мгновенное, то значение ему и не нужно?
	public function master_fill() // только этот метод имеет право изменять содержимое и статус значения.
	{
		if (!$this->master_capable) die ('NOT INTENDED AS MASTER');
		if ($this->value->filler_task===$this) return $this->fill();
		if ($this->value->filler_task!==null) { vdump($this); die ('DOUBLE FILLER'); }
		
		if ($this->value->has_state(Value::STATE_FILLED)) return $this->sign_report(new Report_resolution($this->value->content()));
		$this->original_state=$this->value->state();
		$this->value->set_state(Value::STATE_FILLING);
		$this->value->filler_task=$this;
		return $this->fill();
	}
	
	public function fill_with_master($master)
	{
		$this->original_state=$master->original_state;
		return $this->fill();
	}
	
	public function fill()
	{
		return $this->sign_report(new Report_task($this));
	}
	
	public function progress()
	{
		die ('NO PROGRESS FILLER: '.get_class($this));
	}
	
	public function finish($success=true)
	{
	// несколько задач-филлеров могут быть связаны с одним и тем же значением, но значение связано только с одной из задач.
		if ($this->value->filler_task===$this) $this->apply($success);
		parent::finish($success);
	}
	
	// этот метод вызывается до того, как задача выполнит действия, подобающие своему завершению. она вносит изменения в значение, но только если является старшей (совпадающей с параметром $filler_task этой задачи).
	public function apply($success=null)
	{
		if ($success===null) $success=$this->complete;
		if ($success===null) die ('PREMATURE FILLER APPLICATION');

		if ($success) $this->apply_successful();
		else $this->apply_failed();
		
	}
	
	public function apply_successful()
	{
		$this->log('successful_fill');
		$this->value->set($this->resolution, $this->source_type);
		$this->value->set_state(Value::STATE_FILLED);
		$this->resolution=$this->value->content; // поскольку, во-первых, присваивание нормализует тип данных, во-вторых, филлер иногда выступает в качестве задачи-посредника, и от него ожидается, что результат будет совпадать с содержимым значения.
		$this->value->filler_task=null;	
	}
	
	public function apply_failed()
	{
		$this->value->set_state(Value::STATE_FAILED);
		$this->value->filler_task=null;	
	}
}
?>