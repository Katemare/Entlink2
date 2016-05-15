<?

// эти объекты могут посылать сообщения.
interface MessageOriginator
{
	// этот метод вызывается, если сообщение невозможно доставить.
	public function on_message_bounce(Message $message, \Exception $e);
}

// игнорировать ошибки доставки.
trait IgnoreBouncedMessages
{
	public function on_message_bounce(Message $message, \Exception $e) { }
}

// превращать ошибки доставки в сообщения об ошибке и доставлять их себе.
trait SendMeErrorOnBouncedMessage
{
	public function on_message_bounce(Message $message, \Exception $e)
	{
		if ($message->originator()===$this and $message->code()===ChatServer::SERVER_CODE_ERROR) return; // чтобы не было бесконечного цикла.
		
		if ($e instanceof ChatException) $message=$e->create_error_message($this);
		else $message=Server()->create_unknown_error_message($e, $this);
		$message->deliver($this);
	}
}

// эти объекты могут получать сообщения.
interface MessageTarget
{
	public function process_incoming_message(Message $message);
}

// эти объекты могут и получать, и отправлять сообщения.
// FIXME: нужно ли это объединение, если у него нет никаких своих черт?
interface MessageNode extends MessageOriginator, MessageTarget { }

?>