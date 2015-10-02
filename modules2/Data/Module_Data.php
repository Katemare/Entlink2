<?
namespace Pokeliga\Data;

class Module_Data extends \Pokeliga\Entlink\Module
{
	use \Pokeliga\Entlink\Module_autoload_by_beginning;
	static $instance=null;
	
	public
		$name='Data',
		$track_code='data',
		$class_shorthands=
		[
			'Pokeliga\Data\ValueType'=>
			[
				'auto', 'null',
				'bool',
				'number', 'int', 'unsigned_int', 'percent', 'unsigned_number',
				'string', 'html', 'title', 'text', 'keyword', 'url',
				'enum',
				'array', 'int_array', 'keyword_array', 'title_array',
				'timestamp', 'timespan',
				'object',
				'file_address', 'dirname', 'filename', 'file_address_fragment', 'file_address_component', 'file_extension'
			],
			'Pokeliga\Data\Convert'=>
			[
				'time_to_hours', 'time_to_days'
			],
			'Pokeliga\Data\Validator'=>
			[
				'greater', 'greater_or_equal', 'less', 'less_or_equal', 'not_equal', 'not_equal_strict',
				'greater_than_sibling', 'greater_or_equal_to_sibling',
				'less_than_sibling', 'less_or_equal_to_sibling',
				'not_equal_to_sibling', 'not_equal_to_sibling_strict',
				'file_by_address_exists', 'file_by_address_doesnt_exist'
			]
		],
		$quick_classes=
		[
			'DataFront'			=>'Module_DataFront',
			'TimeTrack'			=>'TimeTrack',
			'Value'				=>'Value/Value',
			'ValueType'			=>'Value/ValueType',
			'Convert'			=>'Convert',
			
			'Compacter'			=>'Compacter',
			'Need_commandline'	=>'Compacter',
			
			'Validator'			=>'Validator/Validator',
			
			'RegisterSet'				=>'ValueSet/RegisterSet',
			'Value_has_registers'		=>'ValueSet/RegisterSet',
			'Value_registers'			=>'ValueSet/RegisterSet',
			
			'Pathway'					=>'Pathway',
			'Task_resolve_track'		=>'Pathway',
			
			'Template_value_delay'		=>'Data_Template',
			'Template_found_options'	=>'Data_Template',
			'Template_list'				=>'Data_Template',
			'Template_list_call'		=>'Data_Template',
			
			'ValueType_array'			=>'Value/Value_array',
			'ValueType_int_array'		=>'Value/Value_array',
			'ValueType_keyword_array'	=>'Value/Value_array',
			'ValueType_title_array'		=>'Value/Value_array',
			
			'ValueModel'				=>'Data_interfaces',
			'ValueLink'					=>'Data_interfaces',
			'ValueContent'				=>'Data_interfaces',
			'ValueHost'					=>'Data_interfaces',
			'ValueType_handles_fill'	=>'Data_interfaces',
			
			'ValueSet'					=>'ValueSet/ValueSet',
			'MonoSet'					=>'ValueSet/ValueSet',
			
			'InputSet'					=>'ValueSet/InputSet',
			'InputSet_complex'			=>'ValueSet/InputSet',
			'Multistage_input'			=>'ValueSet/InputSet',
			
			'Task_for_value'			=>'Filler',
			'Filler'					=>'Filler',
			
			'ValueType_timestamp'			=>'Time/Value_time',
			'ValueType_timespan'			=>'Time/Value_time',
			// 'ValueType_timetable'			=>'Data_time',
			'Template_period'			=>'Time/Template_time',
			
			'ValueType_file_address'	=>'Value/Value_files',
			'ValueType_dirname'			=>'Value/Value_files',
			'ValueType_filename'		=>'Value/Value_files',
			'ValueType_file_extension'	=>'Value/Value_files',
			'ValueType_file_address_component'	=>'Value/Value_files',
			'ValueType_file_address_fragment'	=>'Value/Value_files',
			'Validator_file_by_address_exists'	=>'Value/Value_files',
			'Validator_file_by_address_doesnt_exist'=>'Value/Value_files',
		],
		$classex='(?<file>ValueType|ValueModel|Compacter|Validator_greater|Validator_less|Validator_not_equal|Convert_time_to)',
		$class_to_file=
		[
			'Convert_time_to'	=>'Time/Convert_time',
			'Validator_greater'	=>'Validator/Validator_comparison',
			'Validator_less'	=>'Validator/Validator_comparison',
			'Validator_not_equal'=>'Validator/Validator_comparison',
			'ValueModel'		=>'Data_interfaces',
			'ValueType'			=>'Value/Value_types'
		];
}

?>