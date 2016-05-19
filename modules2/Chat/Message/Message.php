<?

// objects that inherit this implementation can compose messages
abstract class MessageContent
{
	const
		ENCODE_MICROSECONDS_PRECISION=100; // нули в этом числе - то, сколько цифр после запятой сохранять при пересылке.
	
	protected
		$code,			// string[4]
		$content,		// anything
		$timestamp,		// timestamp
		$composed=null;
	
	protected function __construct($code, $content=null, $ts=null)
	{
		$this->code=$code;
		
		if ($content!==null)
		{
			if ($content instanceof Template) $content=['text'=>$content];
			elseif (is_bool($content)) $content=['text'=>(int)$content];
			elseif (is_scalar($content)) $content=['text'=>$content];
			elseif (!is_array($content)) throw new MessageException();
		}
		$this->content=$content;
		
		if ($ts===null) $this->timestamp=microtime(true); else $this->timestamp=$ts;
	}
	
	public function code() { return $this->code; }
	public function content($code=null)
	{
		if ($code===null) return $this->content;
		if (is_array($this->content) and array_key_exists($code, $this->content)) return $this->content[$code];
		return new Report_impossible('no content code');
	}
	public function content_or_default($code, $default=null)
	{
		$result=$this->content($code);
		if ($result instanceof Report) return $default;
		return $result;
	}
	public function timestamp() { return $this->timestamp; }
	
	// this basically is ChatServer's concern, but this way we can cache composed messages, which is useful for logs and such.
	public function compose_for_client()
	{
		if ($this->composed===null)
		{		
			$content=$this->content();
			$server=Server();
			
			if (is_array($content) and array_key_exists('text', $content) and $content['text'] instanceof Template)
			{
				$text=&$content['text'];
				$template=$text;
				$text=$template->now();
				if ($text instanceof Report_impossible) $text='MISSING TEMPLATE: '.$template->db_key;
				else $text=(string)$text;
			}
			
			$time=base_convert(floor(($this->timestamp()-$server->start_time())*pow(10, $server::TIMESTAMP_PRECISION)), 10, 36);
			
			$composed=$this->code().':'.$time;
			if ($content!==null) $composed.=':'.json_encode($content,  JSON_UNESCAPED_UNICODE);
			
			$this->composed=$composed;
		}
		return $this->composed;
	}
}

// Message is an "event" when someone sends a message to someone else, such as request for authrization data and so on.
// Messages only exist for communication with connected clients and client-like objects (bots). In other cases, objects can call each others' methods as necessary.
// messaging goes	client <-> server (auth and stuff)
//					member <-> thread (chat, commands)
//					identity <-> consolethread (help, create channel, notices)

class Message extends MessageContent
{	
	protected
		$originator, 	// MessageOriginator
		$target;		// MessageTarget
	
	public function __construct(MessageOriginator $originator, $code, $content=null, $ts=null)
	{
		parent::__construct($code, $content, $ts);
		$this->set_originator($originator);
	}
	
	public function set_originator(MessageOriginator $originator)
	{
		if ($this->originator!==null and $this->originator!==$originator) throw new MessageException();
		$this->originator=$originator;
	}
	
	public function set_target(MessageTarget $target)
	{
		if ($this->target!==null and $this->target!==$target) throw new MessageException();
		$this->target=$target;
	}
	
	public function originator() { return $this->originator; }
	public function target() { return $this->target; }
	
	public function deliver($target=null)
	{
		try
		{
			if ($target!==null) $this->set_target($target);
			$this->send($target);
		}
		catch (Exception $e)
		{
			$this->on_delivery_exception($e);
		}
	}
	
	public function multicast($list)
	{
		$exception=null;
		foreach ($list as $target)
		{
			try
			{
				$this->send($target);
			}
			catch (Exception $e)
			{
				if ($exception===null) $exception=new MulticastException($e);
				else $exception->addException($target, $e);
			}
		}
		if (!empty($exception)) $this->on_delivery_exception($e);
	}
	
	protected function on_delivery_exception(Exception $e)
	{
		$this->originator->on_message_bounce($this, $e);
	}
	
	public function send($target=null)
	{
		if ($target===null) $target=$this->target();
		if (empty($target)) throw new MessageException();
		$target->process_incoming_message($this);
	}
	
	public function respond(Message $response)
	{
		$originator=$this->originator();
		if (!$originator instanceof MessageTarget) $response->on_delivery_exception(new MessageException());
		else $response->deliver($originator);
	}
	
	public function mirror()
	{
		$originator=$this->originator();
		if (!$originator instanceof MessageTarget) throw new MessageException();
		$this->send($originator);
	}
}

class MessageException extends \Exception implements ChatException
{
	use StandardChatException;
	const ERROR_TEMPLATE_KEY='chat.message_error';
}

class MulticastException extends MessageException
{
	protected
		$exceptions;
	
	public function __construct(Exception $e)
	{
		parent::__construct();
		$this->exceptions=new SplObjectStorage();
		$this->addException($e);
	}
	
	public function getExceptions()
	{
		return array_values($this->exceptions);
	}
	
	public function getExceptionByTarget(MessageTarget $target)
	{
		if (array_key_exists($target, $this->exceptions)) return $this->exceptions[$target];
	}
	
	public function addException(MessageTarget $target, Exception $e)
	{
		$this->exceptions[$target]=$e;
	}
}
?>