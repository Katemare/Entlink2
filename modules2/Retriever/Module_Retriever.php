<?
namespace Pokeliga\Retriever;

class Module_Retriever extends \Pokeliga\Entlink\Module
{
	use \Pokeliga\Entlink\Module_autoload_by_beginning;
	
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
			
			'Request'					=>'Request',
			'Task_processes_request'	=>'Request',
			'RequestTicket'				=>'Request',
			
			'Query'						=>'Query',
			'QueryComposer'				=>'QueryComposer',
			
			'Request_links_with_relations'	=>'Request_links',
			'Request_generic_links'			=>'Request_links',
			
			'Request_reuser'			=>'Request_reuser',
			'Request_limited'			=>'Request_reuser',
			'Request_random'			=>'Request_reuser',
			'Request_ordered'			=>'Request_reuser',
			'Request_page'				=>'Request_reuser',
			'Request_count'				=>'Request_reuser',
			'Request_group_functions'	=>'Request_reuser',
//			'Request_grouped_functions'	=>'Request_reuser',
			'RequestTicket_union'		=>'Request_reuser',
			'RequestTicket_count'		=>'Request_reuser',
			'Request_modify_by_calls'	=>'Request_reuser',
			'RequestTicket_modify_query'=>'Request_reuser',
			
			'RetrieverOperator_mysqli'	=>true,
			'QueryComposer_mysql'		=>true
		],
		$classex='(?<file>Request)';
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