<?
namespace Pokeliga\Retriever;

/**
* Класс уникальных запросов, не складывающихся с другими.
*/
class Request_single extends Request
{
	use Request_no_keys;
	
	/**
	* @var array|\Pokeliga\Retriever\Query $query Содержит собственно формат запроса.
	*/
	protected
		$query;

	/**
	* @return \Pokeliga\Retriever\Request_single Возвращает запрос подкласса (Request_update, Request_insert или Request_delete) в соответствии с запросом. Для SELECT возвращает Request_single.
	*/
	public static function instance($query=null)
	{
		if ($query['action']==='update') $request=new Request_update($query);
		elseif ($query['action']==='delete') $request=new Request_delete($query);
		elseif (in_array($query['action'], ['insert','replace'])) $request=new Request_insert($query);
		else $request=new static($query);
		return $request;
	}
	
	public function __construct($query)
	{
		$this->query=$query;
		parent::__construct();
	}
	
	/**
	* Синоним для instance();
	* @see \Pokeliga\Retriever\Request_single::instance();
	*/
	public static function from_query($query)
	{
		return static::instance($query);
	}
	
	public function create_query()
	{
		return $this->query;
	}
}

/**
* Отвечает за запросы, добавляющие записи. Хранит insert_id.
*/
class Request_insert extends Request_single
{
	public
		$insert_id=null;
	
	public function process_result($result)
	{
		if (is_numeric($result)) // FIXME: не учитывает возможный результат false в случае insert_ignore, не затронувшего ни одной записи.
		{
			$this->insert_id=$result;
		}
		return parent::process_result($result);
	}
}

/**
* Отвечает за запросы, изменяющие существующие записи.
*/
class Request_update extends Request_single { }

/**
* Отвечает за запросы, удаляющие записи.
*/
class Request_delete extends Request_single { }

?>