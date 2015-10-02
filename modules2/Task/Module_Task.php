<?
namespace Pokeliga\Task;

class Module_Task extends \Pokeliga\Entlink\Module
{
	static $instance=null;

	public
		$name='Task',
		$global_classes=['Report_delay', 'Report_dependant', 'Report_dep', 'Report_deps', 'Report_task', 'Report_tasks', 'Report_promise'],
		$quick_classes=
		[
			'Report_delay'		=>'Report_delay',
			'Report_dependant'	=>'Report_delay',
			'Report_dep'		=>'Report_delay',
			'Report_deps'		=>'Report_delay',
			'Report_tasks'		=>'Report_delay',
			'Report_task'		=>'Report_delay',
			'Report_promise'	=>'Report_delay',
			
			'Task'				=>'Task',
			'Dependancy_call'	=>'Task',
			'Task_delayed_call'	=>'Task',
			
			'Process'				=>'Process',
			'Process_single_goal'	=>'Process',
			'Process_collection'	=>'Process',
			'Process_collection_absolute'=>'Process',
			
			'Need'		=>'Need',
			'Need_one'	=>'Need',
			'Need_all'	=>'Need',
			'Need_call'	=>'Need',
			
			'Coroutine'			=>'Coroutine',
			'Task_coroutine'	=>'Coroutine',
			'Need_subroutine'	=>'Coroutine',
			
			'Task_dice'	=>'Task_test',
			'Task_fail'	=>'Task_test',
			'Task_dice_coroutine'=>'Task_test'
		];
}

?>