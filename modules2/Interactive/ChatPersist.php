<?
namespace Pokeliga\Interactive;

// ��������� ���������� ������, ����������� � ������������ ����� �����������. ������ ����� ���� ������ ����� ��� � ����� ������� ������, � � ����� ������� ������ ����� ���� ������� ��������� �������� - ��� �� ����. ��� ������ ������, ������, ���������� ������ � ������ ����� ���� (�, ���� ���� ��� �� ����, � ������ ������������� �����). ��� �� ��������, ��������������� ��� ����������� ��������.
abstract class ChatPersist implements Template_context
{
	static
		$service_group='common';

	public
		$master;
	
	public function __construct($master)
	{
		$this->master=$master;
	}
	
	public function created() { } // ��������� ����� ��������� �������� ����������, ����� �������� �������.
	
	public function banished() { } // ���������� ��� ����������� �������.
}

class ChatPersistManager
{
	public
		$master,
		$factory=[],
		$groups=[],
		$container=[];
	
	public function __construct($master)
	{
		$this->master=$master;
	}
	
	public function register_container($code, $factory, $group=null)
	{
		$this->container[$code]=[];
		$this->factory[$code]=$factory;
		if ($group===null) $group=$factory::$service_group;
		$this->register_container_in_group($code, $group);
	}
	
	public function register_container_in_group($code, $group)
	{
		if (is_array($group))
		{
			foreach ($group as $gr)
			{
				$this->register_container_in_group($code, $gr);
			}
		}
		else
		{
			if (!array_key_exists($group, $this->groups)) $this->groups[$group]=[];
			$this->groups[$group][]=$code;		
		}
	}
	
	public function serve($code, $key, $create=true)
	{
		if (!array_key_exists($code, $this->container)) return false;
		if (array_key_exists($key, $this->container[$code])) return $this->container[$code][$key];
		if (!$create) return false;
		
		$factory=$this->factory[$code];
		$persist=$factory::create_from_key($key, $this); // ����� ������� ��� ������.
		$this->container[$code][$key]=$persist;
		return $persist;
	}
	
	public function banish($code, $ey)
	{
		if (!array_key_exists($code, $this->container)) return false;
		if (!array_key_exists($key, $this->container[$code])) return false;
		$persist=$this->container[$code][$key];
		$persist->banished();
		unset($this->container[$code][$key]);
	}
}

/*
	��������� ����:
	1. ��������������, �� ��������������
	2. �������-��������� (� �����, �������� ������ ������, �� ���������� �� ����������� � ����������� ����� ����� ����� �����������)
	3. ������������-��������� (� �����, ������ ����� ������ ������, ����� ���� �������� ��� ���������� ������� �� �����������, � �� ������ �� ��������.)
	4. ����������� (��� ����, ��� ����� ������, ������ �������� ���). ���������� ���� �� �����, ����� �����������.
	
	����������� ����� ����� ��� ������ ��� ������������, ����� ��������� ������.
	������ ��� ������������ ����� ����� � ����� �������������.
	��������� ���� �� ��������� ������������, �������� ��� ������������� (?) ���� ������������ �� ����������.
	��� ����� ���������� ���������� ������� ��������� �������, ��������������� �������������-�������. ����� ���������� �� ������� �����������
	
	� ������� ������� ����� ���� � ������ ���� ���������� - ��������, ������� ��������� ��� ����������� ������� ����.
*/

// ������������� ��������������� �����������. ������� ����������� ������������� ���� ����� ������. ������� ����� ��������������� ��������� ����������� (�����������). ���������� �����.
abstract class ChatIdentity extends ChatPersist
{
	public
		$client,
		$ident_states=[],
		$single_client=false,
		$personal_rights=[],
		
		$voice,
		$banned_until=false,
		$banned_timeout;
	
	public function __construct($master)
	{
		if ($this->single_client) $this-client=false;
		else $this->client=[];
		parent::__construct($master);
	}
	
	public static abstract function auth($auth_data, $master);
	
	// ��������� ���������������� ��������, ��������, �������� �� ��������, �������� �� �������������.
	public function has_ident_state($state)
	{
		return in_array($state, $this->ident_states);
	}
	
	// true ��� false - ���� �� � ��������� ������������ (�������������) �����.
	public function has_personal_right($right)
	{
		if (array_key_exists($right, $this->personal_rights)) return $this->personal_rights[$right];
	}
	
	public function is_connected()
	{
		return !empty($this->client);
	}
	
	public function connected($client)
	{
		if ($this->single_client)
		{
			if ($this->is_connected())
			{
				$old_client=$this->client;
				$old_client->auth=false; // ����� �� �������� ������� ���������, ���������� � ��� �����.
				$this->client=$client;
				$master=$this->master;
				$old_client->close($master::CLOSE_REPLACED_CONNECTION);
			}
			$this->client=$client;
		}
		else $this->client[$client->client_id]=$client;
	}
	
	public function disconnected($client)
	{
		if ($this->single_client) $this->client=false;
		else unset($this->client[$client->client_id]);
	}
	
	public function is_banned()
	{
		return $this->banned_until!==false;
	}
	
	public function has_voice()
	{
		if ($this->voice!==null) return $this->voice;
		return $this->master->default_voice_for($this);
	}
	
	public function banned($bantime=0)
	{
		if ($this->banned_until!==false) $this->unbanned(); // ��������� ������� ����.
		$this->banned_until=time()+$bantime;
		if ($time>0) $this->banned_timeout=$this->master->set_timeout([$this, 'unban'], $this->banned_until);
	}
	
	public function unbanned()
	{
		$this->clear_timeout($this->banned_timeout);
		$this->banned_until=false;
	}
	
	public function voice()
	{
		$this->voice=true;
	}
	
	public function mute()
	{
		$this->voice=false;
	}
	
	public function reset_voice()
	{
		$this->voice=null;
	}
}

class ChatWatcher extends ChatIdentity
{
	public
		$ident_states=[Chat::CLIENT_WATCHER];
}

class ChatWatcherFactory extends ChatPersistFactory
{
	public function create_from_key($key)
	{
	}
}

// ������������� ������������������ �������������.
class ChatUser extends ChatIdentity implements Template_context
{
	public
		$ident_states=[Chat::CLIENT_USER],
		$single_client=true,
		$entity;
	
	public static function auth($auth_data, $master)
	{
		if ( (empty($auth_data['auth'])) || ($auth_data['auth']!=='user') ) return false;
		
	}
	
	public function __construct($user, $master)
	{
		parent::__construct($master);
		
		if (is_numeric($user)) $user=$this->master->pool()->entity_from_db_id($user, 'User');
		$this->entity=$user;
	}
	
	public function is_admin()
	{
		return $this->entity->value('admin');
	}
	
	public function handle()
	{
		return $this->entity->value('login');
	}
}

// ������������� ��������� �������������. ��� ������ ����� �� ������� ��������� ����-������, ����� ��� ��������� ������ ��� ������ � �� �� ������. ������ �������� ��������� ����� ����� ���������� �� ������, ���� ������ ������� ���������.
class ChatAnon extends ChatMember
{
	const
		HANDLE_EX='/^[A-Za-z�-��-�0-9][A-Za-z�-��-�0-9 \-]*[A-Za-z�-��-�0-9]$/';

	public
		$anon_handle,
		$auth_key;
	
	public function __construct($handle, $master, $key=null)
	{
		parent::__construct($master);
		$this->auth_key=$key;
		$this->anon_handle=$handle;
	}
	
	public function disconnected()
	{
		$master=$this->master;
		$this->master->set_timeout([$this, 'erase'], $master::TIMEOUT_ANON, 'erase'.$this->auth_key);
	}
	
	public function connected()
	{
		parent::connected();
		$this->master->clear_timeout('erase'.$this->auth_key);
	}
	
	public function erase()
	{
		$this->master->remove_persistent_anon($this->auth_key);
	}
	
	public function is_admin()
	{
		return false;
	}
	
	public function handle()
	{
		return $this->anon_handle;
	}
	
	public static function valid_handle($handle)
	{
		return preg_match(static::HANDLE_EX, $handle);
	}
	
	public static function valid_key($key)
	{
		return preg_match('/^[0-9a-f]{32}$/', $key);
	}
	
	public static function generate_random_key()
	{
		return md5(mt_rand());
	}
}

class ChatWatcher extends ChatMember
{
	
}
?>