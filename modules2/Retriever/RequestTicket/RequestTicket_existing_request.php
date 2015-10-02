<?
namespace Pokeliga\Retriever;

/**
* Класс, занимающийся исключительно получением данных из RequestTicket'а.
*/
class Task_request_get_data extends \Pokeliga\Task\Task
{
	use Task_processes_request;
	
	/**
	* Фабрика, создающиая задачу с данным RequestTicket'ом.
	* @param \Pokeliga\Retriever\RequestTicket $ticket
	* @return self
	*/
	public static function with_ticket($ticket)
	{
		$task=new static();
		$task->request_ticket=$ticket;
		return $task;
	}

	/**
	* Фабрика, создающиая задачу с данным Request'ом по приведённым ключам.
	* @param \Pokeliga\Retriever\Request $request
	* @param mixed $keys Ключи для запроса get_data().
	* @see \Pokeliga\Retriever\Request::get_data();
	* @return self
	*/
	public static function with_request($request, ...$keys)
	{
		$ticket=new RequestTicket_existing_request($request, $keys);
		return static::with_ticket($ticket);
	}
	
	protected function apply_data($data)
	{
		$this->finish_with_resolution($data);
	}
}

// Этот класс нельзя использовать в Request_reuser!
class RequestTicket_existing_request extends RequestTicket
{
	protected
		$spawn_method=self::SPAWN_NEW;
		
	public function __construct($request, $keys)
	{
		$this->request=$request;
		parent::__construct('', [], $keys);
	}
	
	public function make_Multiton_key() { }
}

?>