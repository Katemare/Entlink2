<?

interface ChatException extends Template_context
{
	public function create_error_message(MessageOriginator $originator);
}

trait StandardChatException
{
	use Context_self;
	
	public function create_error_message(MessageOriginator $originator)
	{
		echo $this;
		$template=Template_from_db::with_db_key(static::ERROR_TEMPLATE_KEY);
		$template->context=$this;
		return new Message($originator, ChatServer::SERVER_CODE_ERROR, $template);
	}
}

?>