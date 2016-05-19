<?

// the main chatwindow with threads list, help and stuff
class ConsoleThread extends ThreadType implements HasIdent
{
	const
		WELCOME_KEY='chat.console_welcome';
		
	protected
		$bot;
	
	protected function init(Thread $thread)
	{
		parent::init($thread);
		$this->bot=new ConsoleBot($this->thread);
	}
	
	public function ident()
	{
		$server=Server();
		return $server::CONSOLE_THREAD_KEY;
	}
	
	public function process_incoming_message(Message $message)
	{
		$this->bot->process_incoming_message($message);
	}
}

class ConsoleBot extends Bot
{
	use MessageProcessor;
	
	const
		UNKNOWN_COMMAND_KEY='chat.unknown_bot_command';
	
	protected
		$thread,
		$commands=[];
	
	public function __construct($thread)
	{
		parent::__construct();
		$this->thread=$thread;
		$this->handle='Чат';
		$this->init_commands();
	}
	
	protected function init_commands()
	{
		$this->commands[]=new Command_ChangeHandle($this);
		$this->commands[]=new Command_CreateThread($this);
		$this->commands[]=new Command_JoinThread($this);
	}
	
	protected function is_message_processable(Message $message)
	{
		return $message->code()===Thread::CLIENT_CODE_CHAT;
	}
	
	protected function get_message_processor($code)
	{
		return [$this, 'process_chat_message'];
	}
	
	protected function process_chat_message(Message $message)
	{
		$message->respond($this->thread->transform_chat_message($message));
		$text=$message->content('text');
		
		// естественные формы ещё не готовы!
		$ticket=$this->recognize_canonical_form($message);
		if (!$ticket) $this->on_unknown_command($message);
		elseif (!$ticket->is_executable()) $this->create_command_response($message, $ticket->render())->deliver();
		else $ticket->execute();
	}
	
	protected function recognize_canonical_form(Message $message)
	{	
		foreach ($this->commands as $command)
		{
			if ($ticket=$command->recognize_canonical_form($message)) return $ticket;
		}
	}
	
	protected function recognize_natural_form(Message $message)
	{
		$results=[];
		foreach ($this->commands as $command)
		{
			if ($ticket=$command->recognize_natural_form($message)) $results[]=$ticket;
		}
		if (empty($results)) return;
		return $results;
	}
	
	protected function on_unknown_command(Message $message)
	{
		$response=$this->thread->create_thread_message('chat', Template_from_db::with_db_key(static::UNKNOWN_COMMAND_KEY));
		$message->respond($response);
	}
	
	public function create_command_response(Message $message, $response)
	{
		$response=$this->thread->create_thread_message('chat', $response);
		$response->set_target($message->originator());
		return $response;
	}
}

abstract class Command_Console extends Command
{
	const
		DENY_NO_AGENT='non_agent';
	
	protected function is_allowed(Message $message, &$deny_ticket)
	{
		$agent=$message->get_agent();
		if (!$agent) return $this->create_deny_ticket($message, static::DENY_NO_AGENT);
	}
}

class Command_ChangeHandle extends Command
{
	use SingleWordCommand;

	const
		BAD_HANDLE='bad_handle';
	
	protected
		$canonical=['nick', 'name', 'nickname', 'handle', 'имя', 'ник', 'никнейм'];
	
	protected function is_allowed(Message $message, &$deny_ticket)
	{
		if (!parent::is_allowed($message, $deny_reason)) return false;
		return $message->get_agent()->group()==='anon';
	}
	
	protected function extract_canonical_args($data, &$deny_reason)
	{
		$word=$this->extract_single_word($data, $deny_reason);
		if ($word===null) return;
		$manager=Server()->anon_handle_manager();
		if (!$manager->is_custom_handle_valid($word))
		{
			$deny_reason=static::BAD_HANDLE;
			return;
		}
		return ['handle'=>$word];
	}
	
	public function execute(CommandTicket $ticket)
	{
		$manager=Server()->anon_handle_manager();
		$agent=$ticket->message()->get_agent();
		$handle=$ticket->args('handle');
		$result=$manager->approve_custom_handle($agent, $handle, $deny_reason);
		if ($deny_reason!==null) $this->on_deny_execution($ticket, $deny_reason);
		else $agent->set_handle($handle);
	}
}

class Command_CreateThread extends Command
{
	use PhraseCommand;
	
	const THREAD_CLASS='GroupThread';
	
	protected
		$canonical=['create', 'создать канал', 'создать тред', 'создать топик', 'создать', 'новый', 'новый канал', 'новый тред', 'новый топик'];
	
	protected function extract_canonical_args($data, &$deny_reason)
	{
		$title=$this->extract_phrase($data, $deny_reason);
		if ($title===null) return;
		$class=static::THREAD_CLASS;
		if (!$class::is_title_valid($title, $deny_reason)) return false;
		return ['title'=>$title];
	}
	
	public function execute(CommandTicket $ticket)
	{
		$message=$ticket->message();
		$agent=$message->get_agent();
		$title=$ticket->args('title');
		if (!Server()->can_create_thread($title, $deny_reason)) $this->on_deny_execution($ticket, $deny_reason);
		else
		{
			try
			{
				$class=static::THREAD_CLASS;
				$thread=$class::create();
				$thread->set_title($title);
				$thread->set_founder($agent);
				Server()->register_thread($thread);
				$thread->join($agent);
			}
			catch (Exception $e)
			{
				$this->on_execution_exception($message, $e);
			}
		}
	}
}

class Command_JoinThread extends Command
{
	use PhraseCommand;
	
	const
		DENY_NO_SUCH_THREAD='no_thread',
		DENY_NON_JOINABLE_THREAD='non_joinable_thread';
	
	protected
		$canonical=['join', 'присоединиться', 'войти', 'зайти', 'канал', 'тред', 'топик'];
	
	protected function extract_canonical_args($data, &$deny_reason)
	{
		$title=$this->extract_phrase($data, $deny_reason);
		if ($title===null) return;
		return ['title'=>$title];
	}
	
	public function execute(CommandTicket $ticket)
	{
		$message=$ticket->message();
		$agent=$message->get_agent();
		$title=$ticket->args('title');
		$thread=Server()->thread_by_title($title);
		if (!$thread) $this->on_deny_execution($ticket, static::DENY_NO_SUCH_THREAD);
		elseif (!duck_instanceof($thread, 'JoinableThread')) $this->on_deny_execution($ticket, static::DENY_NON_JOINABLE_THREAD);
		elseif (!$thread->can_join($agent, $deny_reason)) $this->on_deny_execution($ticket, $deny_reason);
		else $thread->join($agent);
	}
}

?>