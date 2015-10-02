<?
namespace Pokeliga\Nav;

abstract class PageProcessor extends \Pokeliga\Task\Task implements \Pokeliga\Task\Task_proxy
{
	use \Pokeliga\Task\Task_steps;
	
	const
		STEP_PREPARE_PAGE=0,
		STEP_PROCESS=1,
		STEP_FINISH=2;
	
	public
		$page,
		$final_task;
	
	public static function for_page($page)
	{
		$task=new static();
		$task->page=$page;
		return $task;
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_PREPARE_PAGE)
		{
			$report=$this->page->report();
			if ($report instanceof \Report_final) return $this->advance_step();
			return $this->sign_report(new \Report_task($this->page));
		}
		elseif ($this->step===static::STEP_PROCESS)
		{
			$result=$this->process();
			if ($result instanceof \Report_task) $this->final_task=$result->task;
			elseif ($result instanceof Task) $this->final_task=$result;
			if (!empty($this->final_task))
			{
				$this->make_calls('proxy_resolved', $this->final_task);
				return $this->sign_report(new \Report_task($this->final_task));
			}
			
			if ($result instanceof \Report) return $result;
			return $this->sign_report(new \Report_resolution($result));
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			if (!empty($this->final_task))
			{
				if ($this->final_task->failed()) return $this->final_task->report();
				return $this->sign_report(new \Report_resolution($this->final_task->resolution));
			}
			return $this->sign_report(new \Report_success());
		}
	}
	
	public abstract function process();
}

?>