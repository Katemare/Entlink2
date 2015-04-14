<?
class Module_Cron extends Module implements Templater
{
	const
		HOURLY_MINUTES=5,
		
		DAILY_HOURS=0,
		DAILY_MINUTES=10;

	//use Module_autoload_by_beginning;
	static $instance=null;
	
	public
		$name='Cron',
		$track_code='cron';
		
	public function template($code, $line=[])
	{
		if (array_key_exists('time', $line)) $time=$line['time']; else $time=time();
		if (array_key_exists('plus', $line)) $plus=$line['plus']; else $plus=0;
		if ($code==='next_daily')
		{
			$date=getdate($time);
			if ( ($date['hours']<=static::DAILY_HOURS) && ($date['minutes']<static::DAILY_MINUTES) )
				return mktime(static::DAILY_HOURS, static::DAILY_MINUTES, 0, $date['mon'], $date['mday'], $date['year'])+$plus;
			else
				return mktime(static::DAILY_HOURS, static::DAILY_MINUTES, 0, $date['mon'], $date['mday']+1, $date['year'])+$plus;
		}
		elseif ($code==='next_hourly')
		{
			$date=getdate($time);
			if ($date['minutes']<static::HOURLY_MINUTES)
				return mktime($date['hours'], static::HOURLY_MINUTES, 0, $date['mon'], $date['mday'], $date['year'])+$plus;
			else
				return mktime($date['hours']+1, static::HOURLY_MINUTES, 0, $date['mon'], $date['mday'], $date['year'])+$plus;
		}
		elseif ($code==='last_daily')
		{
			if (array_key_exists('days', $line)) $days=(int)$line['days'];
			else $days=0;
			
			$date=getdate($time);
			if
			(
				($date['hours']>static::DAILY_HOURS) ||
				( ($date['hours']==static::DAILY_HOURS)&&($date['minutes']>static::DAILY_MINUTES) )
			)
				// те же сутки, ранее.
				return mktime(static::DAILY_HOURS, static::DAILY_MINUTES, 0, $date['mon'], $date['mday']-$days, $date['year'])+$plus;
			else
				// предыдущие сутки (сегодняшений прогон ещё не наступил)
				return mktime(static::DAILY_HOURS, static::DAILY_MINUTES, 0, $date['mon'], $date['mday']-1-$days, $date['year'])+$plus;
		}
		elseif ($code==='last_hourly')
		{
			$date=getdate($time);
			if
			($date['minutes']>static::HOURLY_MINUTES)
				return mktime($date['hours'], static::HOURLY_MINUTES, 0, $date['mon'], $date['mday'], $date['year'])+$plus;
			else
				return mktime($date['hours']-1, static::HOURLY_MINUTES, 0, $date['mon'], $date['mday']-1, $date['year'])+$plus;
		}
	}
}