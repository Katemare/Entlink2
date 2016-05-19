<?

interface Commandable
{
	public function create_command_response(Message $message, $response);
}

// команда - это приказ от пользователя. соответственно, команда отвечает за распознавание приказа и его аргументов.
// команды распознаются двумя способами: каноническим, то есть не пересекающимся с другими командами (если команда распознана канонически - нет сомнений, что это она); и естественным (если команда распознана естественно, то могут быть сомнения, её ли пользователь имел в виду или правильно ли его понял распознватель).
// если команда распознана канонически, но аргументы указаны неверно, подготавливается сообщение об ошибке.
// если команда распознана естественно, но аргументы указаны неверно, то среди уточняющих вариантов к пользователю данный всё равно должен быть - либо с исправленными аргументами, либо с советом, как записать правильно.
abstract class Command implements Templater
{
	const
		DEFAULT_TICKET_CLASS		='CommandTicket',
		DEFAULT_DENY_TICKET_CLASS	='CommandTicket_denied',
		DEFAULT_TEMPLATE_KEY		='chat.render_command',
		DEFAULT_DENIED_TEMPLATE_KEY	='chat.command_denied_';
	
	protected
		$owner,
		$canonical=[],
		$natural_ex=[],
		$ticket_class		=self::DEFAULT_TICKET_CLASS,
		$deny_ticket_class	=self::DEFAULT_DENY_TICKET_CLASS,
		$template_key		=self::DEFAULT_TEMPLATE_KEY,
		$denied_template_key=self::DEFAULT_DENIED_TEMPLATE_KEY;
	
	public function __construct(Commandable $owner)
	{
		$this->owner=$owner;
	}
	
	protected function is_allowed(Message $message, &$deny_ticket) { return true; }
	
	public function recognize_canonical_form(Message $message)
	{
		$text=$message->content('text');
		if (!$text) return;
		foreach ($this->canonical as $canon)
		{
			if (preg_match('/^'.preg_quote($canon).'\b/u', $text, $m))
			{
				if (!$this->is_allowed($message, $deny_ticket)) return $deny_ticket;
				$data=mb_substr($text, mb_strlen($m[0]));
				return $this->create_ticket_canonical($message, $data);
			}
		}
	}
	
	protected function create_ticket_canonical(Message $message, $data)
	{
		$args=$this->extract_canonical_args($data, $deny_reason);
		if ($deny_reason!==null) return $this->create_deny_ticket($message, $deny_reason);
		$class=$this->ticket_class;
		return new $class($this, $message, $args);
	}
	
	protected function extract_canonical_args($data, &$deny_reason) { }
	
	public function recognize_natural_form(Message $message)
	{
		if (empty($this->natural_ex)) return;
		
		$results=[];
		$text=$message->content('text');
		foreach ($this->natural_ex as $key=>$natural)
		{
			$ticket=null;
			if (preg_match($natural, $text, $m))
			{
				if (!$this->is_allowed($message, $deny_ticket)) $ticket=$deny_ticket;
				else $ticket=$this->create_ticket_natural($message, $key, $m);
			}
			if ($ticket!==null) $results[]=$ticket;
		}
		if (empty($results)) return;
		return $results;
	}
	
	protected function create_ticket_natural(Message $message, $natural_key, $match)
	{
		$args=$this->extract_natural_args($natural_key, $match, $deny_reason);
		if ($deny_reason!==null) return $this->create_deny_ticket($message, $deny_reason);
		$class=$this->ticket_class;
		return new $class($this, $message, $args);
	}
	
	protected function extract_natural_args($natural_key, $matches, &$deny_reason)
	{
		$args=array_filter($matches, function($val, $key) { return !is_numeric($key); }, ARRAY_FILTER_USE_BOTH);
		if (empty($args)) $args=null;
		if (!$this->are_natural_args_valid($natural_key, $args, $deny_reason)) return;
		return $args;
	}
	
	protected function are_natural_args_valid($natural_key, $args, &$deny_reason)
	{
		return true;
	}
	
	protected function create_deny_ticket(Message $message, $reason)
	{
		$class=$this->deny_ticket_class;
		return new $class($this, $message, $reason);
	}
	
	public function render_ticket(CommandTicket $ticket)
	{
		if ($ticket->command()!==$this) throw new CommandException();
		return $this->describe_ticket($ticket);
	}
	
	protected function describe_ticket(CommandTicket $ticket)
	{
		if ($ticket->is_executable()) $template=Template_from_db::with_db_key($this->template_key);
		else $template=Template_from_db::with_db_key($this->denied_template_key.$ticket->deny_reason());
		$template->context=$ticket;
		return $template;
	}
	
	public function template($code, $line=[]) { }
	
	public function execute_ticket(CommandTicket $ticket)
	{
		if ($ticket->command()!==$this) throw new CommandException();
		$this->execute($ticket);
	}
	
	protected abstract function execute(CommandTicket $ticket);
	
	protected function on_deny_execution(CommandTicket $ticket, $deny_reason)
	{
		$message=$ticket->message();
		$deny_ticket=$this->create_deny_ticket($message, $deny_reason);
		$response=$this->owner->create_command_response($message, $deny_ticket->render());
		$message->respond($response);
	}
	
	protected function on_execution_exception(CommandTicket $ticket, Exception $e)
	{
		if ($e instanceof ChatException)
		{
			$response=$this->owner->create_command_response($message, $e->describe_error());
			$message->respond($response);
		}
		$this->on_deny_execution($ticket, static::DENY_UNKNOWN_ERROR);
	}
}

// команда с привязываемым вызовом.
class BoundCommand extends Command
{
	protected
		$bound;
		
	public function __construct($executor)
	{
		parent::__construct();
		$this->bound=$executor;
	}
	
	protected function execute(CommandTicket $ticket)
	{
		$bound=$this->bound;
		$bound($ticket->message()->originator(), $ticket->args());
	}
}

trait SingleWordCommand
{
	protected function extract_single_word($data, &$deny_reason)
	{
		if (!preg_match('/^\s*([\w\d_]+)\s*/u', $data, $m))
		{
			$deny_reason='no_argument';
			return;
		}
		if (mb_strlen($data)>mb_strlen($m[0]))
		{
			$deny_reason='excessive_arguments';
			return;
		}
		return $m[1];
	}
}

trait PhraseCommand
{
	protected function extract_phrase($data, &$deny_reason)
	{
		if (preg_match('/^\s*"([^\p{C}]+)"\s*/u', $data, $m))
		{
			if (mb_strlen($m[0])<$data)
			{
				$deny_reason='excessive_arguments';
				return;
			}
			return $m[1];
		}
		if (!preg_match('/^\s*([^\p{C}]+?)\s*$/u', $data, $m))
		{
			$deny_reason='no_argument';
			return;
		}
		return $m[1];
	}
}

class CommandException extends \Exception implements ChatException
{
	use StandardChatException;
	const ERROR_TEMPLATE_KEY='chat.command_error';
}

?>