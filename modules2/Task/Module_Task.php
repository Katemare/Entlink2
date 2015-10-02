<?
namespace Pokeliga\Task;

class Module_Task extends \Pokeliga\Entlink\Module
{
	static $instance=null;

	public
		$name='Task',
		$quick_classes=
		[
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