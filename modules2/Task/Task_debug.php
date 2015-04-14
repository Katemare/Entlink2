<?

trait Logger_Task
{
	use Logger;
	
	public function log_domain() { return 'Task'; }
		
	public function log($msg_id, $details=[])
	{
		if ($msg_id==='to_complete') $this->debug('COMPLETING TASK '.$this->human_readable());
		elseif ($msg_id==='success') $this->debug('<b>SUCCESS</b> for '.$this->human_readable());
		elseif ($msg_id==='failure') $this->debug('<b>FAILURE</b> for '.$this->human_readable().': '.( ( is_array($this->errors) && !empty($this->errors) )?(implode(', ', $this->errors)):('(unknown error)')));
		elseif ($msg_id==='dep_resolved') $this->debug('<b>DEP RESOLVED</b> for '.get_class($this).'['.$this->object_id.']: '.$details['task']->human_readable());
		elseif ($msg_id==='progressable') $this->debug('<b>NOW PROGRESSABLE</b> for '.get_class($this).'['.$this->object_id.']');
		elseif ($msg_id==='need') $this->debug('<b>NEED</b> '.$this->human_readable().' by '.$details->human_readable().' (total need '.$this->need.')');
		elseif ($msg_id==='unneed') $this->debug('<b>UNNEED</b> '.$this->human_readable().' by '.$details->human_readable().' (total need '.$this->need.')');
	}
}

?>