<?

interface ChatException extends Template_context
{
	public function create_error_message(MessageOriginator $originator);
}

trait StandardChatException
{
	use Context_self;
	
	public function describe_error()
	{
		$template=Template_from_db::with_db_key(static::ERROR_TEMPLATE_KEY);
		$template->context=$this;
		return $template;
	}
	
	public function create_error_message(MessageOriginator $originator)
	{
		echo $this;
		return new Message($originator, ChatServer::SERVER_CODE_ERROR, $this->describe_error());
	}
}

?>