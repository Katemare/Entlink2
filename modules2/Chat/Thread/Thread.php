<?

interface ThreadTitle
{
	public function title();
	
	public function set_title($title);
	
	public static function is_title_valid($title, &$deny_reason);
}

interface ThreadFounder
{
	public function founder();
	public function set_founder(Agent $founder);
}

interface JoinableThread
{
	public function can_join(Memberable $memberable, &$deny_reason);
	public function join(Memberable $memberable);
}

interface LoggedThread
{
	public function get_log(Memberable $pov, $from=null, $to=null, $max=null);
}

// a thread is a collection of members, a message log and a set of rules by which they trade messages.
class Thread implements MessageNode, HasIdent, Interface_proxy
{
	use Object_id, IgnoreBouncedMessages;

	const
		SERVER_CODE_NOTIFICATION='notify',	// html message
		
		CLIENT_CODE_CHAT='chat';
	
	protected
		$type,			// ThreadType
		$log=[];		// of LoggedMessage
	
	public function __construct(ThreadType $type)
	{
		$this->generate_object_id();
		$this->type=$type;
	}
	
	public function implements_interface($name)
	{
		return $this->type instanceof $name;
	}
	
	public function ident()
	{
		if ($this->type instanceof HasIdent) return $this->type->ident();
		return $this->object_id;
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
	
	public function create_thread_message($code, $content, MessageOriginator $originator=null, $ts=null)
	{
		if ($originator===null) $originator=$this;
		return new ThreadMessage($originator, $code, $content, $this, $ts);
	}
}

// controls how the thread behaves. by having it a separate object, it's possible to transform a private thread to a conversation (a topic?).
abstract class ThreadType implements MessageTarget
{
	use StandardMessageProcessor;
	
	const
		WELCOME_KEY='chat.thread_welcome',
		DEFAULT_MAX_SENT_LOG=100;
		
	protected
		$thread;	// Thread
	
	public function __construct() { }
	
	public static function create()
	{
		$type=new static();
		$thread=new Thread($type);
		$type->init($thread);
		return $thread;
	}
	
	protected function init(Thread $thread)
	{
		$this->thread=$thread;
		$this->init_message_processors();
	}
	
	protected function init_message_processors()
	{
		$this->message_processors+=
		[
			'chat'=>[$this, 'process_chat_message'],
			'ident'=>[$this, 'process_identity_request'],
			'log'=>[$this, 'process_log_request'],
			'active'=>[$this, 'process_activate_message'],
			'ident'=>[$this, 'process_ident_message']
		];
	}
	
	protected function process_chat_message(ThreadMessage $message)
	{
		$message->respond($this->transform_chat_message($message));
	}
	
	protected function process_identity_request(ThreadMessage $message)
	{
		Server()->console_thread()->process_incoming_message($message);
	}
	
	protected function get_log()
	
	protected function process_log_request(ThreadMessage $message)
	{
		$log=[];
		$from=$message->content_or_default('from', 0);
		$to=$message->content_or_default('to', time());
		$max=$message->content_or_default('max', static::DEFAULT_MAX_SENT_LOG);
		$excess=0;
		$source=$this->get_log($message->originator());
		for ($item=end($this->log); $item!==false; $item=prev($this->log);
		{
			if ($item->timestamp()<$from) break;
			if ($item->timestamp()>$to) continue;
			if (count($log)>=$max) $excess++;
			else $log[]=$message;
		}
		$log=array_reverse($log);
		
	}
	
	protected function is_message_processable(Message $message)
	{
		if (!$message instanceof ThreadMessage) return false;
		if (!$message->originator() instanceof Identity) return false;
		return true;
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
	public function transform_chat_message(Message $message)
	{
		if ($message->code()!==Thread::CLIENT_CODE_CHAT) throw new ThreadException();	
		$content=$message->content();
		$content['text']=$this->transform_chat_text($content['text']);
		$transformed=$this->thread->create_thread_message(Thread::CLIENT_CODE_CHAT, $content);
		return $transformed;
	}
	
	public function transform_chat_text($text)
	{
		return htmlspecialchars($text);
	}
}

class ThreadException extends \Exception implements ChatException
{
	use StandardChatException;
	const ERROR_TEMPLATE_KEY='chat.thread_error';
}

?>