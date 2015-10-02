<?
namespace Pokeliga\Interactive;

// соответствует подключению. подключение может быть анонимным, но самое главное - оно не существует, когда клиент отключается. одна из задач чата - соотносить 
class ChatClient
{
	public
		$master,
		$client_id,
		$auth=false,
		$user=false,	// false или объект ChatMember
		$introduced=false,
		$close_reason=false;
	
	public function __construct($client_id, $master)
	{
		$this->client_id=$client_id;
		$this->master=$master;
	}
	
	public function close($error_code=null)
	{
		$this->close_reason=$error_code;
		$this->send($this->master->compose_error($error));
		$this->master->server->wsRemoveClient($this->client_id); // после этого чат ещё получит событие 'close'
	}
	
	// вызывается после того, как чат получает событие о расконнекте клиента. данный объект должен уничтожиться, оставив за собой, возможно, Persistent'ы.
	public function disconnected($status)
	{
		if (!$this->auth) $this->master->clear_timeout('auth'.$this->client_id);
		else $this->broadcast_closed();
		/*
		варианты статуса:
			PHPWebSocket::WS_STATUS_PROTOCOL_ERROR	- ошибка протокола.
			PHPWebSocket::WS_STATUS_GONE_AWAY		- остановка сервера.
			PHPWebSocket::WS_STATUS_TIMEOUT			- пользователь не отвечает на пинг.
			PHPWebSocket::WS_STATUS_MESSAGE_TOO_BIG	- прислано слишком длинное сообщение.
			PHPWebSocket::WS_STATUS_NORMAL_CLOSE	- пользователь вышел или отключён вручную.
		*/
	}
	
	public function send($code, $content=null)
	{
		if ($content===null) $message=$code;
		else $message=$this->master->compose_message($code, $content);
		$this->master->server->wsSend($this->client_id, $message);
	}
	
	public function send_auth()
	{
		$master=$this->master;
		$this->send($master::SERVER_CODE_AUTH);
		$master->set_timeout([$this, 'auth_timeout'], $master::TIMEOUT_AUTH, 'auth'.$this->client_id);
	}
	
	public function auth_timeout()
	{
		$this->close(static::ERROR_AUTH_TIMEOUT);
	}
	
	// возвращает true - новый, ранее не подсоединявшийся игрок; false - старый игрое вернулся; или числовой код ошибки.
	public function attempt_auth($auth_data)
	{
		$master=$this->master;
		if (empty($auth_data['auth'])) return $master::ERROR_BAD_AUTH;
		$auth_types=$this->master->get_auth_types();
		if (!array_key_exists($auth_data['auth'], $auth_types)) return $master::ERROR_BAD_AUTH;
		
		$auth_type=$auth_types[$auth_data['auth']];
		return $auth_type::auth($auth_data, $master);
	}
		
	public function auth($auth_data)
	{
		if (!empty($auth_data['watcher'])) $result=$this->auth_as_watcher($auth_data);
		if (!empty($auth_data['anon'])) $result=$this->auth_as_anon($auth_data);
		elseif (empty($auth_data['token'])) $result=static::ERROR_BAD_TOKEN;
		else
		{
			$user=$this->user_by_token($auth_data['token']);
			if ($user instanceof \Report_impossible) $result=static::ERROR_BAD_TOKEN;
			else $result=$this->auth_as_user($user, $auth_data);
		}
		
		if ($result===true)
		{
			$this>auth=true;
			$result=$this->master->member_authed($this);
		}
		if ($result===true)
		{
			$this->user->connected();
			$this->send_intro();
			$this->broadcast_joined();
		}
		else $this->close($result);
		return $result;
	}
	
	public function user_by_token($token)
	{
		$user=$this->master->pool()->entity_from_provider(['auth_by_token', $token], 'User');
		$user->verify();
		if ($user->state!==Entity::STATE_VERIFIED_ID) return $this->sign_report(new \Report_impossible('bad_token'));
		return $user;
	}
	
	public function auth_as_watcher($auth_data)
	{
		$this->user=$this->master->persistent_watcher();
		return true;
	}
	
	public function auth_as_anon($auth_data)
	{
		$master=$this->master;
		if (empty($auth_data['anon_key'])) $anon_key=null;
		else $anon_key=$auth_data['anon_key'];
		$anon=$master->persistent_anon($auth_data['anon_key']);
		
		$returning=!empty($anon->handle());
		if (!$returning) // новый аноним.
		{
			$error=null;
			if (!array_key_exists('anon_handle', $auth_data)) $error=$master::ERROR_BAD_ANON_HANDLE;
			elseif (!$anon::valid_handle($auth['anon_handle'])) $error=$master::ERROR_BAD_ANON_HANDLE;
			elseif (!$master->is_anon_handle_free($auth['anon_handle'])) $error=$master::ERROR_ANON_HANDLE_TAKEN;
			if (!empty($error))
			{
				$anon->erase();
				return $error;
			}
			$anon->anon_handle=$auth_data['anon_handle'];
		}
		$this->user=$anon;
		return true;
	}
	
	// возвращает true, если авторизован новый пользователь; false, если это старый под новым соединением; или цифровой код ошибки.
	public function auth_as_user(/* сущность User, а не ChatUser */ $user, $auth_data)
	{
		$master=$this->master;
		$chatuser=$master->persistent_user($user);
		
		$this->user=$chatuser;
		return true;
	}
	
	public function broadcast_joined()
	{
		if (!$this->auth) return; // сюда должны попадать только авторизованные клиенты.
		if ($this->introduced) return; // уже сообщили.
		$this->master->broadcast($this->compose_joined(), null, $this->client_id);
		$this->introduced=true;
	}
	
	public function broadcast_closed()
	{
		if (!$this->auth) return; // сюда должны попадать только авторизованные клиенты.
		if (!$this->introduced) return; // не сообщали.
		$this->master->broadcast($this->compose_closed(), null, $this->client_id);
	}
	
	public function compose_joined()
	{
		$master=$this->master;
		if ($this->is_anon()) return $this->master->compose_message($master::SERVER_CODE_JOIN, $master::JOIN_ANON, $this->user);
		else return $this->master->compose_message($master::SERVER_CODE_JOIN,  $master::JOIN_USER, $this->user);
	}
	
	public function compose_closed()
	{
		$master=$this->master;
		if (!empty($this->close_reason)) return $this->master->compose_error($this->close_reason);
		elseif ($client->is_anon()) return $this->master->compose_message($master::SERVER_CODE_CLOSE, $master::CLOSE_ANON, $this->user);
		else $this->master->compose_message($master::SERVER_CODE_CLOSE,  $master::CLOSE_USER, $this->user);
	}
	
	public function is_user()
	{
		return $this->auth && $this->user instanceof ChatUser;
	}
	
	public function is_anon()
	{
		return $this->auth && $this->user instanceof ChatAnon;
	}
	
	public function is_watcher()
	{
		return $this->auth && $this->user instanceof ChatWatcher;
	}
}

?>