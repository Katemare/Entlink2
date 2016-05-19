<?

interface AgentLink extends MemberableLink
{
	public function get_agent();
}

// отвечает пользователю (зарегистрированному либо анониму) или боту.
abstract class Agent implements Memberable, AgentLink, MessageNode, ValueHost, Template_context
{
	use StandardMemberable, ValueHost_standard, Context_self
	{
		StandardMemberable::identity_data as Memberable_identity_data;
	}
	
	const
		AGENT_GROUP='unknown';
	
	public function __construct() { } // чтобы могли вызывать потомки на случай, если тут что-то появится.
	
	public function get_agent() { return $this; }
	
	public function value($code) { return $this->ValueHost_value($code); }
	public function request($code)
	{
		if ($code==='group') return $this->group();
		else return $this->ValueHost_request($code);
	}
	
	public function template($code, $line=[])
	{
		if ($code==='handle') return $this->handle();
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
	
	public function create_identity_message(MessageOriginator $originator=null)
	{
		$server=Server();
		if ($originator===null) $originator=$this;
		$message=new Message($originator, $server::SERVER_CODE_IDENT, $this->identity_data());
		return $message;
	}
}

?>