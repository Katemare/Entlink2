<?
namespace Pokeliga\Retriever;

/**
* Этот класс отвечает за поиск по содержимому поля.
*/
class Request_search extends Request_reuser
{
	use \Pokeliga\Entlink\Multiton, Request_reuser_capture_result;
	
	/**
	* @var array|string $search_field Название поля, по которому проводить поиск.
	* @see \Pokeliga\Retriever\Query Правила записи полей.
	* @var mixed $search Искомое содержимое поля.
	* @var string $op Оператор, с помощью которого осуществляется поиск.
	*/
	protected
		$search_field,
		$search,
		$op='=';
	
	/**
	* @param string $search_field Название поля, по которому проводить поиск.
	* @param mixed $search Искомое содержимое поля.
	*/
	public function __construct($ticket, $search_field, $search)
	{
		parent::__construct($ticket);
		$this->search_field=$search_field;
		$this->search=$search;
	}
	
	protected function modify_query($query)
	{
		$query=Query::from_array($query);
		$query->add_complex_condition(['field'=>$this->search_field, 'op'=>$this->op, 'value'=>$this->compare_to()]);
		return $query;
	}
	
	/**
	* Значение, подставляемое в поисковое условие.
	* @return mixed
	*/
	protected function compare_to()
	{
		return $this->search;
	}
}

/**
* Поиск по подобию строке.
*/
class Request_search_text extends Request_search
{
	static $instances=[];
	
	protected
		$op='LIKE';
		
	protected function compare_to()
	{
		$like=Retriever()->safe_text_like($this->search).'%'; // FIXME: следует сделать иначе, в рамках QueryComposer.
		if ( (mb_strlen($this->search)>1) || (is_numeric($this->search)) ) $like='%'.$like;
		return $like;
	}
}

?>