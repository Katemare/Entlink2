<?

namespace Pokeliga\Data;

class TimeTrack implements Pokeliga\Template\Templater
{
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