<?

// the main chatwindow with threads list, help and stuff
class ConsoleThread extends ThreadType implements HasIdent
{
	const
		WELCOME_KEY='chat.console_welcome';
	
	public function ident()
	{
		$server=Server();
		return $server::CONSOLE_THREAD_KEY;
	}
	
	protected function init_message_processors()
	{
		$this->message_processors['chat']=[$this, 'process_chat_message'];
	}
	
	protected function process_chat_message(Message $message)
	{
		$message->respond($this->transform_chat_message($message));
		$response=$this->create_thread_message(Thread::SERVER_CODE_NOTIFICATION, 'Meow '.mt_rand(1, 256));
		$message->respond($response);
	}
}

class ConsoleBot extends Bot
{
}

?>