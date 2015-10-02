<?
namespace Pokeliga\Form;

class Module_Form extends \Pokeliga\Entlink\Module
{
	use \Pokeliga\Entlink\Module_autoload_by_beginning;
	static $instance=null;
	
	public
		$name='Form',
		$class_shorthands=
		[
			'Pokeliga\Form\Template_field'=>
			[
				'input', 'input_line', 'input_number',
				'hidden',
				'checkbox', 'textarea', 'button', 'submit', 'radio',
				'select', 'select_searchable', 'multiselect', 'radios'
			],
			'Pokeliga\Form\FieldSet'=>
			[
				'date', 'monthday', 'daytime',
				'monthday_period', 'daytime_period', 'timetable',
				'list', 'multiselect', 'slugselect', 'dual_slugselect', 'entity_list'
			],
			'Pokeliga\Data\ValueType'=>
			[
				'month_day', 'month', 'weekday', 'year', 'hour', 'minute',
				'search'
			]
		],
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
			'FieldSet_entity'=>'Form_entity',
			
			'FieldSet_entity_list'=>'FieldSet_entity_list',
			
			'Page_form_api'=>'Form_API',
			
			'Template_field_select'=>'Template_field_select',
			'Template_field_multiselect'=>'Template_field_select',
			'Template_field_option'=>'Template_field_select',
			'Template_field_radios'=>'Template_field_select',
			'Template_field_radios_inline'=>'Template_field_select',
			'Template_field_select_searchable'=>'Template_field_select'
		],
		$classex='(?<file>FieldSet|Form|Template_field_select|Template_field)[_$]';
	
	public function spawn_default_page($route=[])
	{
		return new Page_form();
	}
}

?>