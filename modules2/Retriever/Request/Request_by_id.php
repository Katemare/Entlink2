<?
namespace Pokeliga\Retriever;

class Request_by_id extends Request_by_unique_field
// такая реализация создаёт лишнюю копию кэша БД, но поскольку php использует принцип "copy on write", а содержимое записей не меняется, то лишний расход памяти должен быть небольшим.
{	
	public function __construct($table=null)
	{
		parent::__construct($table, 'id');
		$retriever=Retriever();
		$data=$retriever->data_by_table($table);
		if (!($data instanceof \Report_impossible)) $this->data=$data;
		
		// благодаря этому данный запрос будет получать данные по айди даже в случае, если они были получены другим запросом.
		
		$call=$this->data_hook_call();
		if ($call!==null) $retriever->add_call($call, 'stored_'.$this->table);
	}
	
	public function data_hook_call()
	{
		return
			function()
			{
				$this->data+= Retriever()->data[$this->table]; // совпадающие ключи не будут переписаны.
			};
	}

	public static function make_Multiton_class_name($args)
	{
		return static::std_make_Multiton_class_name($args);
	}
	
	// записывать результаты специально не требуется, потому что это уже делается при срабатывании крючка.
	public function record_result($result) { }
}

trait Request_using_id_and_group
{
	public function create_query()
	{
		$query=parent::create_query();
		$query['where']['id_group']=$this->id_group;
		return $query;
	}
}

class Request_by_id_and_group extends Request_by_id
{
	use
		Request_using_id_and_group,
		Request_field_is_unique; // восстанавливаем стандартное поведение.
		
	static $instances=[];
	
	public
		$field='id', // для хранения результатов
		$id_group;

	public function __construct($table=null, $id_group=null)
	{
		parent::__construct($table);
		$this->id_group=$id_group;
	}
	
	public function data_hook_call() { } // не требуется.
}

// класс для работы с общими таблицами, у которых больше одной записи для каждого идентификатора сущности.
class Request_by_id_and_group_multiple extends Request_by_field
{
	use Request_using_id_and_group;
	
	static $instances=[];
	
	public
		$id_group;
		
	public function __construct($table=null, $id_group=null)
	{
		parent::__construct($table, 'id');
		$this->id_group=$id_group;
	}
	
	public function data_hook_call() { } // не требуется.
}

?>