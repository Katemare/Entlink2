<?
namespace Pokeliga\Data;

load_debug_concern(__DIR__, 'Filler');

abstract class Task_for_value extends \Pokeliga\Task\Task implements ValueLink, ValueModel
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
	
	public function setup() { }
}

// эта задача в результате своей работы переводит значение из состояния FILLING в FILLED или FAILED. 
// Важно! основное назначение этих задач - это именно достичь одного из итоговых состояний данного. задачи, чьим главным назначением является получение некоторого значения, следует лишь оборачивать в филлеры, если может быть необходимость в том, чтобы оперировать с этим значением независимо.
abstract class Filler extends Task_for_value
{
	use Logger_Filler;

	public
		$original_state,
		$source_type=Value::BY_OPERATION,
		$master_capable=true;
	
	// этот метод сначала пытается получить значение без того, чтобы создавать процесс. Он вызывается, когда значение ещё находится в состоянии UNFILLED или IRRELEVANT. Если удаётся выполнить задачу без того, чтобы создавать процесс, замечательно. Если же нет - то есть, если для заполнения нужны дополнительные задачи или запросы к БД - то этот метод возвращает \Report_promise. Важно! Состояние FILLING задаёт ValueSet или другой объект, который вызвал данный метод - вдруг если заполнение не мгновенное, то значение ему и не нужно?
	public function master_fill() // только этот метод имеет право изменять содержимое и статус значения.
	{
		if (!$this->master_capable) die ('NOT INTENDED AS MASTER');
		if ($this->value->filler_task===$this) return $this->fill();
		if ($this->value->filler_task!==null) { vdump($this); die ('DOUBLE FILLER'); }
		
		if ($this->value->is_filled()) return new \Report_resolution($this->value->content(), $this);
		$this->original_state=$this->value->state();
		$this->value->set_filler($this);
		return $this->fill();
	}
	
	public function fill_with_master($master)
	{
		$this->original_state=$master->original_state;
		return $this->fill();
	}
	
	public function fill()
	{
		return $this->report_promise();
	}
	
	public function progress()
	{
		// не абстрактный метод потому, что могут быть филлеры, справляющиеся без выполнения задачи, исключительно методом fill().
		die ('NO PROGRESS FILLER: '.get_class($this));
	}
	
	// этот метод вызывается до того, как задача выполнит действия, подобающие своему завершению. она вносит изменения в значение, но только если является старшей (совпадающей с параметром $filler_task этой задачи).
	public function on_finish($success=null)
	{
		if ($this->value->filler_task!==$this) return;
		if ($this->successful()) $this->apply_successful();
		else $this->apply_failed();
		
	}
	
	public function apply_successful()
	{
		$this->log('successful_fill');
		$this->set_value_to_resolution();
		$this->resolution=$this->value->content; // поскольку, во-первых, присваивание нормализует тип данных, во-вторых, филлер иногда выступает в качестве задачи-посредника, и от него ожидается, что результат будет совпадать с содержимым значения.
		$this->value->filler_task=null;	
	}
	
	public function set_value_to_resolution()
	{
		$this->value->set($this->resolution, $this->source_type);		
	}
	
	public function apply_failed()
	{
		$this->value->set_failed($this->report(), $this->source_type);
	}
}

class Filler_by_task extends Filler
{
	use \Pokeliga\Task\Task_coroutine;
	
	public
		$task;
		
	public function with_task($task, $value)
	{
		$filler=static::for_value($value);
		$filler->task=$task;
		return $filler;
	}
	
	public function coroutine()
	{
		yield $need=$this->task->to_need();
		$this->finish($need);
	}
}

// FIXME: используется всего в одном месте, желательно убратью
class Filler_delay extends Filler
{
	public
		$delay_task;
		
	public static function with_call($value, $call, $dependancy)
	{
		$filler=static::for_value($value);
		$filler->delay_task=Task_delayed_call::with_call($call, $dependancy);
		return $filler;
	}
	
	public function progress()
	{
		if (!$this->delay_task->completed())
		{
			$this->register_dependancy($this->delay_call);
			return;
		}
		
		if ($this->delay_task->failed())
		{
			$this->impossible($this->delay_task);
		}
		else
		{
			$this->finish_with_resolution($this->delay_task->resolution);
		}
	}
}

?>