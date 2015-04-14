<?

trait Logger_Retriever
{
	use Logger;
	
	public function log_domain() { return 'Retriever'; }

	public function log($msg_id, $details=[])
	{
		if ($msg_id==='data_rewrite') $this->debug ('DATA REWRITE');
		elseif ($msg_id==='query') $this->debug($details['query'], 'query');
	}
}

?>