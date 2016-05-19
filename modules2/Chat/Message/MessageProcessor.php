<?

trait MessageProcessor
{
	public function process_incoming_message(Message $message)
	{
		if (!$this->is_message_processable($message)) throw new MessageException();
		$processor=$this->get_message_processor($message->code());
		if (empty($processor)) throw new MessageException();
		$processor($message);
	}
	
	protected function is_message_processable(Message $message) { return true; }
	
	protected abstract function get_message_processor($code);
}

trait StandardMessageProcessor
{
	use MessageProcessor;
	
	protected
		$message_processors=[];
	
	protected abstract function init_message_processors();
	
	protected function get_message_processor($code)
	{
		if (array_key_exists($code, $this->message_processors)) return $this->message_processors[$code];
	}
}

?>