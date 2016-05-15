<?

class ChatContext extends Context
{
	public
		$client,
		$member,
		$agent,
		$thread,
		
		$order=['agent', 'member', 'client'];
	
	protected function update_templaters()
	{
		if (count($this->templaters)<=1) return;
		$old=$this->templaters;
		$this->templaters=[];
		foreach ($this->order as $key)
		{
			if (!empty($old[$key]))
			{
				$this->templaters[$key]=$old[$key];
				unset($old[$key]);
			}
		}
		if (!empty($old)) $this->templaters=array_merge($old, $this->templaters);
	}
	
	public function set_client(Client $client)
	{
		$this->append($client, 'client');
		if ($client->is_authorized()) $this->append($client->agent(), 'agent');
		$this->update_templaters();
	}
	
	public function set_member(ThreadMembership $member)
	{
		$this->append($member, 'member');
		$this->update_templaters();
	}
	
	public function set_agent(Agent $agent)
	{
		$this->append($agent, 'agent');
		$this->update_templaters();
	}
}

?>