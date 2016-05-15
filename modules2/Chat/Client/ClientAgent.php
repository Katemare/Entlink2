<?

// represents a person, either a registered user or not.
// multiple connections per identity are possible.
abstract class ClientAgent extends Agent
{
	use SendMeErrorOnBouncedMessage;
	
	protected
		$clients=[];	// of Client
		
	// sends message to all connections
	public function send_clientward(MessageContent $message)
	{
		foreach ($this->clients as $client) $client->send_clientward($message);
	}
	
	// process message from client
	public function process_client_message(MessageContent $message, Client $client)
	{
	}
	
	public function process_incoming_message(Message $message)
	{
		$this->send_clientward($message);
	}
}

// a registered user
class UserAgent extends ClientAgent
{
	protected
		$user;	// Entity[User]
	
	const
		AGENT_GROUP='user';
	
	public function __construct($user)
	{
		if (is_numeric($user)) $user=Server()->pool()->entity_from_db_id($user);
		$this->user=user;
	}
	
	public function ident() { return static::ident_by_id($this->user->db_id); }
	public function handle() { return $this->user->value('uslogin'); }
	
	public static function ident_by_id($id) { return 'u'.$id; }
}

// an unregistered person
class AnonAgent extends ClientAgent
{
	const
		AGENT_GROUP='anon';
	
	protected
		$token,		// hash
		$handle; 	// string
	
	public function __construct($token)
	{
		$this->token=$token;
	}
	
	public function ident() { return static::ident_by_token($this->token); }
	public function handle()
	{
		if ($this->handle===null)
		{
			$this->handle=$this->generate_handle();
		}
		return $this->handle;
	}
	
	protected function generate_handle()
	{
		return Server()->anon_handle_manager()->generate_handle($this);
	}
	
	public static function ident_by_token($token) { return 'a'.$token; }
	
	public function collides_with_handle($handle)
	{
		if ($this->handle===null) return false;
		return parent::collides_with_handle($handle);
	}
}

?>