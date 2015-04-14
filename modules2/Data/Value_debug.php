<?

trait Logger_Value
{
	use Logger;
	
	public function log_domain() { return 'Data'; }
	
	public function log($msg_id, $details=[])
	{
		if ($msg_id==='value') $this->debug('VALUE '.$this->code.', STATE '.$this->state);
	}
}

?>