<?

// представляет соединение между сервером и клиентом.
// возможно несколько соединений от одного пользователя, но не обратное.
class Client implements MessageNode, Identity, HasIdent, AgentLink
{
	use SendMeErrorOnBouncedMessage, IdentityTemplater;
	
	private
		$id,		// int
		$agent,		// ClientAgent
		$active_thread, // Thread
		$connected=true;
	
	public function __construct($id)
	{
		$this->id=$id;
	}
	
	public function id() { return $this->id; }
	public function agent()
	{
		if (!$this->is_authorized()) throw new ClientException();
		return $this->agent;
	}
	public function set_active_thread(Thread $thread) { $this->active_thread=$thread; }
	public function active_thread() { return $this->active_thread; }
	
	public function is_authorized() { return $this->agent!==null; }
	
	public function process_auth_data($data)
	{
		if (array_key_exists('session', $data)) $this->authorize_by_session($data['session']);
		elseif (array_key_exists('token', $data)) $this->authorize_by_token($data['token']);
		elseif (!empty($data['blank'])) $this->authorize_blank();
		else throw new ClientError();
	}
	
	protected function authorize_by_session($session)
	{
		require_once(Engine()->server_address('compatible2/users/user_def.php'));
		$data=\Pokeliga\Auth\get_user_data_by_session($session);
		if ($data===false) $this->authorize_blank();
		else $this->authorize_as_user($data['id']);
	}
	
	protected function authorize_by_token($token)
	{
		if (!Server()->is_valid_token($token)) $this->authorize_blank();
		else $this->authorize_as_anon($token);
	}
	
	protected function authorize_blank()
	{
		$server=Server();
		$token=$server->get_free_anon_token();
		$message=new Message($this, $server::SERVER_CODE_AUTH, ['token'=>$token]);
		$message->deliver($this);
		$this->authorize_as_anon($token);
	}
	
	protected function authorize_as_user($id)
	{
		$this->agent=Server()->get_user_agent($id);
		$this->agent->register_client($this);
	}
	
	protected function authorize_as_anon($token)
	{
		$this->agent=Server()->get_anon_agent($token);
		$this->agent->register_client($this);
	}
	
	public function on_disconnected()
	{
		$this->disconnected=true;
	}
	public function is_connected() { return $this->connected; }
	
	##########################
	### For ClientIdentity ###
	##########################
	
	public function send_clientward(MessageContent $message)
	{
		Server()->send_to_client($this, $message);
	}
	
	###############################
	### MessageTarget interface ###
	###############################
	
	public function process_incoming_message(Message $message)
	{
		$this->send_clientward($message);
	}
	
	##########################
	### HasIdent interface ###
	##########################
	
	public function ident() { return 'c'.$this->id; }
	
	##########################
	### Identity interface ###
	##########################
	
	public function handle()
	{
		if ($this->is_authorized()) return $this->agent->handle();
		throw new IdentityException();
	}
	
	public function identity_data()
	{
		if ($this->is_authorized()) return $this->agent->identity_data();
		throw new IdentityException();
	}
	
	################################
	### AgentLink interface ###
	################################
	
	public function get_agent()
	{
		if (!$this->is_authorized()) throw new IdentityException();
		if (!$this->agent instanceof Memberable) throw new IdentityException();
		return $this->agent;
	}
	
	public function get_memberable()
	{
		return $this->get_agent();
	}
	
	##########################
	### Garbage collection ###
	##########################
	
	public function dispose()
	{
		unset($this->agent);
		unset($this->active_thread);
	}
	
}

class ClientException extends \Exception implements ChatException
{
	use StandardChatException;
	const ERROR_TEMPLATE_KEY='chat.client_error';
}
?>