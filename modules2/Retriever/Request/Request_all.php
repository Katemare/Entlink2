<?
namespace Pokeliga\Retriever;

/**
* Запрашивает все данные из таблицы.
*/
class Request_all extends Request
{
	use \Pokeliga\Entlink\Multiton, Request_no_keys
	{
		Request_no_keys::compose_data as std_compose_data;
	}
	
	/**
	* @var string $table Название таблицы.
	*/
	protected
		$table=null;
	
	public function create_query()
	{
		$query=
		[
			'action'=>'select',
			'table'=>$this->table
		];
		return $query;
	}
	
	/**
	* @param string $table Название таблицы.
	*/
	public function __construct($table)
	{
		$this->table=$table;
		parent::__construct();
	}
	
	public function compose_data()
	{
		return Retriever()->data_by_table($this->table);
	}
}

?>