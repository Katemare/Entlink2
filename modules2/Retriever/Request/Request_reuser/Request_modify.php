<?
namespace Pokeliga\Retriever;

/**
* Прогоняет Query через вызовы, модифицирующие его.
*/
class Request_modify_by_calls extends Request_reuser
{
	use Request_reuser_capture_result;
	
	protected
		$calls;
	
	/**
	* @param array $calls Вызовы, через которые нужно провести Query.
	*/
	public function __construct($ticket, Callable ...$calls)
	{
		parent::__construct($ticket);
		$this->calls=$calls;
	}
	
	protected function modify_query($query)
	{
		$query=Query::from_array($query); // вызовы должны модифицировать сам объект запроса, а не возвращать новый.
		foreach ($this->calls as $call)
		{
			$call($query);
		}
		return $query;
	}
}

/**
* RequestTicket для модификации запроса вызовами.
*/
class RequestTicket_modify_query extends RequestTicket
{	
	public function __construct(...$args)
	{
		parent::__construct('\Pokeliga\Retriever\Request_modify_by_calls', $args);
	}
}

?>