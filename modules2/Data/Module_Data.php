<?
class Module_Data extends Module implements Templater
{
	use Module_autoload_by_beginning;
	static $instance=null;
	
	public
		$name='Data',
		$track_code='data',
		$quick_classes=
		[
			'Validator'=>'Validator',
			'Converter'=>'Converter',
			'Compacter'=>'Compacter',
			
			'Template_value_delay'=>'Data_Template',
			'Template_found_options'=>'Data_Template',
			'Template_list'=>'Data_Template',
			'Template_list_call'=>'Data_Template',
			
			'Value'=>'Value',
			'ValueModel'=>'Value',
			
			'Value_array'=>'Value_array',
			'Value_int_array'=>'Value_array',
			'Value_keyword_array'=>'Value_array',
			'Value_serialized_array'=>'Value_array',
			
			'ValueModel'=>'Data_interfaces',
			'ValueLink'=>'Data_interfaces',
			'ValueContent'=>'Data_interfaces',
			'ValueHost'=>'Data_interfaces',
			
			'ValueSet'=>'ValueSet',
			'MonoSet'=>'ValueSet',
			
			'InputSet'=>'InputSet',
			'InputSet_complex'=>'InputSet',
			'Multistage_input'=>'InputSet',
			
			'Task_for_value'=>'Filler',
			'Filler'=>'Filler',
			
			'Value_time'=>'Data_time',
			'Value_timespan'=>'Data_time',
			'Value_timetable'=>'Data_time',
			'Template_period'=>'Data_time'
		],
		$classex='/^(?<file>Value|ValueModel|Compacter|Validator|Convert_time_to)[_$]/',
		$class_to_file=['Convert_time_to'=>'Data_time', 'ValueModel'=>'Value', 'Value'=>'Value_types' /* просто Value покрывается быстрым подключением. */ ];
		
	public function template($code, $line=[])
	{
		if ($code==='display_timespan')
		{
			if (array_key_exists('seconds', $line)) $timespan=$line['seconds'];
			elseif (array_key_exists(0, $line)) $timespan=$line[0];
			else return 'UNKNOWN TIMESPAN';
			$value=Value_timespan::from_content($timespan);
			return $value->default_template($line);
		}
		elseif ($code==='display_period')
		{
			return Template_period::with_line($line);
		}
		elseif ($code==='display_timestamp')
		{
			if (array_key_exists('timestamp', $line)) $timestamp=$line['timestamp'];
			elseif (array_key_exists(0, $line)) $timestamp=$line[0];
			else return 'UNKNOWN TIMESTAMP';
			$value=Value_time::from_content($timestamp);
			return $value->default_template($line);
		}
		elseif ($code==='time') return time();
	}
}

?>