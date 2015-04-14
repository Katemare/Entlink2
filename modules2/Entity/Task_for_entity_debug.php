<?

trait Logger_Task_for_entity
{
	use Logger;
	
	public function log_domain() { return 'Entity'; }
	
	public function log($msg_id, $details=[])
	{
		if ($msg_id==='resolved_call')
		{
			ob_start();
			var_dump($this->args);
			$args=ob_get_contents();
			ob_end_clean();
			
			$this->debug('<b><font color="purple">RESOLVED CALL</font></b>: <b>'.$this->name.'</b>['.$args.']');
		}
		else parent::log($msg_id, $details);
	}
}

?>