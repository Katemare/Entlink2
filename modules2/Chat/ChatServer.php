<?

// поддерживает связь с соединениями и общие данные.
class ChatServer implements MessageNode
{
	const
		CONSOLE_THREAD_KEY=0,
	
		DEFAULT_ANON_HANDLE_MANAGER='StrictHandleManager',
	
		ERROR_TEMPLATE_KEY='chat.unknown_error',
	
		THREAD_KEY='thread',// ключ для содержимого сообщения, контекстного для треда.
		JSON_MAX_DEPTH=5,	// максимальная глубина данных в сообщении.
		
		TIMESTAMP_PRECISION=3, // сколько знаков после запятой сохранять у времени сообщений.
		
		// коды сообщений клиент -> сервер
		CLIENT_CODE_AUTH='auth',	// предоставляет данные авторизации.
		
		// коды сообщений сервер -> клиент
		SERVER_CODE_AUTH	='auth',	// запрашивает авторизацию
		SERVER_CODE_ERROR	='error',	// сообщает об ошибке.
		SERVER_CODE_PARAMS	='info',	// сообщает параметры сервера
		SERVER_CODE_CLOSE	='close',	// сообщает параметры сервера
		SERVER_CODE_IDENT	='ident',
		
		CLOSE_REASON_KEY_BASE='chat.disconnected_reason_',
		CLOSE_REASON_BAD_AUTH	='bad_auth',
		CLOSE_REASON_BANNED		='banned',
		CLOSE_REASON_AUTH_EXPIRED='auth_expired',
		CLOSE_REASON_UNKNOWN	='unknown',
		
		AUTH_TIMEOUT=15,
		
		TOKEN_ALPHABET='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
		TOKEN_LENGTH=16;
		
	
	protected
		$server,		// WebSocketServer
		$port,
		$start,
		
		$clients=[],	// of Client
		$threads=[],	// of Thread
		$topics=[],		// of Topic
		$agents=[],		// of Agent
		
		$anon_handle_manager=null,
		
		$message_processors=
		[
			'auth'=>'process_auth_message'
		];
	
	public function __construct($port)
	{
		$this->port=$port;
	}
	
	public function start()
	{
		if (!empty($this->server)) throw new LogicException();
		
		$this->server=$this->create_server();
		$this->prepare_to_start();
		$this->start=time();
		$this->server->wsStartServer('127.0.0.1', $this->port);
	}
	
	protected function create_server()
	{
		$server = new PHPWebSocket();
		$server->bind('message', [$this, 'on_client_message']);
		$server->bind('open', [$this, 'on_client_connected']);
		$server->bind('close', [$this, 'on_client_disconnected']);
		return $server;
	}
	
	protected function prepare_to_start()
	{
		$this->threads[static::CONSOLE_THREAD_KEY]=$this->create_console_thread();
	}
	
	protected function create_console_thread()
	{
		return ConsoleThread::create();
	}
	
	public function start_time() { return $this->start; }
	
	public function log($text)
	{
		$this->server->log($text);
	}
	
	public function pool()
	{
		return EntityPool::default_pool(EntityPool::MODE_OP);
	}
	
	protected function create_message_for_client(Client $client, $code, $content=null, $class='Message')
	{
		$message=new $class($this, $code, $content);
		$message->set_target($client);
		return $message;
	}
	
	#############################
	### New client connection ###
	#############################
	
	public function on_client_connected($clientID)
	{
		if (array_key_exists($clientID, $this->clients)) throw new ClientException();
		$this->clients[$clientID] = $client = new Client($clientID);
		$this->server->setTimeout(new Call([$this, 'auth_request_expired'], $client), static::AUTH_TIMEOUT, $this->auth_timeout_key($client));
		$this->create_set_params_message($client)->deliver();
		$this->create_auth_request_message($client)->deliver();
		$this->log("client $clientID connected, auth requested.");
	}
	
	protected function auth_timeout_key(Client $client) { return $client->ident().'_auth'; }
	
	protected function params_for_client() { return ['start'=>$this->start, 'ts_precision'=>static::TIMESTAMP_PRECISION]; }
	
	protected function create_set_params_message(Client $client)
		{ return $this->create_message_for_client($client, static::SERVER_CODE_PARAMS, $this->params_for_client()); }
	
	protected function create_auth_request_message(Client $client)
		{ return $this->create_message_for_client($client, static::SERVER_CODE_AUTH); }
	
	public function auth_request_expired(Client $client)
	{
		$this->log("client ".$client->id()." auth expired.");
		$this->disconnect_client($client, static::CLOSE_REASON_AUTH_EXPIRED);
	}
	
	public function get_user_agent($id, $create=true)
	{
		if ($id instanceof Entity) $id=$id->db_id;
		$key=UserAgent::ident_by_id($id);
		if (!array_key_exists($key, $this->agents))
		{
			if (!$create) return;
			return $this->agents[$key]=new UserAgent($id);
		}
		return $this->agents[$key];
	}
	
	public function get_anon_agent($token, $create=true)
	{
		$key=AnonAgent::ident_by_token($token);
		if (!array_key_exists($key, $this->agents))
		{
			if (!$create) return;
			return $this->agents[$key]=new AnonAgent($token);
		}
		return $this->agents[$key];
	}
	
	public function get_free_anon_token()
	{
		$token=null;
		while ($token===null or array_key_exists(AnonAgent::ident_by_token($token), $this->agents)) $token=$this->generate_random_token();
		return $token;
	}
	
	public function is_valid_token($token)
	{
		if (strlen($token)!=static::TOKEN_LENGTH) return false;
		if (preg_match('/[^'.static::TOKEN_ALPHABET.']/', $token)) return false;
		return true;
	}
	public function generate_random_token()
	{
		$result='';
		$len=strlen(static::TOKEN_ALPHABET);
		for ($x=1; $x<=static::TOKEN_LENGTH; $x++) $result.=static::TOKEN_ALPHABET[mt_rand(0, $len-1)];
		return $result;
	}
	
	################################
	### Closed client connection ###
	################################
	
	protected function disconnect_client(Client $client, $reason=null)
	{
		$this->create_close_message($client, $reason)->deliver();
		$this->server->wsClose($client->id());
	}
	
	protected function create_close_message(Client $client, $reason=null)
	{
		if ($reason===null) $reason=static::CLOSE_REASON_UNKNOWN;
		$template=Template_from_db::with_db_key(static::CLOSE_REASON_KEY_BASE.$reason);
		$message=new Message($this, static::SERVER_CODE_CLOSE, $template);
		$message->set_target($client);
		return $message;
	}
	
	public function on_client_disconnected($clientID, $closeStatus)
	{
		if (!array_key_exists($clientID, $this->clients)) return;
		$client=$this->clients[$clientID];
		$client->on_disconnected();
		if (!$client->is_authorized()) $this->server->clearTimeout($this->auth_timeout_key($client));
		$client->dispose();
		unset($this->clients[$clientID]);
		$this->log("client $clientID disconnected.");
	}
	
	#############################
	### Messages from clients ###
	#############################
	
	public function on_client_message($clientID, $data)
	{
		$client=$this->clients[$clientID];
		try
		{
			$this->parse_client_message($client, $data)->deliver();
		}
		catch (Exception $e)
		{
			$this->bounce_error($client, $e);
			return;
		}
	}
	
	protected function parse_client_message($client, $data)
	{
		if  (!preg_match('/^([a-z\d]{1,6}):(.+)$/', $data, $m)) throw new MessageException();
		$content=json_decode($m[2], true, static::JSON_MAX_DEPTH);
		if ($content===null) throw new MessageException();
		return $this->create_client_message($client, $m[1], $content);
	}
	
	protected function create_client_message(Client $client, $code, $content)
	{
		if (array_key_exists(static::THREAD_KEY, $content)) return $this->create_client_thread_message($client, $code, $content);
		else return $this->create_client_server_message($client, $code, $content);
	}
	
	protected function create_client_thread_message(Client $client, $code, $content)
	{
		if (!$client->is_authorized()) throw new ClientError();
		$thread_id=$content[static::THREAD_KEY];
		if (!array_key_exists($thread_id, $this->threads)) throw new ThreadException();
		$thread=$this->threads[$thread_id];
		$message=$thread->create_thread_message($code, $content, $client);
		$message->set_target($thread);
		return $message;
	}
	
	protected function create_client_server_message(Client $client, $code, $content)
	{
		$message=new Message($client, $code, $content);
		$message->set_target($this);
		return $message;
	}
	
	public function process_incoming_message(Message $message)
	{
		if ($message->originator() instanceof Client) $this->process_client_message($message);
		else throw new MessageException();
	}
	
	protected function process_client_message(Message $message)
	{
		$code=$message->code();
		if (!array_key_exists($code, $this->message_processors)) throw new MessageException();
		$method=$this->message_processors[$code];
		$this->$method($message);
	}
	
	protected function process_auth_message(Message $message)
	{
		$client=$message->originator();
		if ($client->is_authorized()) throw new ClientException();
		$client->process_auth_data($message->content());
		if (!$client->is_authorized()) $this->disconnect_client($client, static::CLOSE_REASON_BAD_AUTH);
		elseif (!$this->is_client_allowed($client, $reason)) $this->disconnect_client($client, static::CLOSE_REASON_BAD_AUTH, $reason);
		else
		{
			$this->server->clearTimeout($this->auth_timeout_key($client));
			$this->log("client ".$client->id()." authorized as ".$client->agent()->ident());
			$this->followup_client_authorization($client);
		}
	}
	
	protected function is_client_allowed(Client $client, &$reason=false) { return true; }
	// никого не надо отфильтровывать пока.
	
	protected function followup_client_authorization(Client $client)
	{
		$client->create_identity_message($this)->deliver($client);
		$this->console_thread()->welcome_client($client);
	}
	
	###########################
	### Messages to clients ###
	###########################
	
	public function send_to_clients($clients, MessageContent $message, $approve_callback=null)
	{
		foreach ($clients as $client)
		{
			if ($approve_callback!==null and !$approve_callback($client)) continue;
			$this->send_data_to_client($client, $message);
		}
	}
	
	public function send_to_client(Client $client, MessageContent $message)
	{
		$data=$message->compose_for_client();
		$this->send_data_to_client($client, $data);
	}
	
	protected function send_data_to_client(Client $client, $data)
	{
		$this->server->wsSend($client->id(), $data);
	}
	
	#########################
	###   Error handling  ###
	#########################
	
	public function handle_exception(Exception $e)
	{
		$this->log($e->getMessage());
		throw $e;
	}
	
	protected function bounce_error(Client $client, \Exception $e)
	{
		if ($e instanceof ChatException) $message=$e->create_error_message($this);
		else $message=$this->create_unknown_error_message($e);
		if (!empty($message)) $message->deliver($client);
	}
	
	public function create_unknown_error_message(\Exception $e, MessageOriginator $originator=null)
	{
		echo $e;
		die('roar');
		$template=Template_from_db::with_db_key(static::ERROR_TEMPLATE_KEY);
		if ($originator===null) $originator=$this;
		return new Message($originator, static::SERVER_CODE_ERROR, $template);
	}
	
	public function on_message_bounce(Message $message, \Exception $e)
	{
		// write severe errors to log? 
	}
	
	###############
	### Threads ###
	###############
	
	public function console_thread()
	{
		return $this->threads[static::CONSOLE_THREAD_KEY];
	}
	
	##############
	### Agents ###
	##############
	
	public function agents($approve_callback=null, $class=null)
	{
		foreach ($this->agents as $agent)
		{
			if ($class!==null and !$agent instanceof $class) continue;
			if ($approve_callback!==null and !$approve_callback($agent)) continue;
			yield $agent;
		}
	}
	public function anons($approve_callback=null)
	{
		foreach ($this->agents($approve_callback, 'AnonAgent') as $agent) yield $agent;
	}
	public function bots($approve_callback=null)
	{
		foreach ($this->agents($approve_callback, 'Bot') as $agent) yield $agent;
	}
	public function users($approve_callback=null)
	{
		foreach ($this->agents($approve_callback, 'UserAgent') as $agent) yield $agent;
	}
	
	public function anon_handle_manager()
	{
		if (empty($this->anon_handle_manager)) $this->set_anon_handle_manager(static::DEFAULT_ANON_HANDLE_MANAGER);
		return $this->anon_handle_manager;
	}
	public function set_anon_handle_manager($class)
	{
		if (!string_instanceof($class, 'HandleManager')) throw new LogicException();
		$this->anon_handle_manager=new $class([$this, 'anons']);
	}
}

// имеет уникальный ключ, по которому можно идентифицировать в разных массивах.
interface HasIdent
{
	public function ident();
}

?>