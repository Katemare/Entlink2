<?
namespace Pokeliga\Interactive;

abstract class ChatCommand
{
	public
		$master,
		$code,
		$bound=false;
	
	public function __construct($code, $master, $bind=null)
	{
		$this->master=$master;
		$this->code=code;
		if ($bind!==null) $this->bound=$bind;
	}
	
	public function execute($by_client, $details)
	{
		$allowed=$this->is_executable($by_client, $details);
		if ($allowed!==true) return $allowed;
		if ($this->bound()) return $this->run_bound($by_client, $details);
		
		$master=$this->master;
		return $master::ERROR_BAD_COMMAND;
	}
	
	public function is_bound()
	{
		if ($this->bound===false) return false;
		if (!is_callable($this->bound)) return false;
		return true;
	}
	
	public function run_bound(...$args)
	{
		if (!$this->is_bound()) return false;
		return $bound(...$args);
	}
}

abstract class ChatCommand_admin extends ChatCommand
{
	public function is_executable($by_client, $details)
	{
		$master=$this->master;
		if (!$by_client->is_admin()) return $master::ERROR_UNRIGHTFUL_COMMAND;
		return true;
	}
}

abstract class ChatCommand_admin_to_client extends ChatCommand_admin
{
	public
		$allow_on_self=false;

	public function is_executable($by_client, $details)
	{
		$result=parent::execute($by_client, $details);
		if ($result!==true) return $result;
		
		$target_client_id=reset($details);
		if ( (!$this->allow_on_self) && ($target_client_id==$by_client->client_id) ) return static::ERROR_BAD_COMMAND_TARGET;
		if (!array_key_exists($target_client_id, $this->clients)) return static::ERROR_BAD_COMMAND_TARGET;
		return true;
	}
}

abstract class ChatCommand_admin_to_user extends ChatCommand_admin
{
	public
		$allow_on_self=false;

	public function is_executable($by_client, $details)
	{
		$result=parent::execute($by_client, $details);
		if ($result!==true) return $result;
		
		$target_usid=reset($details);
		if ( (!$allow_on_self) && ($target_usid==$by_client->user->entity->db_id) ) return static::ERROR_BAD_COMMAND_TARGET;
		return true;
	}
}
?>