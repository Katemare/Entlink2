<?

namespace Pokeliga\Retriever;

abstract class QueryComposer
{
	use \Pokeliga\Entlink\Shorthand;
	
	public
		$operator,
		$query,
		$query_obj;
		
	public static function with_query($query, $operator)
	{
		$composer=new static();
		if (is_array($query)) $query=Query::from_array($query);
		$composer->query_obj=$query;
		$composer->query=&$query->query; // для быстроты доступа.
		$composer->operator=$operator;
		return $composer;
	}
	
	abstract public function compose();
}

?>