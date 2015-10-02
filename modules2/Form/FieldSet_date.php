<?
namespace Pokeliga\Form;

class FieldSet_date extends FieldSet_sub
{
	use \Pokeliga\Data\Multistage_input;
	
	const
		NOW='n',
		NOW_PLUS='p',
		STAGE_NOW=0,
		STAGE_DATE=1,
		
		XML_EXPORT=__CLASS__; // TEST
	
	public
		$template_db_key='form.date',
		$model_stages=
		[
			self::STAGE_DATE=>['day', 'month', 'year', 'hour', 'minute']
		],
		$model_stage=self::STAGE_NOW,
		$input_fields=['now'],
		$model=
		[
			'day'=>
			[
				'type'=>'month_day',
				'template'=>'select',
				'validators'=>['month_day'],
				'month_source'=>'month',
				'year_source'=>'year'
			],
			'month'=>
			[
				'type'=>'month',
				'template'=>'select'
			],
			'year'=>
			[
				'type'=>'enum',
				'template'=>'select',
				'options'=>[2013=>2013, 2014=>2014, 2015=>2015]
			],
			'hour'=>
			[
				'type'=>'hour',
				'template'=>'input_number'
			],
			'minute'=>
			[
				'type'=>'minute',
				'template'=>'input_number'
			],
			'now'=>
			[
				'type'=>'bool',
				'template'=>'checkbox',
				'default_for_display'=>true
			]
		];
	
	public function update_subfields()
	{
		if ( ($this->model_stage===static::STAGE_NOW) && (!$this->content_of('now')) ) return $this->change_model_stage(static::STAGE_DATE);
		return false;
	}
	
	public function process_valid()
	{
		if ($this->content_of('now')) $result=time();
		else $result=mktime($this->content_of('hour'), $this->content_of('minute'), 0, $this->content_of('month'), $this->content_of('day'), $this->content_of('year'));
		$this->process_success=true;
		return $this->sign_report(new \Report_resolution($result));
	}
	
	public function set($content, $source_code=Value::BY_OPERATION)
	{
		$time=time();
		if ($content===static::NOW) $target_time=$time;
		elseif ( (is_array($content)) && ($content[0]===static::NOW_PLUS) ) $target_time=time()+$content[1];
		else $target_time=$content;
		
		$data=getdate($target_time);
		
		$set=['day'=>$data['mday'], 'month'=>$data['mon'], 'year'=>$data['year'], 'hour'=>$data['hours'], 'minute'=>$data['minutes'], 'now'=>$content===static::NOW];
		$this->set_by_array($set, $source_code);
	}
}

// ввод только месяца и числа.
class FieldSet_monthday extends FieldSet_date
{
	public
		$erase_fields=['now', 'year', 'hour', 'minute'],
		$template_db_key='form.monthday',
		$model_stage=FieldSet_date::STAGE_NOW;
		
	public function supply_model()
	{
		parent::supply_model();
		foreach ($this->erase_fields as $code)
		{
			unset($this->model[$code]);
		}
		$this->input_fields=array_keys($this->model);
	}
		
	public function update_subfields()
	{
		return false;
	}
	
	public function process_valid()
	{
		$result=$this->content_of('month').':'.$this->content_of('day'); // не очень удобный формат, но такой, который пока используется.
		$this->process_success=true;
		return $this->sign_report(new \Report_resolution($result));
	}
	
	public function set($content, $source_code=Value::BY_OPERATION)
	{
		$date=explode(':', $content);
		if (count($date)!=2) return $this->sign_report(new \Report_impossible('bad_monthday'));
		
		$set=['month'=>$date[0], 'day'=>$date[1]];
		$this->set_by_array($set, $source_code);
	}
}

class FieldSet_monthday_period extends FieldSet_sub
{
	public
		$model=
		[
			'start'=>
			[
				'fieldset_type'=>'monthday'
			],
			'finish'=>
			[
				'fieldset_type'=>'monthday'
			]
		],
		$template_db_key='form.period';

	public function process_valid()
	{
		$result=$this->content_of('start').'-'.$this->content_of('finish');
		$this->process_success=true;
		return $this->sign_report(new \Report_resolution($result));
	}
	
	public function set($content, $source_code=Value::BY_OPERATION)
	{
		$dates=explode('-', $content);
		$set=['start'=>$dates[0], 'finish'=>$dates[1]];
		$this->set_by_array($set, $source_code);
	}
}

// ввод только часов и минут.
class FieldSet_daytime extends FieldSet_date
{
	public
		$erase_fields=['now', 'year', 'month', 'day'],
		$template_db_key='form.time',
		$model_stage=FieldSet_date::STAGE_NOW;
		
	public function supply_model()
	{
		parent::supply_model();
		foreach ($this->erase_fields as $code)
		{
			unset($this->model[$code]);
		}
		$this->input_fields=array_keys($this->model);
	}
		
	public function update_subfields()
	{
		return false;
	}
	
	public function process_valid()
	{
		$result=$this->content_of('hour').':'.$this->content_of('minute'); // не очень удобный формат, но такой, который пока используется.
		$this->process_success=true;
		return $this->sign_report(new \Report_resolution($result));
	}
	
	public function set($content, $source_code=Value::BY_OPERATION)
	{
		$date=explode(':', $content);
		if (count($date)!=2) return $this->sign_report(new \Report_impossible('bad_monthday'));
		
		$set=['hour'=>$date[0], 'minute'=>$date[1]];
		$this->set_by_array($set, $source_code);
	}
}

class FieldSet_daytime_period extends FieldSet_sub
{
	public
		$model=
		[
			'start'=>
			[
				'fieldset_type'=>'daytime'
			],
			'finish'=>
			[
				'fieldset_type'=>'daytime'
			]
		],
		$template_db_key='form.period';

	public function process_valid()
	{
		$result=$this->content_of('start').'-'.$this->content_of('finish');
		$this->process_success=true;
		return $this->sign_report(new \Report_resolution($result));
	}
	
	public function set($content, $source_code=Value::BY_OPERATION)
	{
		$dates=explode('-', $content);
		$set=['start'=>$dates[0], 'finish'=>$dates[1]];
		$this->set_by_array($set, $source_code);
	}
}

// WIP
/*
class FieldSet_timetable extends FieldSet_sub
{
	use Multistage_input;
	
	const
		STAGE_INIT=0,
		STAGE_DATE=1;
		
	public
		$template_db_key='form.timetable',
		$model_stages=
		[
			FieldSet_date::STAGE_DATE=>['day', 'month', 'year', 'hour', 'minute']
		],
		$model_stage=FieldSet_date::STAGE_NOW,
		$input_fields=['now'],
		$model=
		[
			'always'=>
			[
				'type'=>'bool',
				'template'=>'checkbox',
				'default_for_display'=>true
			],
			'set_start'=>
			[
				'type'=>'bool',
				'template'=>'checkbox',
				'default_for_display'=>false
			],
			'start'=>
			[
				'fieldset_type'=>'date'
			],
			'set_finish'=>
			[
				'type'=>'bool',
				'template'=>'checkbox',
				'default_for_display'=>false
			],
			'finish'=>
			[
				'fieldset_type'=>'date'
			],
			
			'dates'=>
			[
				'fieldset_type'=>'list',
				'entry_fieldset_type'=>'monthday',
				'min'=>0,
				'max'=>30,
				'prefix'=>'date'
			],
			
			'date_periods'=>
			[
				'fieldset_type'=>'list',
				'entry_fieldset_type'=>'monthday_period',
				'min'=>0,
				'max'=>30,
				'prefix'=>'date_period'
			],
			
			'weekdays'=>
			[
				'fieldset_type'=>'slugselect',
				'entry_type'=>'weekday',
				'min'=>0,
				'max'=>6,
				'prefix'=>'weekday',
				'id_group'=>'weekday'
			],
			
			'months'=>
			[
				'fieldset_type'=>'slugselect',
				'entry_type'=>'month',
				'min'=>0,
				'max'=>11,
				'prefix'=>'month',
				'id_group'=>'months'
			],
			
			'monthdays'=>
			[
				'fieldset_type'=>'slugselect',
				'entry_type'=>'month_day',
				'min'=>0,
				'max'=>30,
				'prefix'=>'monthday',
				'id_group'=>'monthday'
			],
			
			'years'=>
			[
				'fieldset_type'=>'slugselect',
				'entry_type'=>'year',
				'min'=>2014,
				'max'=>2020,
				'prefix'=>'year',
				'id_group'=>'years'
			],
			
			'time'=>
			[
				'fieldset_type'=>'list',
				'entry_fieldset_type'=>'daytime_period',
				'min'=>0,
				'max'=>10,
				'prefix'=>'time'
			]
		];
	
	public function update_subfields()
	{
		if ( ($this->model_stage===static::STAGE_NOW) && (!$this->content_of('now')) ) return $this->change_model_stage(static::STAGE_DATE);
		return false;
	}
	
	public function process_valid()
	{
		if ($this->content_of('now')) $result=time();
		else $result=mktime($this->content_of('hour'), $this->content_of('minute'), 0, $this->content_of('month'), $this->content_of('day'), $this->content_of('year'));
		$this->process_success=true;
		return $this->sign_report(new \Report_resolution($result));
	}
	
	public function set($content, $source_code=Value::BY_OPERATION)
	{
		$time=time();
		if ($content===static::NOW) $target_time=$time;
		elseif ( (is_array($content)) && ($content[0]===static::NOW_PLUS) ) $target_time=time()+$content[1];
		else $target_time=$content;
		
		$data=getdate($target_time);
		
		$set=['day'=>$data['mday'], 'month'=>$data['mon'], 'year'=>$data['year'], 'hour'=>$data['hours'], 'minute'=>$data['minutes'], 'now'=>$content===static::NOW];
		$this->set_by_array($set, $source_code);
	}
}
*/

class ValueType_month_day extends ValueType_natural_int
{
	const
		MAX=31;
}

class Validator_month_day extends Validator
{
	public function progress()
	{
		$master=$this->value->master;
		$day=$this->value->content;
		$month=$master->content_of($this->value_model_now('month_source'));
		$year=$master->content_of($this->value_model_now('year_source'));
		$valid=checkdate($month, $day, $year);
		if ($valid) $this->finish();
		else $this->impossible('bad_date');
	}
}

class ValueType_month extends ValueType_natural_int
{
	const
		MAX=12,
		
		FORMAT_RUS='rus',
		FORMAT_RUS_SHORT='rus_short',
		FORMAT_ENG='eng',
		FORMAT_ENG_SHORT='eng_short',
		FORMAT_NUM_ZEROFILL='num_zerofill',
		FORMAT_NUM='num',
		FORMAT_RAW='raw',
		FORMAT_DEFAULT='rus';
		
	static
		$convert=
		[
			self::FORMAT_RUS=>[1=>'Январь', 2=>'Февраль', 3=>'Март', 4=>'Апрель', 5=>'Май', 6=>'Июнь', 7=>'Июль', 8=>'Август', 9=>'Сентябрь', 10=>'Октябрь', 11=>'Ноябрь', 12=>'Декабрь'],
			self::FORMAT_RUS_SHORT=>[1=>'Янв', 2=>'Фев', 3=>'Март', 4=>'Апр', 5=>'Май', 6=>'Июнь', 7=>'Июль', 8=>'Авг', 9=>'Сен', 10=>'Окт', 11=>'Ноя', 12=>'Дек'],
			self::FORMAT_ENG=>[1=>'January', 2=>'February', 3=>'March', 4=>'April', 5=>'May', 6=>'June', 7=>'July', 8=>'August', 9=>'September', 10=>'October', 11=>'November', 12=>'December'],
			self::FORMAT_ENG_SHORT=>[1=>'Jan', 2=>'Feb', 3=>'Mar', 4=>'Apr', 5=>'May', 6=>'Jun', 7=>'Jul', 8=>'Aug', 9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Dec'],
			self::FORMAT_NUM_ZEROFILL=>[1=>'01', 2=>'02', 3=>'03', 4=>'04', 5=>'05', 6=>'06', 7=>'07', 8=>'08', 9=>'09', 10=>'10', 11=>'11', 12=>'12'],
			self::FORMAT_NUM=>[1=>1, 2=>2, 3=>3, 4=>4, 5=>5, 6=>6, 7=>7, 8=>8, 9=>9, 10=>10, 11=>11, 12=>12]
		];
		
	public function for_display($format=null, $line=[])
	{
		if ($format===null) $format=static::FORMAT_DEFAULT;
		if ($format===static::FORMAT_RAW) return $this->content();
		if (!array_key_exists($format, static::$convert)) $format=static::FORMAT_DEFAULT;
		return static::$convert[$format][$this->content()-1];
	}
	
	public function options($line=[])
	{
		//return static::$convert[ValueType_month::FORMAT_NUM_ZEROFILL];
		return static::$convert[static::FORMAT_RUS];
	}
}

class ValueType_weekday extends ValueType_natural_int
{
	const
		MAX=7,
		
		FORMAT_RUS='rus',
		FORMAT_RUS_SHORT='rus_short',
		FORMAT_ENG='eng',
		FORMAT_ENG_SHORT='eng_short',
		FORMAT_NUM='num',
		FORMAT_RAW='raw',
		FORMAT_DEFAULT='rus';
		
	static
		$convert=
		[
			self::FORMAT_RUS=>[1=>'Понедельник', 2=>'Вторник', 3=>'Среда', 4=>'Четверг', 5=>'Пятница', 6=>'Суббота', 7=>'Воскресенье'],
			self::FORMAT_RUS_SHORT=>[1=>'Пн', 2=>'Вт', 3=>'Ср', 4=>'Чт', 5=>'Пт', 6=>'Сб', 7=>'Вс'],
			self::FORMAT_ENG=>[1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 7=>'Sunday'],
			self::FORMAT_ENG_SHORT=>[1=>'Mon', 2=>'Tue', 3=>'Wed', 4=>'Thu', 5=>'Fri', 6=>'Sat', 7=>'Sun'],
			self::FORMAT_NUM=>[1=>1, 2=>2, 3=>3, 4=>4, 5=>5, 6=>6, 7=>7]
		];
		
	public function for_display($format=null, $line=[])
	{
		if ($format===null) $format=static::FORMAT_DEFAULT;
		if ($format===static::FORMAT_RAW) return $this->content();
		if (!array_key_exists($format, static::$convert)) $format=static::FORMAT_DEFAULT;
		return static::$convert[$format][$this->content()-1];
	}
	
	public function options($line=[])
	{
		//return static::$convert[ValueType_month::FORMAT_NUM_ZEROFILL];
		return static::$convert[static::FORMAT_RUS];
	}
}

class ValueType_year extends ValueType_natural_int
{
	const
		MIN=2000,
		MAX=2036;
}

class ValueType_hour extends ValueType_unsigned_int
{
	const
		MAX=24;
		
	public static function type_conversion($content)
	{
		$content=parent::type_conversion($content);
		if ($content instanceof \Report) return $content;
		if ($content==24) $content=0;
		return $content;
	}
}

class ValueType_minute extends ValueType_unsigned_int
{
	const
		MAX=59;
		
	public function options($line=[])
	{
		$options=parent::options();
		foreach ($options as $key=>&$val)
		{
			$val=str_pad($val, 2, '0', STR_PAD_LEFT);
		}
		return $options;
	}
}
?>