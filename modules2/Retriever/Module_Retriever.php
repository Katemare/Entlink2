<?
namespace Pokeliga\Retriever;

class Module_Retriever extends \Pokeliga\Entlink\Module
{	
	const
		FRONT_CLASS_NAME='Pokeliga\Retriever\RetrieverFront';
	
	static $instance=null;
	
	public
		$name='Retriever',
		$class_shorthands=
		[
			'Pokeliga\Retriever\RetrieverOperator'=>
			[
				'mysqli'
			],
			'Pokeliga\Retriever\QueryComposer'=>
			[
				'mysql'
			]
		],
		$quick_classes=
		[
			'Retriever'					=>'Retriever',
			
			'Request'					=>'Request/Request',
			'Task_processes_request'	=>'Request/Request',
			
			'RequestTicket'				=>'RequestTicket/RequestTicket',
			'Task_request_get_data'		=>'RequestTicket/RequestTicket_existing_request',
			'RequestTicket_existing_request'=>'RequestTicket/RequestTicket_existing_request',
			
			'Request_single'			=>'Request/Request_single',
			'Request_update'			=>'Request/Request_single',
			'Request_insert'			=>'Request/Request_single',
			'Request_delete'			=>'Request/Request_single',
			
			'Request_by_field'			=>'Request/Request_by_field',
			'Request_by_unique_field'	=>'Request/Request_by_field',
			'Request_links'				=>'Request/Request_by_field',
			'Request_by_field_spectrum'	=>'Request/Request_by_field',
			
			'Request_by_id'				=>'Request/Request_by_id',
			'Request_by_id_and_group'	=>'Request/Request_by_id',
			'Request_by_id_and_group_multiple'=>'Request/Request_by_id',
			
			'Query'						=>'Query/Query',
			'QueryComposer'				=>'Query/QueryComposer',
			
			'Request_links_with_relations'	=>'Request/Request_links',
			'Request_generic_links'			=>'Request/Request_links',
			
			'Request_reuser'			=>'Request/Request_reuser/Request_reuser',
			'Request_limited'			=>'Request/Request_reuser/Request_ordered',
			'Request_ordered'			=>'Request/Request_reuser/Request_ordered',
			'Request_page'				=>'Request/Request_reuser/Request_ordered',
			'Request_random'			=>'Request/Request_reuser/Request_random',
			'Request_count'				=>'Request/Request_reuser/Request_group',
			'Request_group_functions'	=>'Request/Request_reuser/Request_group',
//			'Request_grouped_functions'	=>'Request_reuser',
			'RequestTicket_union'		=>'Request/Request_reuser/Request_union',
			'RequestTicket_count'		=>'Request/Request_reuser/Request_group',
			'Request_modify_by_calls'	=>'Request/Request_reuser/Request_modify',
			'RequestTicket_modify_query'=>'Request/Request_reuser/Request_modify',
			'Request_search'			=>'Request/Request_reuser/Request_search',
			'Request_search_text'		=>'Request/Request_reuser/Request_search',
			
			'RetrieverOperator_mysqli'	=>true,
			'QueryComposer_mysql'		=>'Query/QueryComposer_mysql'
		];
}

class RetrieverFront extends \Pokeliga\Entlink\ModuleFront
{
	public
		$retriever;
		
	public function get_retriever()
	{
		if ($this->retriever===null) $this->retriever=new Retriever($this->config);
		return $this->retriever;
	}
}
?>