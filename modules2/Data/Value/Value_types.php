<?
namespace Pokeliga\Data;

trait ValueType_minmax
{
	public function min()
	{
		$type_min=null;
		$min=null;
		if (defined(get_class($this).'::MIN')) $type_min=static::MIN;
		if ($this->in_value_model('min')) $min=$this->value_model_now('min');
		if ($min===null) return $type_min;
		if ($type_min===null) return $min;
		if ($min<$type_min) return $type_min;
		return $min;
	}
	
	public function max()
	{
		$type_max=null;
		$max=null;
		if (defined(get_class($this).'::MAX')) $type_max=static::MAX;
		if ($this->in_value_model('max')) $max=$this->value_model_now('max');
		if ($max===null) return $type_max;
		if ($type_max===null) return $max;
		if ($max>$type_max) return $type_max;
		return $max;
	}
}

class ValueType_null extends ValueType
{
	const
		FORMAT_RAW		='raw',
		FORMAT_CAPITAL	='caps',
		FORMAT_LOWERCASE='lc',
		FORMAT_RUS		='rus',
		FORMAT_SYMBOL	='symbol',
		FORMAT_DEFAULT=self::FORMAT_SYMBOL;
	
	static
		$convert=
		[
			self::FORMAT_RAW		=>'',
			self::FORMAT_CAPITAL	=>'NULL',
			self::FORMAT_LOWERCASE	=>'null',
			self::FORMAT_RUS		=>'нет',
			self::FORMAT_SYMBOL		=>'-'
		];
	
	public static function type_conversion($content)
	{
		return null;
	}
	
	public function for_display($format=null, $line=[])
	{
		if ($format===null or !array_key_exists($format, static::$convert) ) $format=static::FORMAT_DEFAULT;
		return static::$convert[$format];
	}
}

class ValueType_string extends ValueType
{
	use ValueType_minmax;
	
	const
		BAD_CHARACTERS=null,
		GOOD_CHARACTERS=null,
		MIN=null,
		MAX=null,
		DEFAULT_FIELD_TEMLATE='input_line',
		
		FORMAT_HTML=1,							// показывать как строку, которая может содержать html.
		FORMAT_HTML_SAFE=2,						// показывать с экранированием тегов html.
		FORMAT_DEFAULT=self::FORMAT_HTML_SAFE;	// по умолчанию экранировать теги html.
	
	static
		$string_convert=
		[
			'html'		=>self::FORMAT_HTML,
			'html_safe'	=>self::FORMAT_HTML_SAFE,
			'default'	=>self::FORMAT_DEFAULT
		];
		
	public static function type_conversion($content)
	{
		$content=(string)$content;
		$content=static::filter_out_impossible_characters($content);
		return $content;
	}
	
	public function settings_based_conversion($content)
	{
		if ( (($min=$this->min())!==null) && (mb_strlen($content)<$min) ) return new \Report_impossible('illegal_content', $this);
		if ( (($max=$this->max())!==null) && (mb_strlen($content)>$max) ) $content=substr($content, 0, $max);
		return $content;
	}
	
	public static function filter_out_impossible_characters($content)
	{
		if (static::GOOD_CHARACTERS!==null) return preg_replace('/[^'.static::GOOD_CHARACTERS.']/u', '', $content);
		if (static::BAD_CHARACTERS!==null) return preg_replace('/['.static::BAD_CHARACTERS.']/u', '', $content);
		return $content;
	}
	
	public function html_allowed()
	{
		return $this->in_value_model('html') && $this->value_model_now('html');
	}
	
	public function for_display($format=null, $line=[])
	{
		if ($format===null) $format=static::FORMAT_DEFAULT;
		elseif (array_key_exists($format, static::$string_convert)) $format=static::$string_convert[$format];
		elseif (!in_array($format, static::$string_convert)) $format=static::FORMAT_DEFAULT;
		
		if ($format===static::FORMAT_HTML) $result=$this->content();
		elseif ($format===static::FORMAT_HTML_SAFE) $result=htmlspecialchars($this->content());
		else die ('UNKNOWN FORMAT');
		
		if (array_key_exists('case', $line))
		{
			if ($line['case']==='lower') $result=mb_strtolower($result);
			elseif ($line['case']==='upper') die('UNIMPLEMENTED YET: uppercase string');
			elseif ($line['case']==='lower_first') $result=mb_lcfirst($result);
			elseif ($line['case']==='upper_first') $result=mb_ucfirst($result);
		}
		
		return $result;
	}
}

class ValueType_html extends ValueType_string
{	
	const
		FORMAT_DEFAULT=self::FORMAT_HTML;	// по умолчанию не экранировать теги html.
}

class ValueType_text extends ValueType_string
{
	const
		DEFAULT_FIELD_TEMLATE='textarea',
		BAD_CHARACTERS='\x00-\x08\x0B\x0C\x0E-\x1F';
	
	public function for_display($format=null, $line=[])
	{
		$text=parent::for_display($format, $line);
		if ($format===null) $format=static::FORMAT_DEFAULT;
		if ($format===static::FORMAT_HTML_SAFE) $text=nl2br($text);	// если теги html экранируются, то видимых в html переносов не будет, их нужно добавить.
		return $text;
	}
}

class ValueType_title extends ValueType_text
{
	const
		BAD_CHARACTERS=ValueType_text::BAD_CHARACTERS.'\v\r\n',
		DEFAULT_FIELD_TEMLATE='input',
		MIN=1,
		MAX=200;
}

class ValueType_keyword extends ValueType_title
{
	const
		GOOD_CHARACTERS='a-zA-Z\d_%', // % оставлен для конструкций типа %prefix%, которые предлагается заменять.
		MAX=50;
}

class ValueType_bool extends ValueType implements Value_provides_options
{	
	const
		DEFAULT_FIELD_TEMLATE='checkbox',
		FORMAT_NUMERIC	=1,	// 1 или 0
		FORMAT_KEYWORD	=2,	// 'true' или 'false'
		FORMAT_SYMBOL	=3,	// галочка или крестик
		FORMAT_RUS		=4,	// 'да' или 'нет'
		FORMAT_DEFAULT=ValueType_bool::FORMAT_SYMBOL;
	
	static
		$string_convert=
		[
			'numeric'	=>ValueType_bool::FORMAT_NUMERIC,
			'keyword'	=>ValueType_bool::FORMAT_KEYWORD,
			'symbol'	=>ValueType_bool::FORMAT_SYMBOL,
			'rus'		=>ValueType_bool::FORMAT_RUS,
			'default'	=>ValueType_bool::FORMAT_DEFAULT
		],
		
		$display_values=
		[
			ValueType_bool::FORMAT_NUMERIC	=>[1, 0],
			ValueType_bool::FORMAT_KEYWORD	=>['true', 'false'],
			ValueType_bool::FORMAT_SYMBOL	=>['&#10003;', '&#10008;'],
			ValueType_bool::FORMAT_RUS		=>['да', 'нет']
		];
		
	public static function type_conversion($content)
	{
		return (bool)$content;
	}
	
	public function for_display($format=null, $line=[])
	{
		if ($format===null) $format=static::FORMAT_DEFAULT;
		elseif ( (is_string($format)) && (array_key_exists($format, static::$string_convert)) ) $format=static::$string_convert[$format];
		elseif (!array_key_exists($format, static::$display_values)) $format=static::FORMAT_DEFAULT; // ERR: нет обработки ошибки
		
		if ($this->content()) return static::$display_values[$format][0];
		else return static::$display_values[$format][1];
	}
	
	public function options($line=[])
	{
		return static::$display_values[ValueType_bool::FORMAT_SYMBOL];
	}
}

class ValueType_number extends ValueType
{
	use ValueType_minmax;
	
	const
		DEFAULT_FIELD_TEMLATE='input_number',
		MIN=null,
		MAX=null;
	
	public static function type_conversion($content)
	{
		$content=str_replace(',', '.', $content);
		return (float)$content;
	}
	
	public function settings_based_conversion($content)
	{
		if ( (($min=$this->min())!==null) && ($content<$min) ) return $min;
		if ( (($max=$this->max())!==null) && ($content>$max) ) return $max;
		return $content;
	}
	
	public function for_display($format=null, $line=[])
	{
		if ( (array_key_exists('digits', $line)) || (array_key_exists('fraction_digits', $line)) )
		{
			$content=$this->content();
			$parts=explode('.', $content);
			$before=abs(reset($parts));
			$after=next($parts);
			$result='';
			if ($content<0) $result.='-';
			
			if (array_key_exists('digits', $line)) $result.=str_pad($before, $line['digits'], '0', STR_PAD_LEFT);
			else $result.=$before;
			
			if (array_key_exists('fraction_digits', $line)) $result.='.'.str_pad($after, $line['fraction_digits'], '0', STR_PAD_RIGHT);
			elseif ( ($after!==false) && ($after>0) ) $result.='.'.$after;
			
			return $result;
		}
		return parent::for_display($format, $line);
	}
}

class ValueType_int extends ValueType_number implements Value_provides_options
{
	const
		MAX_OPTIONS=100;

	public static function type_conversion($content)
	{
		return (int)$content;
	}
	
	public function options($line=[])
	{
		if ( $this->max()-$this->min() > static::MAX_OPTIONS ) return;
		$range=range($this->min(), $this->max());
		return array_combine($range, $range);
	}
	
	public function for_display($format=null, $line=[])
	{
		$result=parent::for_display($format, $line);
		$result.=static::units($this->content(), $line);
		return $result;
	}
	
	public static function units($number, $units)
	{
		if ( (array_key_exists('many', $units)) && ($number>=11) && ($number<=14) ) $result='&nbsp;'.$units['many'];
		elseif ( (array_key_exists('singular', $units)) && ($number % 10 === 1) ) $result='&nbsp;'.$units['singular'];
		elseif ( (array_key_exists('few', $units)) && ( ($digit=$number % 10)>=2) && ($digit<=4) ) $result='&nbsp;'.$units['few'];
		elseif (array_key_exists('many', $units)) $result='&nbsp;'.$units['many'];
		else $result='';
		return $result;
	}
}

class ValueType_unsigned_int extends ValueType_int
{
	const
		MIN=0;
}

class ValueType_natural_int extends ValueType_int
{
	const
		MIN=1;
}

class ValueType_percent extends ValueType_unsigned_int
{
	const
		MAX=100;
}

class ValueType_unsigned_number extends ValueType_number
{
	const
		MIN=0;
}

// содержит пункт из заданного списка.
class ValueType_enum extends ValueType implements Value_provides_titled_options
{
	const
		DEFAULT_FIELD_TEMLATE='select';
		
	public
		$options=null,
		$select_options=null,
		$titles=null;
	
	public function possible_content()
	{
		// не включает null, даже если это установлено в модели, потому что с этим значением разбираются другие части класса.
		if ($this->in_value_model('options')) $options=$this->value_model_now('options');
		elseif ($this->options!==null) $options=$this->options;
		elseif ($this->in_value_model('select_options')) $options=array_keys($this->value_model_now('select_options'));
		else { vdump($this); die ('NO ENUM OPTIONS'); }
		
		if ($this->in_value_model('exclude_options')) $options=array_diff($options, $this->value_model_now('exclude_options'));
		return $options;
	}
	
	public function select_values()
	{
		if ($this->in_value_model('select_options')) return array_keys($this->value_model_now('select_options'));
		if ($this->in_value_model('select_values')) return $this->value_model_now('select_values');
		
		$list=$this->possible_content();
		if ($this->in_value_model('null')) array_unshift($list, null);
		
		foreach ($list as &$value)
		{
			if ( (is_int($value)) || (is_string($value)) ) continue;
			{
				$options[$value]=$value;
				continue;
			}
			if (!$this->in_value_model('replace')) die ('BAD OPTIONS 2');
			if ( ($key=array_search($value, $this->value_model_now('replace')))!==false) die ('BAD OPTIONS 3');
			$value=$key;
		}
		
		return $list;
	}
	
	public function options($line=[])
	{
		if ($this->select_options===null)
		{
			if ($this->in_value_model('select_options')) $this->options=$this->value_model_now('select_options');
			else
			{
				$select_values=$this->select_values();
				$options=[];
				foreach ($select_values as $content)
				{
					if ( (is_int($content)) || (is_string($content)) )
					{
						$options[$content]=$content;
						continue;
					}
					if (!$this->in_value_model('replace')) die ('BAD OPTIONS 4');
					if ( ($key=array_search($content, $this->value_model_now('replace')))!==false) continue;
					$options[$key]=$content;
				}
				$this->select_options=$options;
			}
		}
		return $this->select_options;
	}
	
	public function titled_options($line=[])
	{
		$titles=[];
		$this->options(); // может быть, сгенерирует названия.
		if ($this->titles!==null) $titles=$this->titles+$titles;
		if ($this->in_value_model('titles')) $titles=$this->value_model_now('titles')+$titles;
		if (empty($titles)) return $this->options($line);
		
		$values=$this->select_values();
		$options=[];
		foreach ($values as $value)
		{
			if (array_key_exists($value, $titles)) $options[$value]=$titles[$value];
			else $options[$value]=$value;
		}
		return $options;
	}
		
	public function settings_based_conversion($content)
	{
		$options=$this->possible_content();
		$key=array_search($content, $options);
		if ($key!==false) return $options[$key];
		return reset($options);
	}

	public function for_display($format=null, $line=[])
	{
		$content=$this->content();
		if ($format==='raw') return $content;
		if ( (!empty($this->titles)) && (array_key_exists($content, $this->titles)) ) return $this->titles[$content];
		if ( ($this->in_value_model('titles')) && (array_key_exists($content, $titles=$this->value_model_now('titles'))) ) return $titles[$content];
		return $content;
	}
}

class ValueType_url extends ValueType_string
{
	const URL_EX='/https?:\/\/[\w-]+(\.[\w-]+)+(\/\S*)?/i';

	public static function type_conversion($content)
	{
		$content=parent::type_conversion($content);
		if ($content instanceof \Report) return $content;
		if (!preg_match(static::URL_EX, $content)) return new \Report_impossible('bad_url');
		return $content;
	}
}

class ValueType_object extends ValueType
{
	public static function type_conversion($content)
	{
		if (!is_object($content)) return new \Report_impossible('not_object');
	}
	
	public function for_display($format=null, $line=[])
	{
		return get_class($this->content()); // STUB
	}
}

class ValueType_auto extends ValueType
{
	public static function type_conversion($content)
	{
		return new \Report_impossible(); // заставляет использовать заменный тип.
	}
}

?>