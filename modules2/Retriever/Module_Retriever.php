<?
class Module_Retriever extends Module
{
	use Module_autoload_by_beginning;
	static $instance=null;
	
	public
		$name='Retriever',
		$quick_classes=
		[
			'Retriever'					=>'Retriever',
			
			'Request'					=>'Request',
			'Task_processes_request'	=>'Request',
			'RequestTicket'				=>'Request',
			
			'Query'						=>'Query',
			
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
			
			'Retriever_mysqli'			=>'Retriever_mysqli'
		],
		$classex='/^(?<file>Retriever|Request)/';
}

?>