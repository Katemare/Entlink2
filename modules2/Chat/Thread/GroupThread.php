<?

// тред для общения нескольких человек.
class GroupThread extends ThreadType implements ThreadTitle, ThreadFounder, JoinableThread, LoggedThread
{	
	const
		WELCOME_KEY='chat.group_welcome',
		JOIN_ANNOUNCEMENT_KEY='chat.member_joined';
	
	const
		// codes that client sends to server.
		CLIENT_CODE_JOIN='join',			// memberable requests to join
		CLIENT_CODE_LEAVE='leave',			// member requests to leave
		CLIENT_CODE_IDENTITY='ident',		// member requests identity data
		CLIENT_CODE_LOG='log',				// request message backlog
		
		// codes that server sends to clients
		SERVER_CODE_CHAT='chat',			// a chat message having text, author and related info.
		SERVER_CODE_JOIN='join',			// makes memberable a member join the thread.
		SERVER_CODE_LEAVE='leave',			// expells member
		SERVER_CODE_ATTENTION='attent',		// places an attention marker on thread tab
		SERVER_CODE_IDENTITY='ident',		// informs on identity data
		
		DENY_JOIN_OTHER		='other',
		DENY_JOIN_DUPLICATE	='duplicate',
		DENY_JOIN_BANNED	='banned',
		
		DENY_CHAT_OTHER		='other',
		DENY_CHAT_NO_VOICE	='no_voice',
		
		FORCE_LEAVE_OTHER		='other',
		FORCE_LEAVE_KICK		='kick',
		FORCE_LEAVE_THREAD_CLOSED='closed',
		
		ERROR_BAD_TITLE				='bad_title';
	
	protected
		$title,
		$membership=[],
		$owner,
		$founder;
	
	public function title() { return $this->title; }
	public function set_title($title)
	{
		// if ($this->title!==null) // WIP - оповещение о смене названия.
		$this->title=$title;
	}
	
	public static function is_title_valid($title, &$deny_reason)
	{
		if (!preg_match('/^[\pL\d_]+$/u', $title))
		{
			$deny_reason=static::ERROR_BAD_TITLE;
			return false;
		}
		return true;
	}
	
	public function founder() { return $this->founder; }
	public function set_founder(Agent $founder)
	{
		if ($this->founder!==null) throw new ThreadExeption();
		$this->founder=$founder;
	}
	
	protected function init_message_processors()
	{
		parent::init_message_processors();
		$this->message_processors+=
		[
			'join'=>[$this, 'process_join_request'],
			'leave'=>[$this, 'process_leave_request']
		];
	}
	
	protected function process_chat_message(ThreadMessage $message)
	{
		$this->broadcast($this->transform_chat_message($message));
	}
	
	protected function process_join_request(ThreadMessage $message)
	{
		$memberable=$message->get_memberable();
		if ($this->can_memberable_join($memberable, $reason)) $this->join_member($memberable);
		else $this->deny_join($memberable, $reason);
	}
	
	public function can_join(Memberable $memberable, &$deny_reason)
	{
		foreach ($this->memberships as $membership)
		{
			if ($membership->get_memberable()===$memberable)
			{
				$deny_reason=static::DENY_JOIN_DUPLICATE;
				return false;
			}
		}
		return true;
	}
	
	protected function process_leave_request(ThreadMessage $message)
	{
	}
	
	protected function process_identity_request(ThreadMessage $message)
	{
	}
	
	protected function process_log_request(ThreadMessage $message)
	{
	}
	
	public function join(Memberable $member)
	{
		$membership=$this->create_membership($member);
		$this->memberships[$membership->ident()]=$membership;
		$this->welcome_member($membership);
		$this->announce_member($membership);
	}
	
	protected function welcome_member(ThreadMembership $membership)
	{
		$message=$this->thread->create_thread_message('join', $this->get_thread_data());
		$message->deliver($membership);
	}
	
	protected function get_thread_data()
	{
		$data=[];
		$data['title']=$this->title;
		$data['members']=array_values(array_map(function($membership) { return $membership->identity_data(); }, $this->memberships));
		return $data;
	}
	
	protected function announce_member(ThreadMembership $membership)
	{
		$message=$this->thread->create_thread_message('notify', $this->get_join_announcement($membership));
		$this->broadcast($message);
	}
	
	protected function get_join_announcement(ThreadMembership $membership)
	{
		$template=Template_from_db::with_db_key(static::JOIN_ANNOUNCEMENT_KEY);
		$template->context=$membership;
		return $template;
	}
	
	protected function create_membership(Memberable $member)
	{
		return new ThreadMembership($this->thread, $member);
	}

	public function gen_members($approve_callback=null)
	{
		foreach ($this->memberships as $membership)
		{
			if ($approve_callback!==null and !$approve_callback($member)) continue;
			yield $membership;
		}
	}
	
	// sends a message to all members
	public function broadcast(Message $message, $approve_callback=null)
	{
		$message->multicast($this->gen_members($approve_callback));
	}
}

?>