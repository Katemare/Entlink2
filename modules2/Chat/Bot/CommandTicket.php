<?

class CommandTicket implements Templater, Template_context, Pathway
{
	use Context_self;
	
	protected
		$command,
		$message,
		$args;
	
	public function __construct(Command $command, Message $message, $args)
	{
		$this->command=$command;
		$this->message=$message;
		$this->args=$args;
	}
	
	public function command() { return $this->command; }
	public function message() { return $this->message; }
	public function args($code)
	{
		if ($code===null) return $this->args;
		if (is_array($this->args) and array_key_exists($code, $this->args)) return $this->args[$code];
		return new Report_impossible('no args code');
	}
	
	public function render()
	{
		return $this->command->render_ticket($this);
	}
	
	public function template($code, $line=[])
	{
	}
	
	public function templaters()
	{
		return [$this->command, $this];
	}
	
	public function follow_track($code)
	{
		if ($code==='command') return $this->command;
	}
	
	public function execute()
	{
		$this->command->execute_ticket($this);
	}
	
	public function is_executable() { return true; }
}

class CommandTicket_denied extends CommandTicket
{
	public function execute()
	{
		throw new CommandExeption();
	}
	
	public function is_executable() { return false; }
	
	public function deny_reason() { return $this->args; }
}
?>