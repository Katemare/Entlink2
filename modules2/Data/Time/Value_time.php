<?

namespace Pokeliga\Data;

class ValueType_timestamp extends ValueType_unsigned_int implements Value_has_registers
{
	use Value_registers;
	
	const
		DEFAULT_FORMAT='j M Y H:i';
	
	public
		$reg_model=
		[
			'year'	=>
			[
				'type'=>'unsigned_int',
				'extract_call'=>['extract_time_component', 'Y']
			],
			'month'	=>
			[
				'type'=>'unsigned_int',
				'min'=>1, 'max'=>12,
				'extract_call'=>['extract_time_component', 'n']
			],
			'day'	=>
			[
				'type'=>'unsigned_int',
				'min'=>1, 'max'=>31,
				'extract_call'=>['extract_time_component', 'j']
			],
			'hour'	=>
			[
				'type'=>'unsigned_int',
				'min'=>0, 'max'=>23,
				'extract_call'=>['extract_time_component', 'G']
			],
			'min'	=>
			[
				'type'=>'unsigned_int',
				'min'=>0, 'max'=>59,
				'extract_call'=>['extract_time_component', 'i']
			],
			'sec'	=>
			[
				'type'=>'unsigned_int',
				'min'=>0, 'max'=>59,
				'extract_call'=>['extract_time_component', 's']
			],
		];
	
	public function for_display($format=null, $line=[])
	{
		if ($format===null) $format=static::DEFAULT_FORMAT;
		return date($format, $this->content());
	}
	
	public static function type_conversion($content)
	{
		if (!is_numeric($content) and is_string($content))
		{
			$content=strtotime($content);
			if ($content===false) return new \Report_impossible('bad_timestamp');
		}
		return parent::type_conversion($content);
	}
	
	public function compose_from_regs($data)
	{
		// FIX - заменить на ?? в PHP7
		$args=
		[
			empty($data['hour']) ? 0 : $data['hour'],
			empty($data['min']) ? 0 : $data['min'],
			empty($data['sec']) ? 0 : $data['sec'],
			empty($data['month']) ? 1 : $data['month'],
			empty($data['day']) ? 1 : $data['day'],
			empty($data['year']) ? 1970 : $data['year'],
		];
		$result=mktime(...$args);
		if ($result===false) return $this->sign_report(new \Report_impossible('bad_time_regs'));
		return $result;
	}
	
	public function compose_change_by_reg($register, $reg_content)
	{
		$regs=$this->list_regs();
		$data=[];
		foreach ($regs as $reg)
		{
			if ($reg===$register) $data[$reg]=$reg_content;
			else $data[$reg]=$this->value_reg($reg);
		}
		return $this->compose_from_regs($data);
	}
	
	public function extract_time_component($format /* , $register - подаётся, но не используется. */)
	{
		$content=$this->content();
		if ($content===null) return null;
		return date($format, $content); // этот метод должен вызываться, когда содержимое уже определено.
	}
}

class ValueType_timespan extends ValueType_timestamp
{
	const
		DEFAULT_FORMAT='h';
		
	static
		$div=['d'=>86400, 'h'=>3600, 'm'=>60, 's'=>1],
		$lower_formats=['s'=>null, 'm'=>'s', 'h'=>'m', 'd'=>'h'],
		// STUB
		$convert=
		[
			's'=>['singular'=>'секунда', 'few'=>'секунды', 'many'=>'секунд'],
			'm'=>['singular'=>'минута', 'few'=>'минуты', 'many'=>'минут'],
			'h'=>['singular'=>'час', 'few'=>'часа', 'many'=>'часов'],
			'd'=>['singular'=>'день', 'few'=>'дня', 'many'=>'дней']
		];
		
	public function for_display($format=null, $line=[])
	{
		$content=$this->content();
		if ($format==='auto')
		{
			$format=null;
			foreach (static::$div as $format_code=>$div)
			{
				if ($content>$div)
				{
					$format=$format_code;
					break;
				}
			}
		}
		if ( ($format===null) || (!array_key_exists($format, static::$div)) ) $format=static::DEFAULT_FORMAT;
		
		$major_format=$format;
		$minor_format=static::$lower_formats[$major_format];
		
		$major= floor($content/static::$div[$major_format]);
		if ($minor_format!==null) $minor= floor( ($content % static::$div[$major_format]) / static::$div[$minor_format]);
		else $minor=0;
		
		$result=$major.Value_int::units($major, static::$convert[$major_format]);
		if ($minor>0) $result.='&nbsp;'.$minor.Value_int::units($minor, static::$convert[$minor_format]);
		return $result;
	}
}

/*
class Value_timetable extends Value_serialized_array
{

	/ *
	формат:
	
	для разового срока -
	'start'		=> время,
	'finish'	=> время,
	
	для периодичности -
	'weekdays'	=> 1,3,7 - понедельник, среда, воскресенье.
	'months'	=> 1,2,12 - январь, февраль, декабрь.
	'monthdays'	=> 1,4,28	- первое, четвёртое и 28-е числа месяца.
	
	'dates'		=> '1:1-1:14','2:23'  - с 1 до 14 января (включительно), 23 февраля. 
	
	'years'		=> 2014,2015 - годы.
	
	'time'		=> '0:30-12:00','12:50-13:00' - с пол первого ночи до полудня, с без десяти час дня до часу (включитьельно).
	* /
	
	const
		DATEEX='/^(?<month1>[1-9]|1[0-2])\:(?<day1>[1-9]|[1-2]\d|3[0-1])(?<end>\-(?<month2>[1-9]|1[0-2])\:(?<day2>[1-9]|[1-2]\d|3[0-1]))?$/',
		TIMEEX='/^(?<hour1>1?\d|2[0-3])\:(?<minutes1>[0-6]?\d)\-(?<hour2>1?\d|2[0-3])\:(?<minutes2>[0-6]?\d)$/';

	static
		$good_keys=['start', 'finish', 'weekdays', 'months', 'monthdays', 'dates', 'years', 'time'];
	
	public function to_good_content($content)
	{
		$result=parent::to_good_content($content);
		if ($result instanceof \Report) return $result;
		
		if ( (array_key_exists('start', $result)) && (array_key_exists('finish', $result)) && ($result['start']>=$result['finish']) ) return $this->sign_report(new \Report_impossible('bad_timetable'));
		
		return $result;
	}
	
	public function legal_element($content, $key)
	{
		if (!in_array($key, static::$good_keys, true)) return $this->sign_report(new \Report_impossible('bad_key'));
		
		if ( ($key==='start') || ($key==='finish') ) return (int)$content;
		
		if (!is_array($content)) $content=[$content];
		$content=array_unique($content);
		$func='good_'.$key;
		foreach ($content as &$cont)
		{
			$good=$this->$func($cont);
			if ($good instanceof \Report) return $good;
			$cont=$good;
		}
		
		return $content;
	}
	
	public function good_weekdays($weekday)
	{
		return min_max_int($weekday, 1, 7);
	}
	public function good_months($month)
	{
		return min_max_int($month, 1, 12);
	}
	public function good_monthdays($monthday)
	{
		return min_max_int($monthday, 1, 31);
	}
	public function good_years($year)
	{
		return min_max_int($year, 2000, 2037);
	}
	public function good_dates($date)
	{
		if (!preg_match(static::DATEEX, $date, $m)) return $this->sign_report(new \Report_impossible('bad_date'));
		
		if (!checkdate($m['month1'], $m['day1'], 2000 / * високосный * /)) return $this->sign_report(new \Report_impossible('bad_date'));
		if (empty($m['end'])) return $date;
		
		if ($m['month1']>$m['month2']) return $this->sign_report(new \Report_impossible('bad_date'));
		if ( ($m['month1']==$m['month2']) && ($m['day1']>=$m['day2']) ) return $this->sign_report(new \Report_impossible('bad_date'));
		
		if (!checkdate($m['month2'], $m['day2'], 2000)) return $this->sign_report(new \Report_impossible('bad_date'));
		
		return $date;
	}
	public function good_time($time)
	{
		if (!preg_match(static::TIMEEX, $time, $m)) return $this->sign_report(new \Report_impossible('bad_time'));
		
		if ($m['hour1']>$m['hour2']) return $this->sign_report(new \Report_impossible('bad_time'));
		if ( ($m['hour1']==$m['hour2']) && ($m['minutes1']>=$m['minutes2']) ) return $this->sign_report(new \Report_impossible('bad_time'));
		return $time;
	}
	
	public function is_on($time=null)
	{
		if (!$this->has_state(Value::STATE_FILLED))
		{
			$report=$this->request();
			if ($report instanceof \Report_impossible) return $report;
			return Task_delayed_call::with_call(new Call([$this, 'is_on'], $time), $report);
		}
		
		$timetable=$this->content();
		if ($timetable===null) return true;
		if ($time===null) $time=time();
		if ( (array_key_exists('finish', $timetable)) && ($time>=$timetable['finish']) ) return false;
		if ( (array_key_exists('start', $timetable)) && ($time<$timetable['start']) ) return false;

		$exact_date=$this->matches_exact_date($time);
		$vague_date=$this->matches_date_conditions($time);
		if ( ($exact_date!==true) && ($vague_date!==true) && ( $exact_date===false || $vague_date===false) ) return false;
		
		if ($this->matches_year($time)===false) return false;
		if ($this->matches_time($time)===false) return false;
		
		return true;
	}
	
	protected function matches_exact_date($time)
	{
		$timetable=$this->content();
		if (!array_key_exists('dates', $timetable)) return;
		$good_date=false;
		$month=date('n', $time);
		$day=date('j', $time);
		foreach ($timetable['dates'] as $date)
		{
			preg_match(static::DATEEX, $date, $m); // FIX: это в общем-то затратная операция, однако хранение в виде строки выбрано для лучшего вида сериализованного массива, хотя бы для визуального контроля админа. возможно, это можно будет упростить и ускорить при налчии абсолютно надёжной и удобной CMS.
			if ( (empty($m['end'])) && ($month==$m['month1']) && ($day==$m['day1']) ) $good_date=true;
			elseif (empty($m['end'])) continue;
			elseif ( ($month>$m['month1']) && ($month<$m['month2']) ) $good_date=true;
			elseif ( ($m['month1']==$m['month2']) && ($month==$m['month1']) && ($day>=$m['day1']) && ($day<=$m['day2']) ) $good_date=true;
			elseif ( ($m['month1']<$m['month2']) && ($month==$m['month1']) && ($day>=$m['day1']) ) $good_date=true;
			elseif ( ($m['month1']<$m['month2']) && ($month==$m['month2']) && ($day<=$m['day2']) ) $good_date=true;
			
			if ($good_date) break;
		}
		return $good_date;
	}
	
	protected function matches_date_conditions($time)
	{
		$timetable=$this->content();
		$exists=[];
		if ( ($exists[]=array_key_exists('weekdays', $timetable)) && (!in_array(date('N', $time), $timetable['weekdays'])) ) return false;
		if ( ($exists[]=array_key_exists('months', $timetable)) && (!in_array(date('n', $time), $timetable['months'])) ) return false;
		if ( ($exists[]=array_key_exists('monthdays', $timetable)) && (!in_array(date('j', $time), $timetable['monthdays'])) ) return false;
		if ( ($exists[]=array_key_exists('weekdays', $timetable)) && (!in_array(date('N', $time), $timetable['weekdays'])) ) return false;
		if (!in_array(true, $exists)) return; // если не было ни одного условия, то и проверок не было.
		return true;
	}
	
	protected function matches_year($time)
	{
		$timetable=$this->content();
		if (!array_key_exists('years', $timetable)) return;
		return in_array(date('Y', $time), $timetable['years']);
	}
	
	protected function matches_time($time)
	{
		$timetable=$this->content();
		if (!array_key_exists('time', $timetable)) return;
		
		$hour=date('G', $time);
		$minutes=date('i', $time);
		$good_time=false;
		foreach ($timetable['time'] as $period)
		{
			preg_match(static::TIMEEX, $period, $m);
			if ( ($hour>$m['hour1']) && ($hour<$m['hour2']) ) $good_time=true;
			elseif ( ($m['hour1']==$m['hour2']) && ($hour==$m['hour1']) && ($minutes>=$m['minutes1']) && ($minutes<=$m['minutes2']) ) $good_time=true;
			elseif ( ($m['hour1']<$m['hour2']) && ($hour==$m['hour1']) && ($minutes>=$m['minutes1']) ) $good_time=true;
			elseif ( ($m['hour1']<$m['hour2']) && ($hour==$m['hour2']) && ($minutes<=$m['minutes2']) ) $good_time=true;
		}
		
		return $good_time;
	}
}
*/

?>