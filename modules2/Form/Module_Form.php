<?
class Module_Form extends Module
{
	use Module_autoload_by_beginning;
	static $instance=null;
	
	public
		$name='Form',
		$quick_classes=
		[
			'Form'=>'Form',
			'Page_form'=>'Form',
			
			'FieldSet'=>'FieldSet',
			'FieldSet_sub'=>'FieldSet',
			'Template_requies_fieldset'=>'FieldSet',
			
			'Template_field'=>'Template_field',
			
			'Form_search'=>'Form_search',
			'Value_search'=>'Form_search',
			'Task_process_search_form'=>'Form_search',
			
			'FieldSet_date'=>'FieldSet_date',
			'FieldSet_timetable'=>'FieldSet_date',
			
			'FieldSet_list'=>'FieldSet_list',
			'FieldSet_multiselect'=>'FieldSet_list',
			'FieldSet_slugselect'=>'FieldSet_list',
			'FieldSet_dual_slugselect'=>'FieldSet_list',
			
			'Form_entity'=>'Form_entity',
			
			'FieldSet_entity_list'=>'FieldSet_entity_list',
			
			'Page_form_api'=>'Form_API',
			
			'Template_field_select'=>'Template_field_select',
			'Template_field_multiselect'=>'Template_field_select',
			'Template_field_option'=>'Template_field_select',
			'Template_field_radios'=>'Template_field_select',
			'Template_field_radios_inline'=>'Template_field_select'
		],
		$classex='/^(?<file>FieldSet|Form|Template_field_select|Template_field)[_$]/';
}

?>