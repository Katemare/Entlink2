<?

// A message that is processed in context of a thread (unlike authorization, for example).
class ThreadMessage extends Message implements MemberableLink
{
	protected
		$thread;		// Thread
	
	public function __construct(MessageOriginator $originator, $code, $content=null, Thread $thread=null, $ts=null)
	{
		if (!empty($thread)) $this->thread=$thread;
		elseif ($originator instanceof Thread) $this->thread=$originator;
		else throw new ThreadException();
		
		parent::__construct($originator, $code, $content, $ts);
		if ($this->content===null) $this->content=[];
		$server=Server();
		if (array_key_exists($server::THREAD_KEY, $this->content) and $this->content[$server::THREAD_KEY]!=$this->thread->ident()) throw new ThreadException();
		
		$this->content[$server::THREAD_KEY]=$this->thread->ident();
		
		if ($originator instanceof MemberableLink)
		{
			$this->content['identity']=$originator->get_memberable()->ident();
		}
	}
	
	protected function make_text_safe($text)
	{
		return htmlspecialchars($text);
	}
	
	public function get_memberable()
	{
		if ($this->originator instanceof MemberableLink) return $this->originator->get_memberable();
		if ($this->target instanceof MemberableLink) return $this->target->get_memberable();
		throw new ThreadException();
	}
	
	public function get_agent()
	{
		if ($this->originator instanceof AgentLink) return $this->originator->get_agent();
		if ($this->target instanceof AgentLink) return $this->target->get_agent();
		throw new ThreadException();
	}
}

// this is a "print" that a message leaves in a ThreadLog. it's not an event but a retelling of an event.
// it doesn't have a MessageOriginator because they may be gone, logged off and flushed from cache.
class LoggedMessage extends MessageContent
{
	protected
		$thread;		// Thread
}

?>