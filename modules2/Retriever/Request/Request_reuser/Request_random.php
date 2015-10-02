<?
namespace Pokeliga\Retriever;

/**
* Делает случайную выборку, при необходимости ограниченную.
*/
class Request_random extends Request_reuser
{
	use Request_reuser_capture_result;

	/**
	* @var $limit null|int Сколько записей выбрать. Если null, то все.
	*/
	protected
		$limit=null;
	
	/**
	* @param $limit null|int Сколько записей выбрать. Если null, то все.
	*/
	public function __construct($ticket, $limit=null)
	{
		parent::__construct($ticket);
		$this->limit=$limit;
	}
	
	protected function modify_query($query)
	{
		$query['order']=[ ['expression'=>'RAND()'] ];
		if ($this->limit!==null) $query['limit']=[0, $this->limit];
		return $query;
	}
	
	public function set_data(...$keys)
	{
		$result=parent::set_data();
		if ( ($result===true) && ($this->completed()) ) return $this->sign_report(new \Report_impossible('new_keys_after_completion'));
		return $result;
	}
}

?>