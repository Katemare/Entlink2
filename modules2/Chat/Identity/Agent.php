<?

// отвечает пользователю (зарегистрированному либо анониму) или боту.
abstract class Agent implements Memberable, MessageNode, ValueHost
{
	use StandardMemberable, ValueHost_standard
	{
		StandardMemberable::identity_data as Memberable_identity_data;
	}
	
	const
		AGENT_GROUP='unknown';
		
	public function value($code) { return $this->ValueHost_value($code); }
	public function request($code)
	{
		if ($code==='group') return $this->group();
		else return $this->ValueHost_request($code);
	}
	
	public function group()
	{
		return static::AGENT_GROUP;
	}
	
	public function identity_data()
	{
		$data=$this->Memberable_identity_data();
		$data['group']=$this->group();
		return $data;
	}
}

?>