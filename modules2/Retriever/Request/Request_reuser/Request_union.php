<?
namespace Pokeliga\Retriever;

/**
* Отвечает получению данных из объединения запросов.
*/
class RequestTicket_union extends RequestTicket
{		
	public function __construct(...$subtickets)
	{
		parent::__construct('\Pokeliga\Retriever\Request_union', [$subtickets]);
	}
}

/**
* Отвечает получению данных из объединения запросов.
* @var array $ticket Хранит массив RequestTicket'ов, а не единстенный!
* @var array $subrequest Хранит массив Request'ов, а не единстенный!
*/
class Request_union extends Request_reuser
{
	use Request_reuser_capture_result;
	
	/**
	* @param array $tickets Массив из объектов \Pokeliga\Retriever\RequestTicket
	*/
	public function __construct($tickets)
	{
		$tickets=(array)$tickets;
		parent::__construct($tickets);
	}
	
	/**
	* @return \Pokeliga\Retriever\RequestTicket|array По умолчанию возвращает массив Request'ов и заполняет запросы во всех RequestTicket'ах.
	*/
	protected function create_subrequest($ticket=null)
	{
		if ($ticket!==null) return parent::create_subrequest($ticket); // создание по конкретному RequestTicket'у, а не массиву.
		
		$result=[];
		foreach ($this->ticket as $ticket)
		{
			if ($this->spawn_new) $result[]=$ticket->standalone();
			else $result[]=$ticket->get_request();
		}
		return $result;
	}
	
	public function create_query()
	{
		$query=['action'=>'select', 'union'=>[]];
		foreach ($this->get_subrequest() as $request)
		{
			$subquery=$request->create_query();
			$this->modify_query($subquery);
			$query['union'][]=$subquery;
		}
		return $query;
	}
	
	protected function modify_query($query) { }
		
	protected function data_processed()
	{
		foreach ($this->get_subrequest() as $request)
		{
			$request->data_processed();
		}
	}
	
	public function finish($success=true)
	{
		Request::finish($success);
		foreach ($this->get_subrequest() as $request)
		{
			$request->finish($success);
		}
	}
	
	public function set_data(...$keys)
	{
		$uncompleted=false;
		$this->get_subrequest(); // чтобы создать запрос в тикете правильным образом.
		foreach ($this->ticket as $ticket)
		{
			if ($set=$ticket->set_data()) $uncompleted=true;
		}
		return $uncompleted;
	}
}

?>