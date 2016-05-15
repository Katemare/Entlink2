<?

abstract class HandleManager
{
	const
		MAX_TRIES=100;
	
	public
		$generator_call;
	
	public function __construct(Callable $generator_call)
	{
		$this->generator_call=$generator_call;
	}
	
	public function generate_handle(Persona $self=null)
	{
		return $this->suggest_free_handle($self);
	}
	
	public abstract function approve_custom_handle(Persona $self, &$handle, &$reason=false);
	
	protected function suggest_free_handle(Persona $self=null, $previous_handle=null, &$carry=null)
	{
		$tries=0;
		$handle=$previous_handle;
		while ($tries==0 or !$this->is_handle_free($handle, $self))
		{
			if ($tries>static::MAX_TRIES) break;
			$tries++;
			$handle=$this->suggest_handle($self, $handle, $carry);
		}
		if ($tries>static::MAX_TRIES) $this->on_handle_failuire($self, $handle);
		return $handle;
	}
	
	protected abstract function suggest_handle(Persona $self, $previous_handle=null, &$carry=null);
	
	protected function on_handle_failuire(Persona $self, &$handle)
	{
		return $handle;
	}
	
	public function is_handle_free($handle, Persona $self=null)
	{
		$call=$this->generator_call;
		foreach ($call() as $persona)
		{
			if ($self!==null and $persona===$self) continue;
			if ($persona instanceof Persona and $persona->collides_with_handle($handle)) return false;
		}
		return true;
	}
}

class StrictHandleManager extends HandleManager
{
	public function suggest_handle(Persona $self=null, $previous_handle=null, &$carry=null)
	{
		if ($self===null or $previous_handle!==null) return Server()->generate_random_token();
		return $self->ident();
	}
	
	public function approve_custom_handle(Persona $self, &$handle, &$reason=false)
	{
		if ($reason!==false) $reason=Template_from_db::with_db_key('chat.no_custom_handles');
		return false;
	}
}
?>