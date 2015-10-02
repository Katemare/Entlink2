<?
namespace Pokeliga\Interactive;

// базово изображает из себя чат, но в общем смысле служит основой для различных интерактивных игр. поскольку все они в онлайне с возможным присутствием зрителей, то функционал чатовых сообщений не помешает.
class Chat implements Template_context
{
	use Task_entity_methods;
	
	const
		CLIENT_CLASS	='ChatClient',		// отвечает за соединение.
		USER_CLASS		='ChatUser',		// отвечает за пользователя, зарегенного на сайте. 
		ANON_CLASS		='ChatAnon',		//
		WATCHER_CLASS	='ChatWatcher',	//
		MAX_CLIENTS		=50,
		MAX_ANON		=10,
		MAX_USERS		=50,
		
		// состояния клиента.
		CLIENT_PREAUTH	=1,	// клиент подсоединился, но ещё не представился (все варианты ниже предполагают представление)
		CLIENT_AUTH		=2, // клиент уже представился.
		CLIENT_ANON		=3, // клиент представился как аноним.
		CLIENT_USER		=4, // клиент представился как пользователь.
		
		ERROR_TOO_MANY_CLIENTS		=1, // слишком много подключений. у сервера тоже есть такой функционал, но хочется единообразия.
		ERROR_TOO_MANY_ANONS		=2, // слишком много анонимов, нельзя подключить нового.
		ERROR_TOO_MANY_USERS		=3, // слишком много авторизованных пользователей.
		ERROR_NO_TOKEN				=4, // нет прислано данных для авторизации.
		ERROR_BAD_TOKEN				=5, // данные для авторизации не подходят.
		ERROR_AUTH_TIMEOUT			=6,	// слишком долго не получаются данные авторизации пользователя.
		ERROR_AUTH_FAILED			=7, // авторизация не удалась по неизвестной причине.
		ERROR_SERVER_EXPIRED		=8,	// игра может длиться не более заданного времени (пока что - потому что это не MUD, а события в экспедициях, и потому что не ясно, насколько код подготовлен экономить память)
		ERROR_DENY_ANON				=9,	// анонимам нельзя.
		ERROR_DENY_USERS			=10,// пользователям нельзя.
		ERROR_BAD_COMMAND			=11,// плохая команда управления чатом.
		ERROR_UNRIGHTFUL_COMMAND	=12,// нет прав на команду.
		ERROR_BAD_COMMAND_TARGET	=13,// неправильные параметры команды.
		ERROR_BANNED				=14,// нельзя зайти, пользователь забанен.
		ERROR_UNKNOWN_MESSAGE		=98,// сервер пытается отправиться сообщение с неизвестным кодом шаблона.
		ERROR_UNKNOWN				=99,// неизвестный код ошибки.
		
		CLOSE_REPLACED_CONNECTION	=100,// тот же пользователь подконнектился в другой вкладке.
		CLOSE_ANON					=101,// аноним отключился.
		CLOSE_USER					=102,// пользователь-наблюдатель отключился.
		CLOSE_KICKED				=103,// выкинут админом.
		CLOSE_BANNED				=104,// забанен.
		
		JOIN_ANON					=200,// присоединился новый аноним.
		JOIN_USER					=201,// присоединился пользователь-наблюдатель.
		
		NOTICE_ALONE				=300,// предупреждение, что чат никто не слушает ввиду отсутствия других игроков.
		
		CODE_COUNT=4, // сколькими символами ограничиваются серверные и клиентские коды.
		
		SERVER_CODE_AUTH	='auth', // просит конкретного пользователя представиться.
		SERVER_CODE_CHAT	='chat', // сообщает окружающим, что пользователь сказал кое-что по чату.
		//ФОРМАТ: chat:<client_id>:<сообщение>
		SERVER_CODE_JOIN	='join', // сообщает пользователям о присоединении пользователя.
		// ФОРМАТ: join:<json>
		SERVER_CODE_CLOSE	='clos', // сообщает пользователям об уходе пользователя.
		// ФОРМАТ: clos:<client_id>
		SERVER_CODE_ERROR	='erro', // сообщает об ошибке.
		// ФОРМАТ: erro:<сообщение>
		SERVER_CODE_NOTICE	='note', // персональное извещение от сервера.
		// ФОМАТ: note:<сообщение>
		
		CLIENT_CODE_AUTH	='auth',
		// ФОРМАТ: auth:token=<token>, ИЛИ auth:anon=1, или auth:token=<token>&play=1
		CLIENT_CODE_CHAT	='chat',
		// ФОРМАТ: chat:<сообщение>
		CLIENT_CODE_COMMAND	='comm',
		
		COMMAND_KICK		='kick', // обрывает указанное соединение.
		COMMAND_BAN			='bann', // запрещает указанному пользователю подсоединяться заданный срок.
		COMMAND_UNBAN		='uban', // снимает запрет.
		COMMAND_FINISH		='exit', // завершает чат.
		COMMAND_MUTE		='mute', // лишает пользователя права говорить.
		COMMAND_VOICE		='voic', // даёт пользователю право говорить.
		COMMAND_MODERATED	='modr', // по умолчанию права голоса нет.
		COMMAND_RESTRICTED	='rest', // по умолчанию у анонимов нет права голоса, у пользователей есть.
		COMMAND_UNMODERATED	='umod', // по умолчанию право голоса есть.
		
		MODERATION_ON		=1, // по умолчанию права голоса нет.
		MODERATION_OFF		=2, // по умолчанию право голоса есть.
		MODERATION_ANON		=3, // по умолчанию у анонимов нет права голоса, у пользователей есть.
		MODERATION_DEFAULT	=self::MODERATION_OFF,
		
		TIMEOUT_AUTH		=300,	// 5 минут - если пользователь не авторизовался за это время, его отключает.
		TIMEOUT_ANON		=900,	// 15 минут - время хранения данных анонима.
		TIMEOUT_SESSION		=3600;	// 1 час - сколько по умолчанию живёт чат (пока мы не будем уверены в надёжности обращения с памятью)
	
	public
		$good_codes=
		// соответствия код клиентского сообщения => вызов.
		[
			self::CLIENT_CODE_AUTH		=>'user_auth',
			self::CLIENT_CODE_CHAT		=>'user_chat',
			self::CLIENT_CODE_COMMAND	=>'user_command',
		],
		
		$good_commands=
		[
			self::COMMAND_KICK		='client_comand_kick',
			self::COMMAND_BAN		='client_comand_ban',
			self::COMMAND_UNBAN		='client_comand_unban',
			self::COMMAND_FINISH	='client_comand_exit',
			
			// отличаются потому, что пользователя можно лишить/одарить голосом даже до того, как он впервые войдёт, а анонима - только пока тот присутствует, потому что анонимы отличаются только соединением.
			self::COMMAND_MUTE_ANON	='client_comand_mute_anon',
			self::COMMAND_VOICE_ANON='client_comand_voice_anon',
			self::COMMAND_MUTE_USER	='client_comand_mute_user',
			self::COMMAND_VOICE_USER='client_comand_voice_user',
			
			self::COMMAND_MODERATED	='client_comand_mode_moderated',
			self::COMMAND_RESTRICTED='client_comand_mode_restricted',
			self::COMMAND_UNMODERATED='client_comand_unmoderated'
		],
		
		$template_keys=
		[
			self::ERROR_TOO_MANY_ANONS		='interactive.too_many_anons',
			self::ERROR_NO_TOKEN			='interactive.auth_error',
			self::ERROR_BAD_TOKEN			='interactive.auth_error',
			self::ERROR_SERVER_EXPIRED_EXPIRED ='interactive.game_expired',
			self::ERROR_DENY_ANON			='interactive.no_anons',
			self::ERROR_UNKNOWN_MESSAGE		='interactive.unknown_server_message',
			self::ERROR_UNKNOWN				='interactive.unknown_error',
			
			self::CLOSE_REPLACED_CONNECTION	='interactive.replaced_connection',
			self::CLOSE_ANON				='interactive.anon_disconnected',
			self::CLOSE_USER				='interactive.user_disconnected',
			
			self::JOIN_ANON					='interactive.anon_connected',
			self::JOIN_USER					='interactive.user_connected',
			
			self::NOTICE_ALONE				='interactive.chat_alone'
		],
	
		$server,
		$port,
		$started,
		$timeouts=[],
		$pool,
		
		$moderation_mode=self::MODERATION_OFF,
		$user_data=[],		// соответствия айди_пользователя => объект ChatUser
		$anon_data=[],		// соответствия ключ анонима => объект ChatAnon
		$clients=[],	// соответствия client_id => объект ChatClient
		
		// счётчики, нужные отчасти для быстроты, отчасти потому, что вместо массивов используются генераторы.
		$client_count	=0, // можно обращаться к серверу, но хочется единообразия ведения счётчиков.
		$auth_count		=0,
		$anon_count		=0,
		$users_count	=0,
		
		// генераторы:
		$auth_clients,		// авторизовавшиеся клиенты
		$anon_clients, 		// анонимы
		$user_clients;		// пользователи
		
	public function __construct($port)
	{
		$this->port=$port;
		
		// генераторы
		$this->auth_clients=[$this, 'gen_auth'];
		$this->anon_clients=[$this, 'gen_anons'];
		$this->user_clients=[$this, 'gen_users'];
	}
	
	// STUB
	public function pool()
	{
		if ($this->pool===null) $this->pool=EntityPool::default_pool(EntityPool::MODE_OPERATION);
		return $this->pool;
	}
	
	##########################
	### Работа с клиентами ###
	##########################
	
	public function gen_auth()
	{
		foreach ($this->clients as $client_id=>$client)
		{
			if ($client->auth===true) yield $client_id=>$client;
		}
	}
	
	public function gen_anons()
	{
		foreach ($this->auth_clients as $client_id=>$client)
		{
			if ($client->is_anon()) yield $client_id=>$client;
		}
	}
	
	public function gen_users()
	{
		foreach ($this->auth_clients as $client_id=>$client)
		{
			if ($client->is_user()) yield $client_id=>$client;
		}
	}
	
	public function persistent_user($usid, $create=true)
	{
		if ($usid instanceof \Pokeliga\Entity\Entity) $usid=$usid->db_id;
		if (!array_key_exists($usid, $this->user_data))
		{
			if (!$create) return false;
			$class=$this->USER_CLASS;
			$chatuser=new $class($usid, $this);
			$this->user_data[$usid]=$chatuser;
		}
		return $this->user_data[$usid];
	}
	
	public function persistent_anon($key, $create=true)
	{
		if (!array_key_exists($key, $this->anon_data))
		{
			if (!$create) return false;
			if ( (empty($key)) || (!ChatAnon::valid_key($key)) ) $key=$this->generate_anon_key();
			$class=$this->ANON_CLASS;
			$anon=new $class($key, $this);
			$this->anon_data[$key]=$anon;
		}
		return $this->anon_data[$key];
	}
	
	public function remove_persistent_anon($key)
	{
		if (empty($this->anon_data[$key])) return;
		if ($this->anon_data[$key]->is_connected()) return false;
		unset($this->anon_data[$key]);
	}
	
	public function generate_anon_key()
	{
		while (array_key_exists($key=ChatAnon::generate_random_key()) { }
	}
	
	public function is_anon_handle_free($handle)
	{
		foreach ($this->anon_data as $anon)
		{
			if ($anon->handle===$handle) return false;
		}
		return true;
	}
	
	#####################
	### Основной цикл ###
	#####################
	
	public function start()
	{
		$this->started=time();
		
		$this->server = new PHPWebSocket();
		$this->setup_server();
		if (static::TIMEOUT_SESSION>0) $this->set_timeout([$this, 'session_timeout'], static::TIMEOUT_SESSION, 'session');
		
		$this->server->wsStartServer('127.0.0.1', $this->port);
	}
	
	public function setup_server()
	{
		$this->server->bind('message', [$this, 'client_messaged']);
		$this->server->bind('open', [$this, 'client_connected']);
		$this->server->bind('close', [$this, 'client_disconnected']);
	}
	
	public function session_timeout()
	{
		$this->stop(static::ERROR_SERVER_EXPIRED);
	}
	
	public function stop($error=null)
	{
		if ($error!==null)
		{
			$composed=$this->compose_error($error);
			$this->broadcast($composed);
		}
		$this->server->wsStopServer();
		$this->finish();
	}
	
	// сохранение игры, подведение итогов и так далее.
	public function finish() { }
	
	public function compose_message($code, $content)
	{
		return $code.':'.$content;
	}
	
	public function client_messaged($client_id, $message, $messageLength, $binary)
	{
		$client=$this->clients[$client_id];
		$this->parse_message($message, $code, $content);
		if (array_key_exists($code, $this->good_codes)) $this->bad_client_message($client, $code, $content);
		$call=$this->good_codes[$message];
		$call($client, $content);
	}
	
	##########################
	### Механизм сообщений ###
	##########################
	
	// создаёт создаёт сообщение как шаблон на основе кода, с заданным контекстом (по умолчанию - данным интерактивом).
	public function compose_special_message($key_code, $message_code, $context=null, $unknown_code=self::ERROR_UNKNOWN)
	{
		if (array_key_exists($key_code, $this->template_keys)) $use_key=$key_code;
		elseif (array_key_exists($unknown_code, $this->template_keys)) $use_key=$unknown_code;
		else $use_key=static::ERROR_UNKNOWN_MESSAGE;
		$key=$this->template_keys[$use_key];
		
		$template=Template_from_db::with_db_key($key);
		if ($context!==null) $template->context=$context;
		else $template->context=$this;
		$template->complete();
		return $this->compose_message($message_code, $template->resolution);
	}
	
	public function compose_error($error_code)
	{
		return $this->compose_special_message($error_code, static::SERVER_CODE_ERROR);
	}
	
	public function parse_message($message, &$code, &$content)
	{
		$code=substr($message, 0, static::CODE_COUNT);
		$content=substr($message, static::CODE_COUNT+1); // после кода ставится : просто для читемости дебаговых сообщений.
	}
	
	public function send_to($client_id, $code, $content=null, ...$except) // если $content===null, то в $code уже готовое сообщение.
	{
		if (is_array($client_id))
		{
			foreach ($client_id as $id)
			{
				if (in_array($id, $except)) continue;
				if (!array_key_exists($id, $this->clients)) continue;
				$client=$this->clients[$id];
				if (in_array($client, $except, true)) continue;
				$client->send($code, $content);
			}
		}
		elseif (is_callable($client_id)) /* генератор */
		{
			foreach ($client_id as $id=>$client)
			{
				if (in_array($client, $except, true)) continue;
				if (in_array($id, $except)) continue;
				$client->send($code, $content);
			}
		}
		elseif ($client_id instanceof ChatClient) $client_id->send($code, $content);
		elseif (!array_key_exists($client_id, $this->clients)) return false;
		else $this->clients[$client_id]->send($code, $content);
	}
	
	public function broadcast($code, $content=null, ...$except)
	{
		if (empty($code)) return;
		$this->send($this->auth_clients, $code, $content, ...$except);
	}
	
	########################
	### Механизм событий ###
	########################
	
	public function set_timeout($call, $timeout, $ident=null)
	{
		$timeout_id=$this->server->setTimeout($call, $timeout);
		if ($ident!==null) $this->timeouts[$ident]=$timeout_id;
	}
	
	public function reset_timeout($call, $timeout, $ident)
	{
		$this->clear_timeout($ident);
		$this->set_timeout($call, $timeout, $ident);
	}
	
	public function clear_timeout($ident)
	{
		if (!array_key_exists($ident, $this->timeouts)) return;
		$timeout_id=$this->timeouts[$ident];
		$this->server->clearTimeout($timeout_id);
		unset($this->timeouts[$ident]);
	}
	
	##############################################
	### Подключения, отключения, инициализация ###
	##############################################
	
	public function client_connected($client_id)
	{
		$class=static::CLIENT_CLASS;
		$client=new $class($client_id, $this);
		$this->client_count++;
		if ($this->client_count>static::MAX_CLIENTS) $client->close(static::ERROR_TOO_MANY_CLIENTS)
		else
		{
			$this->clients[$client_id]=$client;
			$client->send_auth();
		}
	}
	
	// реакция на событие от сервера - значит, техническое отключение, уход со страницы или реакция на предыдущий метод close().
	public function client_disconnected($client_id, $status)
	{
		$client=$this->clients[$client_id];
		$this->client_count--;
		if ($client->auth)
		{
			$this->auth_count--;
			if ($client->is_anon()) $this->anon_count--;
			if ($client->is_user()) $this->users_count--;
		}
		$this->disconnected($status);
		unset($this->clients[$client_id]);
		/*
		варианты статуса:
			PHPWebSocket::WS_STATUS_PROTOCOL_ERROR	- ошибка протокола.
			PHPWebSocket::WS_STATUS_GONE_AWAY		- остановка сервера.
			PHPWebSocket::WS_STATUS_TIMEOUT			- пользователь не отвечает на пинг.
			PHPWebSocket::WS_STATUS_MESSAGE_TOO_BIG	- прислано слишком длинное сообщение.
			PHPWebSocket::WS_STATUS_NORMAL_CLOSE	- пользователь вышел.
		*/
	}
	
	public function client_auth($client_id, $content)
	{
		$auth_data=parse_str($content);
		$client=$this->clients[$client_id];
		$result=$client->auth($auth_data);
		if ($client->auth)
		{
			$this->auth_count++;
			if ($client->is_anon())
			{
				$this->anon_count++;
				if ($this->anon_count>static::MAX_ANON) $client->close(static::ERROR_TOO_MANY_ANONS);
			}
			elseif ($client->is_user())
			{
				$this->users_count++;
				if ($this->users_count>static::MAX_USERS) $client->close(static::ERROR_TOO_MANY_USERS);
			}
		}
		elseif (is_numeric($result)) $client->close($result);
		else $client->close(static::ERROR_AUTH_FAILED);
	}
	
	####################
	### Функции чата ###
	####################
	
	public function client_chat($client, $content)
	{
		if ($this->auth_count==1) $client->send($this->compose_special_message(static::SERVER_CODE_NOTICE, static::NOTICE_ALONE));
		else $this->broadcast_chat($client, $content);
	}
	
	public function broadcast_chat($client, $content)
	{
		$composed=$this->compose_chat($client, $content);
		$this->broadcast($composed, null, $client->client_id);
	}
	
	public function compose_chat($client, $content)
	{
		$template=Template_chat_message::with_message($client->user->entity, $content);
		$template->complete();
		return $this->compose_message(static::SERVER_CODE_CHAT, $template->resolution);
	}
	
	########################
	### Управление чатом ###
	########################
	
	// техническая команда, исходящая от интерфейса, а не из строки ввода.
	public function client_command($client, $content)
	{
		$command=$this->parse_command($content);
		if ($command===false)
		{
			$client->send($this->compose_error(static::ERROR_BAD_COMMAND));
			return;
		}
		$code=array_shift($command);
		
		if (!array_key_exists($code, $this->good_commands))
		{
			$client->send($this->compose_error(static::ERROR_BAD_COMMAND));
			return;
		}
		
		$method=$this->good_commands[$code];
		if (!method_exists($this, $method))
		{
			// хотя на самом деле виноват сервер...
			$client->send($this->compose_error(static::ERROR_BAD_COMMAND));
			return;
		}
		
		$result=$this->$method($client, $command);
		if ($result!==true) $client->send($this->compose_error($result));
	}
	
	public function client_command_kick($client, $details)
	{
		if (!$client->user->is_admin()) return static::ERROR_UNRIGHTFUL_COMMAND;
		$target_client_id=reset($details);
		if ($target_client_id==$client->client_id) return static::ERROR_BAD_COMMAND_TARGET;
		if (!array_key_exists($target_client_id, $this->clients)) return static::ERROR_BAD_COMMAND_TARGET;

		$this->clients($target_client_id)->close(static::CLOSE_KICKED);
	}
	
	public function client_command_ban($client, $details)
	{
		if (!$client->user->is_admin()) return static::ERROR_UNRIGHTFUL_COMMAND;
		$target_usid=reset($details);
		if ($target_usid==$client->user->entity->db_id) return static::ERROR_BAD_COMMAND_TARGET;
		$target_user=$this->persistent_user($target_usid);
		
		$target_user->banned($time);
		if (!empty($target_user->client)) $target_user->client->close(static::CLOSE_BANNED);
	}
	
	public function client_command_unban($client, $details)
	{
		if (!$client->user->is_admin()) return static::ERROR_UNRIGHTFUL_COMMAND;
		$target_usid=reset($details);
		if ($target_usid==$client->user->entity->db_id) return static::ERROR_BAD_COMMAND_TARGET;
		$target_user=$this->persistent_user($target_usid);
		
		$target_user->unbanned();
	}
	
	public function client_command_voice_user($client, $details)
	{
		if (!$client->user->is_admin()) return static::ERROR_UNRIGHTFUL_COMMAND;
		$target_usid=reset($details);
		if ($target_usid==$client->user->entity->db_id) return static::ERROR_BAD_COMMAND_TARGET;
		$target_user=$this->persistent_user($target_usid);
		
		$target_user->banned($time);
		if (!empty($target_user->client)) $target_user->client->close(static::CLOSE_BANNED);
	}
}

class Template_chat_message extends Template_from_db
{
	public
		$db_key='interactive.chat_message',
		$message;
	
	public static function with_message($user, $message, $line=[])
	{
		$template=static::with_line($line);
		$template->message=Value_text::standalone($message);
		$template->context=$user;
		return $template;
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='message') return $this->message->default_template($line);
		return parent::make_template($code, $line);
	}
}
?>