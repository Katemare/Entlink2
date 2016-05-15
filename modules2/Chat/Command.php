<?

// this is a acting agent when a user sends a command.
abstract class Command
{
	public abstract function execute();
}

// this command allows to bind and run a custom function.
class BoundCommand
{
	protected
		$bound;
}

?>