<?
class Module_Entity extends Module
{
	use Module_autoload_by_beginning;
	static $instance=null;
	
	public
		$name='Entity',
		$slugs=['entity', 'entities'],
		$quick_classes=
		[
			'Entity'						=>'Entity',
			'EntityPool'					=>'EntityPool',
			'Aspect'						=>'Aspect',
			'DataSet'						=>'DataSet',
			'Selector'						=>'Select',
			
			'Select_from_ids'				=>'Select_from_ids',
			'Select_special_from_complex'	=>'Select_from_ids',
			'Select_complex'				=>'Select_from_ids',
			
			'Select_from_siblings'			=>'Select_from_siblings',
			'Select_linked_to_siblings'		=>'Select_from_siblings',
			'Select_filter_siblings'		=>'Select_from_siblings',
			
			'LinkSet'						=>'LinkSet',
			'Value_linkset'					=>'LinkSet',
			'Value_linkset_ordered'			=>'LinkSet',
			
			'EntityType'					=>'EntityType',
			'EntityType_untyped'			=>'EntityType',
			'Request_entity_search'			=>'EntityType',
			
			'Filler_for_entity'				=>'Filler_for_entity',
			'Filler_for_entity_generic'		=>'Filler_for_entity',
			'Filler_for_entity_value'		=>'Filler_for_entity',
			'Filler_for_entity_reference'	=>'Filler_for_entity',
			'Fill_entity_producal_value'	=>'Filler_for_entity',			
			'Fill_entity_producal_value_callback'=>'Filler_for_entity',
			
			'Keeper'						=>'Keeper',
			
			'Value_links_entity'			=>'Entity_Data',
			'Value_unkept'					=>'Entity_Data',
			'Value_id'						=>'Entity_Data',
			'Value_ids'						=>'Entity_Data',
			'Value_own_id'					=>'Entity_Data',
			'Validator_for_entity_value'	=>'Entity_Data',
			'EntitySet'						=>'Entity_Data',
			'Select_has_range_validator'	=>'Entity_Data',
			
			'Task_for_entity'				=>'Entity_Task',
			'Task_for_entity_methods'		=>'Entity_Task',
			'Task_save_new_entity'			=>'Entity_Task',
			'Task_save_entity'				=>'Entity_Task',
			'Task_for_entity_verify_id'		=>'Entity_Task',
			'Task_entity_value_request'		=>'Entity_Task',
			'Task_determine_aspect'			=>'Entity_Task',
			'Task_resolve_entity_call'		=>'Entity_Task',
			'Task_save_generic_links'		=>'Entity_Task',
			'Task_calc_aspect_right'		=>'Entity_Task',
			
			'Provider'						=>'Provider'
		],
		$classex='/^(?<file>Keeper|Filler_for_entity|Provide|Select|Template_entity)[_$]/',
		$class_to_file=['Provide'=>'Provider'];
}

?>