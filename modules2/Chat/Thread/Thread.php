<?

// a thread is a collection of members, a message log and a set of rules by which they trade messages.
class Thread implements MessageNode, HasIdent
{
	use Object_id;

	const
		SERVER_CODE_NOTIFICATION='notify',	// html message
		
		CLIENT_CODE_CHAT='chat';
	
	protected
		$type,			// ThreadType
		$log=[],		// of LoggedMessage
		$memberships=[];	// of ThreadMembership
	
	public function __construct(ThreadType $type)
	{
		$this->generate_object_id();
		$this->type=$type;
	}
	
	public function ident()
	{
		if ($this->type instanceof HasIdent) return $this->type->ident();
		return $this->object_id;
	}
	
	public function gen_members($approve_callback=null)
	{
		foreach ($this->memberships as $membership)
		{
			if ($approve_callback!==null and !$approve_callback($member)) continue;
			yield $membership->member;
		}
	}
	
	// sends a message to all members
	public function broadcast(Message $message, $approve_callback=null)
	{
		$message->multicast($this->gen_members($approve_callback));
	}
	
	public function process_incoming_message(Message $message)
	{
		$this->type->process_incoming_message($message);
	}
	
	public function __call($method, $args)
	{
		if (method_exists($this->type, $method)) return $this->type->$method(...$args);
		else throw new BadMethodCallException();
	}
	
	public function on_message_bounce(Message $message, Exception $e)
	{
		// nop
	}
	
	public function create_thread_message($code, $content, MessageOriginator $originator=null, $ts=null)
	{
		if ($originator===null) $originator=$this;
		return new ThreadMessage($originator, $code, $content, $this, $ts);
	}
}

// controls how the thread behaves. by having it a separate object, it's possible to transform a private thread to a conversation (a topic?).
abstract class ThreadType
{
	const
		WELCOME_KEY='chat.thread_welcome';
		
	protected
		$thread,	// Thread
		$message_processors=[];
	
	public static function create()
	{
		$type=new static();
		$thread=new Thread($type);
		$type->thread=$thread;
		$type->init_message_processors();
		return $thread;
	}
	
	protected function init_message_processors() { }
	
	public function process_incoming_message(Message $message)
	{
		if (!$message instanceof ThreadMessage) throw new ThreadException();
		if (!$message->originator() instanceof Identity) throw new ThreadException();
		$processor=$this->get_message_processor($message->code());
		if (empty($processor)) throw new ThreadException();
		$processor($message);
	}
	
	protected function get_message_processor($code)
	{
		if (array_key_exists($code, $this->message_processors))
		{
			return $this->message_processors[$code];
		}
	}
	
	public function welcome_client(Client $client)
	{
		$message=$this->create_welcome_message($client);
		$message->deliver();
	}
	
	protected function create_thread_message($code, $content, MessageOriginator $originator=null, $ts=null)
	{
		return $this->thread->create_thread_message($code, $content, $originator, $ts);
	}
	
	protected function create_welcome_message(Client $client)
	{
		$message=$this->create_thread_message(Thread::SERVER_CODE_NOTIFICATION, $this->get_welcome_nofitication($client));
		$message->set_target($client);
		return $message;
	}
	
	protected function get_welcome_nofitication(Client $client)
	{
		$template=Template_from_db::with_db_key(static::WELCOME_KEY);
		$context=new ChatContext();
		$context->set_client($client);
		$template->context=$context;
		return $template;
	}
	
	// превращает сообщение от клиента (с ББ-кодами и JS-инъекциями) в безопасное сообщение, выглядящее так, как должно.
	protected function transform_chat_message(Message $message)
	{
		if ($message->code()!==Thread::CLIENT_CODE_CHAT) throw new ThreadException();	
		$content=$message->content();
		$content['text']=$this->transform_chat_text($content['text']);
		$transformed=$this->thread->create_thread_message(Thread::CLIENT_CODE_CHAT, $content);
		return $transformed;
	}
	
	protected function transform_chat_text($text)
	{
		return htmlspecialchars($text);
	}
}

// a thread of a forum topic.
class TopicThread extends ThreadType
{
	const
		WELCOME_KEY='chat.topic_welcome';
		
	protected
		$topic;	// Entity[Topic]
	
	const
		// codes that client sends to server.
		CLIENT_CODE_JOIN='join',			// memberable requests to join
		CLIENT_CODE_LEAVE='leave',			// member requests to leave
		CLIENT_CODE_IDENTITY='ident',		// member requests identity data
		CLIENT_CODE_LOG='log',			// request message backlog
		
		// codes that server sends to clients
		SERVER_CODE_CHAT='chat',			// a chat message having text, author and related info.
		SERVER_CODE_JOIN='join',			// makes memberable a member join the thread.
		SERVER_CODE_LEAVE='leave',			// expells member
		SERVER_CODE_ATTENTION='attent',		// places an attention marker on thread tab
		SERVER_CODE_IDENTITY='ident',		// informs on identity data
		
		REASON_DENY_JOIN_OTHER		='other',
		REASON_DENY_JOIN_DUPLICATE	='duplicate',
		REASON_DENY_JOIN_BANNED		='banned',
		
		REASON_DENY_CHAT_OTHER		='other',
		REASON_DENY_CHAT_NO_VOICE	='no_voice',
		
		REASON_FORCE_LEAVE_OTHER	='other',
		REASON_FORCE_LEAVE_KICK		='kick',
		REASON_FORCE_LEAVE_THREAD_CLOSED='closed';
	
	protected function init_message_processors()
	{
		$this->message_processors+=
		[
			'chat'=>[$this, 'process_chat_message'],
			'join'=>[$this, 'process_join_request'],
			'leave'=>[$this, 'process_leave_request'],
			'ident'=>[$this, 'process_identity_request'],
			'log'=>[$this, 'process_log_request']
		];
	}
	
	protected function process_chat_message(ThreadMessage $message)
	{
	}
	
	protected function process_join_request(ThreadMessage $message)
	{
		$memberable=$message->get_memberable();
		if ($this->can_memberable_join($memberable, $reason)) $this->join_member($memberable);
		else $this->deny_join($memberable, $reason);
	}
	
	protected function can_memberable_join(Memberable $memberable, &$reason=false)
	{
		// if (array_any($this->members, function($member) use ($memberable) { return $member
	}
	
	protected function process_leave_request(ThreadMessage $message)
	{
	}
	
	protected function process_identity_request(ThreadMessage $message)
	{
	}
	
	protected function process_log_request(ThreadMessage $message)
	{
	}
	
	protected function get_welcome_nofitication(Client $client)
	{
		$template=parent::get_welcome_nofitication($client);
		$template->context->append($this->topic, 'topic');
		return $template;
	}
}

class BotThread extends TopicThread
{
	protected
		$bot_member;
}

// a thread representing a private conversaion.
class PrivateThread extends ThreadType
{
	const
		WELCOME_KEY='chat.private_welcome';
}

class ThreadException extends \Exception implements ChatException
{
	use StandardChatException;
	const ERROR_TEMPLATE_KEY='chat.thread_error';
}

?>