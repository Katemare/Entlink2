<?

trait Logger_Entity
{
	use Logger;
	
	public function log_domain() { return 'Entity'; }
		
	public function log($msg_id, $details=[])
	{
		if ($msg_id==='provider') $this->debug('CREATED PROVIDER: '.$this->provider->human_readable());
		elseif ($msg_id==='resolving_call')
		{
			ob_start();
			var_dump($details['args']);
			$args=ob_get_contents();
			ob_end_clean();
			$this->debug('<b><font color="purple">RESOLVING CALL</font></b>: <b>'.$details['name'].'</b>['.$args.']', 'EntityType');
		}
	}
}

?>