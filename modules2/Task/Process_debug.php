<?

trait Logger_Process
{
	use Logger;
	
	public function log_domain() { return 'Task'; }
		
	public function log($msg_id, $details=[])
	{
		if ($msg_id==='to_complete')
		{
			$this->debug('COMPLETING PROCESS: '.$this->human_readable());
			
			foreach ($this->subtasks as $task)
			{
				$this->debug('SUBTASK '.$task->human_readable());
			}
			foreach ($this->requests as $task)
			{
				$this->debug('REQUEST '.$task->human_readable());
			}
		}
		elseif ($msg_id==='new_cycle') $this->debug('<b><font color="blue">process ['.$this->object_id.']: complete cycle #'.$details['try'].'</b></font>');
		elseif ($msg_id==='requests') $this->debug('<b><font color="green">MAKING REQUESTS</font></b> ('.count($this->requests).') by ['.$this->object_id.']');
		elseif ($msg_id==='request') $this->debug('<b><font color="green">REQUEST</font></b>: '.get_class($details['request']).'['.$details['request']->object_id.']');
		elseif ($msg_id==='progressing_subtask') $this->debug('<b>PROGRESS</b>: '.get_class($details['subtask']));
		elseif ($msg_id==='subtask_report')
		{
			$this->debug('<b>REPORT</b> for '.get_class($details['subtask']).':');
			$this->debug($details['subtask']->human_readable());
		}
		elseif ($msg_id==='max_standalone_progress') $this->debug('<font color="blue">process: max_standalone_progress #'.$details['try'].'</font>');
		elseif ($msg_id==='drop_unneeded') $this->debug('<b>DROPPING UNNEEDED</b>: '.$details['subtask']->human_readable());
		elseif ($msg_id==='removing_subtask') $this->debug ('<b>REMOVING COMPLETED</b>: '.$details['subtask']->human_readable());
		elseif ($msg_id==='skipping_subtask')
		{
			$ids=[];
			foreach ($details['subtask']->subtasks as $subtask)
			{
				$ids[]=$subtask->object_id;
			}
			$msg='<b>SKIPPING DELAYED</b>: '.get_class($details['subtask']).'['.($details['subtask']->object_id).'], '.count($details['subtask']->subtasks).' subtasks ids: '.implode(', ', $ids);
			$this->debug($msg);
		}
		elseif ($msg_id==='progress_result') $this->debug('<b><font color="blue">RESULT</font></b>: '.$this->human_readable());
		elseif ($msg_id==='adding_subtask') $this->debug('<b>ADDING SUBTASK</b> to ['.$this->object_id.']: '.$details['subtask']->human_readable());
		elseif ($msg_id==='goal_completed') $this->debug('<b>GOAL COMPLETED</b>');
		else parent::log($msg_id, $details);
		/*
		elseif ($msg_id==='success') $this->debug('<b>SUCCESS</b> for '.$this->human_readable());
		elseif ($msg_id==='failure') $this->debug('<b>FAILURE</b> for '.$this->human_readable().': '.( ( is_array($this->errors) && !empty($this->errors) )?(implode(', ', $this->errors)):('(unknown error)')));
		elseif ($msg_id==='dep_resolved') $this->debug('<b>DEP RESOLVED</b> for '.get_class($this).': '.$task->human_readable());
		*/
	}
}

?>