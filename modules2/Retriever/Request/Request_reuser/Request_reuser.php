<?
namespace Pokeliga\Retriever;

/**
* Базовый класс для запросов, модифицирующих другие запросы.
*/
abstract class Request_reuser extends Request
{
	use Request_no_keys;
	
	/**
	* @var \Pokeliga\Retriever\RequestTicket $ticket Представляет исходный запрос и требование к данным.
	* @var \Pokeliga\Retriever\Request Подзапрос, который надо модифицировать. Создаётся с помощью $ticket.
	* @var bool $spawn_new Настройка, определяющая, нужно ли создать уникальный или общественный запрос.
	*/
	protected
		$ticket,
		$subrequest,
		$spawn_new=true;
	
	/**
	* @param \Pokeliga\Retriever\RequestTicket $ticket Представляет исходный запрос и требование к данным.
	*/
	public function __construct($ticket)
	{
		parent::__construct();
		$this->ticket=$ticket;
	}
	
	/**
	* Создаёт подзапрос согласно правилам модификации.
	* Иногда необходимо создать уникальный запрос, даже если обычно тот создаётся общественным, чтобы не попали лишние данные.
	* Помимо прочего, заполняет запрос, содержащийся в $ticket!
	* @param null|\Pokeliga\Retriever\RequestTicket $ticket При необходимости работает не с собственным, а с поставляемым RequestTicket'ом.
	* @return \Pokeliga\Retriever\Request
	*/
	protected function create_subrequest($ticket=null)
	{
		if ($ticket===null) $ticket=$this->ticket;
		if ($this->spawn_new) return $ticket->standalone();
		return $ticket->get_request();
	}
	
	/**
	* Возвращает подзапрос, создавая его при необходимости.
	* @return \Pokeliga\Retriever\Request
	*/
	protected function get_subrequest()
	{
		if ($this->subrequest===null) $this->subrequest=$this->create_subrequest();
		return $this->subrequest;
	}
	
	public function create_query()
	{
		$query=$this->get_subrequest()->create_query();
		$query=$this->modify_query($query);
		return $query;
	}
	
	/**
	* Модифицирует Query (или массив соответствующего формата), полученный у подзапроса.
	* @param \Pokeliga\Retriever\Query|array
	* @return \Pokeliga\Retriever\Query|array
	*/
	protected abstract function modify_query($query);
	
	protected function process_result($result)
	{
		return $this->get_subrequest()->process_result($result);
	}
	
	protected function data_processed()
	{
		$this->get_subrequest()->data_processed();
	}
	
	public function set_data(...$keys)
	{
		$this->get_subrequest(); // чтобы создать запрос в тикете правильным образом.
		return $this->ticket->set_data();
	}
	
	public function compose_data(...$keys)
	{
		$this->get_subrequest(); // чтобы создать запрос в тикете правильным образом.
		return $this->ticket->compose_data();
	}
	
	public function finish($success=true)
	{
		parent::finish($success);
		$this->get_subrequest()->finish($success); // FIXME: возможно, это не вполне правильно - нужно посмотреть, не будет ли глюков.
	}
}

/**
* Черта для Request_reuser, позволяющая перехватить результат выполнения подзапроса.
*/
trait Request_reuser_capture_result
{
	protected function process_result($result)
	{
		if ($result instanceof \Report_impossible) return false;
		$this->data=$result;
		return true;
	}
	
	// хотя какой-нибудь Request_by_field в ответ на этот вызов отдаёт только данные по соответствующим ключам, данный запрос использует механизмы подзапроса для хранения ключей и формирования SQL-запроса, а возвращает всё.
	public function compose_data(...$keys)
	{
		return $this->data;
	}
}

?>