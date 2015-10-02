<?
namespace Pokeliga\Retriever;

/**
* Упорядочивает выборку из подзапроса.
*/
class Request_ordered extends Request_reuser
{
	use \Pokeliga\Entlink\Multiton, Request_reuser_capture_result;
		
	/**
	* @var array|string $order Параметры упорядочивания.
	*/
	protected
		$order;
	
	/**
	* @param array|string $order Параметры упорядочивания.
	*/
	public function __construct($ticket, $order='id')
	{
		parent::__construct($ticket);
		$this->order=$order;
	}
	
	protected function modify_query($query)
	{
		$query['order']=$this->order;
		return $query;
	}
}

/**
* Берёт только ограниченный набор из подзапроса. Предполагает упорядочивание, иначе следует использовать Request_random.
*/
class Request_limited extends Request_ordered
{	
	static $instances=[];
	
	/**
	* @var int $requested_limit Максимальный предел, требующийся сейчас.
	* @var false|int $completed_limit Сведения о том, до какого пункта уже получены данные.
	*/
	protected
		$requested_limit,
		$completed_limit=false;
	
	public function keys_number() { return 1; }
	
	protected function modify_query($query)
	{
		$query=parent::modify_query($query);
		if ($this->completed_limit===false) $query['limit']=[0, $this->requested_limit];
		else $query['limit']=[$this->completed_limit+1, $this->requested_limit];
		return $query;
	}
	
	public function set_data($limit=null, ...$keys)
	{
		$uncompleted_keys=parent::set_data();
		if ($limit>$this->requested_limit) $this->requested_limit=$limit;
		if ($this->requested_limit>$this->completed_limit) return true;
		return $uncompleted_keys;
	}
	
	public function compose_data($limit=null, ...$keys)
	{
		if ($this->data instanceof \Report_impossible) return $this->data;
		if ($limit>$this->completed_limit) return new \Report_impossible('limit_uncompleted', $this);
		return array_slice($this->data, 0, $limit);
	}
	
	protected function process_result($result)
	{
		if ($result instanceof \Report_impossible) return false;
		$this->completed_limit=$this->requested_limit;
		if (empty($this->data)) $this->data=[];
		$this->data=array_merge($this->data, $result);
		return true;
	}
}

/**
* Получает данные, соответствующие "странице" записей.
*/
class Request_page extends Request_ordered
{
	static $instances=[];
	
	/**
	* @var int $per_page Количество записей на страницу.
	* @var int $page Номер страницы.
	* @var bool $done Получена ли уже данная страница.
	*/
	protected
		$per_page=50,
		$page=1,
		$done=false;

	/**
	* @param int $per_page Количество записей на страницу.
	* @param int $page Номер страницы.
	*/
	public function __construct($ticket, $order='id', $page, $per_page=50)
	{
		parent::__construct($ticket, $order);
		$this->per_page=$per_page;
		$this->page=$page;
	}
	
	protected function modify_query($query)
	{
		$query=parent::modify_query($query);
		$query['limit']=[ ($this->page-1)*$this->per_page, $this->per_page];
		return $query;
	}
	
	/**
	* @throws \Exception при добавлении ключей к уже выполненному запросу.
	*/
	public function set_data(...$keys)
	{
		$uncompleted_keys=parent::set_data();
		if ( ($uncompleted_keys) && ($this->done) ) throw new \Excepion('adding more keys to Page request');
		return !$this->done;
	}
	
	public function compose_data(...$keys)
	{
		if ($this->data instanceof \Report_impossible) return $this->data;
		if (!$this->done) return new \Report_impossible('page_uncompleted', $this);
		return $this->data;
	}
	
	protected function process_result($result)
	{
		$this->done=true;
		if ($result instanceof \Report_impossible) return false;
		$this->data=$result;
		return true;
	}
}

?>