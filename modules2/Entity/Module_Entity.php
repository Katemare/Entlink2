<?
namespace Pokeliga\Entity;

class Module_Entity extends \Pokeliga\Entlink\Module
{
	use \Pokeliga\Entlink\Module_autoload_by_beginning;
	static $instance=null;
	
	public
		$name='Entity',
		$slugs=['entity', 'entities'],
		$class_shorthands=
		[
			'Pokeliga\Data\ValueType'=>
			[
				'entity', 'id_group', 'reference', 'ids',
				'linkset', 'linkset_ordered'
			],
			'Pokeliga\Entity\Keeper'=>
			[
				'db', 'id_and_group', 'var', 'var_array'
			],
			'Pokeliga\Data\Validator'=>
			[
				'subentity_exists', 'subentity_not_self', 'subentity_group_is', 'subentity_value_is', 'subentity_backlinks',
				'all_ids_exist', 'id_in_range'
			]
		],
		$quick_classes=
		[
			'Entity'						=>'Entity',
			'EntityPool'					=>'EntityPool',
			'Aspect'						=>'Aspect',
			'DataSet'						=>'DataSet',
			'Select'						=>'Select',
			'Value_of_entity'				=>'Value_of_entity',
			
			'Select_from_ids'				=>'Select_from_ids',
			'Select_special_from_complex'	=>'Select_from_ids',
			'Select_complex'				=>'Select_from_ids',
			
			'Select_from_siblings'			=>'Select_from_siblings',
			'Select_linked_to_siblings'		=>'Select_from_siblings',
			'Select_filter_siblings'		=>'Select_from_siblings',
			
			'LinkSet'						=>'LinkSet',
			'ValueType_linkset'				=>'LinkSet',
			'ValueType_linkset_ordered'		=>'LinkSet',
			
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
			
			'ValueType_entity'				=>'Entity_Data',
			'ValueType_ids'					=>'Entity_Data',
			'ValueType_own_id'				=>'Entity_Data',
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
			
			'Provider'						=>'Provider',
			
			'Request_entity_search'			=>'Entity_Request',
			
			'Test'							=>'Test'
		],
		$classex='(?<file>Keeper|Filler_for_entity|Provide|Select|Template_entity)[_$]',
		$class_to_file=['Provide'=>'Provider'];
}

?>