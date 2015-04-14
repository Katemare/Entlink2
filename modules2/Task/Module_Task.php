<?
class Module_Task extends Module
{
	static $instance=null;

	public
		$name='Task',
		$quick_classes=
		[
			'Task'=>'Task',
			'Dependancy_call'=>'Task',
			'Task_delayed_call'=>'Task',
			
			'Process'=>'Process',
			'Process_single_goal'=>'Process',
			'Process_collection'=>'Process',
			'Process_collection_absolute'=>'Process'
		];
}

?>