<?

namespace Pokeliga\Entity;

class Request_entity_search extends \Pokeliga\Retriever\Request_reuser
{
	use \Pokeliga\Retriever\Request_reuser_capture_result;
	
	public
		$entity_type,
		$search;
		
	public function __construct($ticket, $entity_type, $search)
	{
		parent::__construct($ticket);
		$this->entity_type=$entity_type;
		$this->search=$search;
	}
	
	public function create_subrequest($ticket=null)
	{
		$entity_type=$this->entity_type;
		if ($ticket===null) $ticket=$this->ticket;
		$search_ticket=$entity_type::transform_search_ticket($this->search, $ticket);
		return parent::create_subrequest($search_ticket);
	}
	
	public function modify_query($query) { return $query; } // модификаций не требуется, мы уже сделали их на этапе создания запроса.
}

?>