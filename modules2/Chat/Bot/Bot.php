<?

// this bot runs server-side
abstract class Bot extends Agent implements Commandable
{
	use IgnoreBouncedMessages, Object_id;
	
	protected
		$handle;
	
	public function __construct()
	{
		parent::__construct();
		$this->generate_object_id();
	}
	
	public function handle() { return $this->handle; }
	
	public function ident() { return 'b'.$this->object_id; }
	
	public function process_incoming_message(Message $message)
	{
		// WIP
	}
	
	public abstract function create_command_response(Message $message, $response);
}

?>