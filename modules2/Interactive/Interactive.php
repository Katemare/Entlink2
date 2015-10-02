<?
namespace Pokeliga\Interactive;

// это класс для интерактивности в реальном времени - будь то бои, квесты или что-либо ещё. В принципе, из этого можно выделить родительский класс чата, но пока не нужно.
// это класс имеет представление об анонимах, пользователях, игроках, участниках и админах и даёт им возможность общаться по чату, но не имеет представления о том, в чём заключается игра.
abstract class Interactive extends Chat
{	
	const
		CLIENT_CLASS='InteractiveClient',
		MAX_WATCHERS	=30,
		MAX_PLAYERS		=6, // подключённые игроки, помимо инициатора.
		MAX_CHARACTERS	=6, // созданные персонажи, помимо инициатора. считаются персноажи отключившихся игроков.
		
		CLIENT_WATCHER	=5, // клиент находится в статусе наблюдателя.
		CLIENT_PLAYER	=6, // клиент находится в статусе игрока.
		CLIENT_ADMIN	=7, // клиент находится в статусе админа.
		
		ERROR_INIT_FAILED			=50, // не удалось создать интерактив согласно событию.
		ERROR_NOT_PLAYER			=51, // не игрок или не активированный игрок.
		ERROR_NO_SCENARIO			=52 	// не указан сценарий игры (некоторые игровые режимы могут его и не требовать, но многие будут требовать).
		ERROR_BAD_SCENARIO			=53,// плохой сценарий игры.
		ERROR_NO_PARAMS				=54,// параметры отсутствуют, но нужны.
		ERROR_BAD_PARAMS			=55,// параметры для сценария есть, но они плохие (не удалось получить либо применить)
		ERROR_BAD_SAVE				=56,// сейв есть, но он плохой (не удалось получить либо применить)
		ERROR_PREMATURE_JOIN		=57,// интерактив ещё не инициализирован и присоединяется не инициализатор.
		ERROR_INIT_TIMEOUT			=58,// слишком долго не получаются данные для инициализации.
		ERROR_DENY_WATCHERS			=59,// наблюдателям нельзя.
		ERROR_TOO_MANY_WATCHERS		=60,// достигнут предел наблюдателей.
		ERROR_TOO_MANY_PLAYERS		=61,// достигнут предел игроков.
		ERROR_TOO_MANY_CHARACTERS	=62,// достигнут предел персонажей.
		
		CLOSE_PLAYER				=103, // игрок с персонажем отключился.
		JOIN_PLAYER					=202, // присоединился игрок-участник.
		
		STATE_INIT		=0,	// система локаций ещё не создана. Она будет создана, когда подключится первый игрок.
		STATE_RUNNING	=1,	// система локаций создана.
		STATE_FAILED	=2, // инициализация провалена.
		STATE_FINISHED	=3,
				
		SERVER_CODE_DESC	='desc', // обновляет у конкретного пользователя описания.
		// ФОРМАТ: desc:<json>
		CLIENT_CODE_ACTION	='actn',
		// ФОРМАТ: actn:<строка>
		
		TIMEOUT_INIT		=300; // если игра не инициализирована за это время, она заканчивается.
	
	public
		$more_codes=
		// соответствия код клиентского сообщения => вызов.
		[
			self::CLIENT_CODE_ACTION	=>'user_action',
		],
		
		$more_template_keys=
		[
			self::ERROR_INIT_FAILED			='interactive.init_failed',
			self::ERROR_NOT_PLAYER			='interactive.bad_initiator',
			self::ERROR_NO_SCENARIO			='interactive.no_scenario',
			self::ERROR_BAD_SCENARIO		='interactive.bad_scenario',
			self::ERROR_NO_PARAMS			='interactive.no_params',
			self::ERROR_BAD_PARAMS			='interactive.bad_params',
			self::ERROR_BAD_SAVE			='interactive.bad_save',
			self::ERROR_DENY_WATCHERS		='interactive.no_watchers',
			
			self::CLOSE_PLAYER				='interactive.player_disconnected',
			self::JOIN_PLAYER				='interactive.player_connected',
		],
	
		$state=Interactive::STATE_INIT,
		$main_usid,		// айди пользователя-инициатора.
		
		// счётчики, нужные отчасти для быстроты, отчасти потому, что вместо массивов используются генераторы.
		$watchers_count	=0,
		$players_count	=0,
		$chars_count	=0,
		
		// генераторы:
		$watchers,	// наблюдатели
		$players;	// игроки.
		
	
	public function __construct($port, $main_usid)
	{
		parent::__construct($port);
		$this->main_usid=$main_usid;
		$this->good_codes+=$this->more_codes;
		$this->template_keys+=$this->more_template_keys;
		
		// генераторы
		$this->watchers=[$this, 'gen_watchers'];
		$this->players=[$this, 'gen_players'];
	}
	
	##########################
	### Работа с клиентами ###
	##########################
	
	// этот метод не использует is_watcher(), а следующий - is_player() для упрощения проверок. неавторизованные и анонимы и так отфильтрованы использованием генераторов.
	public function gen_watchers()
	{
		foreach ($this->users as $client_id=>$client)
		{
			if ($client->is_watcher()) yield $client_id=>$data;
		}
	}
	
	public function gen_players()
	{
		foreach ($this->users as $client_id=>$client)
		{
			if ($client->is_player()) yield $client_id=>$client;
		}
	}
	
	#####################
	### Основной цикл ###
	#####################
	
	public function setup_server()
	{
		parent::setup_server();
		$this->set_timeout([$this, 'init_timeout'], static::TIMEOUT_INIT, 'init');
	}

	public function init_timeout()
	{
		if ($this->state!==static::STATE_INIT) return;
		$this->stop(static::ERROR_INIT_TIMEOUT);
	}
	
	##########################
	### Механизм сообщений ###
	##########################
	
	// ничего нового.
	
	########################
	### Механизм событий ###
	########################
	
	// ничего нового.
	
	##############################################
	### Подключения, отключения, инициализация ###
	##############################################
	
	public function user_connected($client_id)
	{
		$this->client_data[$client_id]=['auth'=>false];
		$this->set_timeout(new Call([$this, 'user_auth_timeout'], $client_id), static::TIMEOUT_AUTH, 'auth'.$client_id);
		$this->send($client_id, static::SERVER_CODE_AUTH);
	}
	
	public function user_auth_timeout($client_id)
	{
		$this->close($client_id, static::ERROR_AUTH_TIMEOUT);
	}
	
	// так интерактив закрывает соединение с пользователем по своей воле, в отличие от отключения пользователя ввиду технической ошибки или ухода со страницы.
	public function close($client_id, $error=null)
	{
		$composed=$this->compose_error($error);
		$this->send($client_id, $composed);
		$this->server->wsRemoveClient($client_id); // после этого интерактив ещё получит событие 'close'
	}
	
	// реакция на событие от сервера - значит, техническое отключение, уход со страницы или реакция на предыдущий метод close().
	public function user_disconnected($client_id, $status)
	{
		if ($this->client_data[$client_id]['auth']===false) $this->clear_timeout('auth'.$client_id);
		else $this->auth_count--;
		if ($this->is_anon($client_id)) $this->anon_count--;
		if ($this->is_watcher($client_id)) $this->watchers_count--;
		if ($this->is_player($client_id)) $this->players_count--;
		
		$this->broadcast_closed($client_id);
		unset($this->client_data[$client_id]);
		/*
		варианты статуса:
			PHPWebSocket::WS_STATUS_PROTOCOL_ERROR	- ошибка протокола.
			PHPWebSocket::WS_STATUS_GONE_AWAY		- остановка сервера.
			PHPWebSocket::WS_STATUS_TIMEOUT			- пользователь не отвечает на пинг.
			PHPWebSocket::WS_STATUS_MESSAGE_TOO_BIG	- прислано слишком длинное сообщение.
			PHPWebSocket::WS_STATUS_NORMAL_CLOSE	- пользователь вышел.
		*/
	}
	
	public function user_auth($client_id, $content)
	{
		$auth_data=parse_str($content);
		if (!empty($auth_data['anon'])) $result=$this->auth_as_anon($client_id, $auth_data);
		elseif (empty($auth_data['token'])) $result=static::ERROR_BAD_TOKEN;
		else
		{
			$user=$this->user_by_token($auth_data['token']);
			if ($user instanceof \Report_impossible) $result=static::ERROR_BAD_TOKEN;
			else $result=$this->auth_as_user($client_id, $user, $auth_data);
			
			if ($result===true)
			{
				// теперь нужно решить, станет пользователь наблюдателем или игроком.
				if ( ($this->main_usid==$user->db_id) || (!empty($auth_data['play'])) )
				{
					$result=$this->can_join_play($user);
					if ($result===true) $result=$this->make_character($user, $auth_data);
				}
				else // новый, ранее не подключавшийся пользователь присоединяется как наблюдатель.
				{
					$result=$this->make_watcher($user, $auth_data);
				}
			}
		}
		
		if (is_bool($result))
		{
			if ($result===true) $this->auth_count++;
			$this->send_intro($client_id);
			$this->broadcast_joined($client_id);
		}
		else $this->close($client_id, $result);
	}
	
	public function user_by_token($token)
	{
		$user=$this->pool()->entity_from_provider(['auth_by_token', $token], 'User');
		$user->verify();
		if ($user->state!==Entity::STATE_VERIFIED_ID) return $this->sign_report(new \Report_impossible('bad_token'));
		return $user;
	}
	
	public function auth_as_anon($client_id, $auth_data)
	{
		if ($this->anon_count>=static::MAX_ANON) return static::ERROR_TOO_MANY_ANONS;
		if ($this->watchers_count>=static::MAX_WATCHERS) return static::ERROR_TOO_MANY_WATCHERS;
		$this->anon_count++;
		$this->watchers_count++;
		$this->client_data[$client_id]['auth']=true;
		$this->client_data[$client_id]['user']=false;
	}
	
	// возвращает true, если авторизован новый пользователь; false, если это старый под новым соединением; или цифровой код ошибки.
	public function auth_as_user($client_id, $user, $auth_data)
	{
		$new_user=true;
		if (array_key_exists($user->db_id, $this->user_data)) // пользователь уже подключался.
		{
			$new_user=false;
			if (!empty($old_client_id=$this->user_data[$user->db_id]['client']))
			{
				$this->client_data[$client_id]=$this->client_data[$old_client_id];
				$this->user_data[$user->db_id]['client']=$client_id;
				unset($this->client_data[$old_client_id]); // стираем заранее, чтобы не отправились сообщения о выходе пользователя.
				$this->close($old_client_id, static::CLOSE_REPLACED_CONNECTION);
				// пользователь зашёл с другого соединения (например, вкладки). в игре ничего не должно измениться кроме соединения, по которому пользователь получает данные, поэтому уходим.
				return false;
			}
		}
		else // пользователь ещё не подключался.
		{
			$this->user_data[$user->db_id]=
			[
				'client'=>$client_id,
				'entity'=>$user
			];
		}
		
		$this->client_data[$client_id]['auth']=true;
		$this->client_data[$client_id]['usid']=$user->db_id;
		
		if ($this->state===static::STATE_INIT)
		{
			if ($this->main_usid!==$user->db_id) return static::ERROR_PREMATURE_JOIN;
			
			$result=$this->init($auth_data);
			if ($result!==true)
			{
				$this->init_failed($result);
				return static::ERROR_INIT_FAILED;
			}
		}
		return $new_user;
	}
	
	public function make_watcher($user, $auth_data)
	{
		if ($this->watchers_count>=static::MAX_WATCHERS) return static::ERROR_TOO_MANY_WATCHERS;
		$this->watchers_count++;
		return true;
	}
	
	public function can_join_play($user, $auth_data)
	{
		if ($user->db_id==$this->main_usid) return true; // инициатор всегда может и должен стать игроком.
		if ($this->player_count>=static::MAX_PLAYERS) return static::ERROR_TOO_MANY_PLAYERS;
		if ($this->character_count>=static::MAX_CHARACTERS) return static::ERROR_TOO_MANY_CHARACTERS;
		return true; // дополнительные условия могут быть добавлены наследованием.
	}
	
	// метод должен вернуть истину, если всё получилось, либо код ошибки. также он должен позаботиьтся об увеличении числа игроков и персонажей.
	abstract public function make_character($user, $auth_data);
	
	abstract public function init($data);
	
	public function init_failed($error_code)
	{
		$this->state=static::STATE_FAILED;
		$this->stop($error_code);
	}
	
	####################
	### Функции чата ###
	####################
	
	public function user_chat($client_id, $content)
	{
		if ($this->auth_count==1) $this->send($client_id, $this->compose_special_message(static::SERVER_CODE_NOTICE, static::NOTICE_ALONE));
		else $this->broadcast_chat($client_id, $content);
	}
	
	public function broadcast_chat($client_id, $content)
	{
		$composed=$this->compose_chat($client_id, $content);
		$this->broadcast($composed, null, $client_id);
	}
	
	public function compose_chat($client_id, $content)
	{
		return $this->compose_message(static::SERVER_CODE_CHAT, $client_id.':'.$content);
	}
}

class InteractiveClient extends ChatClient
{
	static
		USER_CLASS='InteractiveUser';
	
	public is_player()
	{
		return $this->is_user() && $this->user->is_player();
	}
	
	public is_watcher()
	{
		return $this->is_anon() || !$this->user->is_player();
	}
}

class InteractiveUser extends ChatUser
{
	public
		$player=false,
		$thing;
		
	public function is_player()
	{
		return $this->player!==false;
	}
	
	public function is_watcher()
	{
		return $this->player===false;
	}
}
?>