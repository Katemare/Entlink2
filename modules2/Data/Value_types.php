<?

trait Value_minmax
{
	public function min()
	{
		if ($this->in_value_model('min')) return $this->value_model_now('min');
		return $this->min;
	}
	
	public function max()
	{
		if ($this->in_value_model('max')) return $this->value_model_now('max');
		return $this->max;
	}
}

class Value_string extends Value
{
	use Value_minmax;
	
	const
		FORMAT_HTML=1,
		FORMAT_HTML_SAFE=2,
		FORMAT_DEFAULT=Value_string::FORMAT_HTML_SAFE;
	
	static
		$string_convert=
		[
			'html'=>Value_string::FORMAT_HTML,
			'html_safe'=>Value_string::FORMAT_HTML_SAFE,
			'default'=>Value_string::FORMAT_DEFAULT
		];

	public
		$min=null,
		$max=null;
		
	public function legal_value($content)
	{
		$content=(string)$content;
		$content=$this->filter_out_bad_characters($content);
		if ($content instanceof Report) return $content;
		if ( (($min=$this->min())!==null) && (mb_strlen($content)<$min) ) return $this->sign_report(new Report_impossible('illegal_content'));
		if ( (($max=$this->max())!==null) && (mb_strlen($content)>$max) ) $content=substr($content, 0, $max);
		return $content;
	}
	
	public function filter_out_bad_characters($content)
	{
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

class Value_html extends Value_string
{	
	const
		FORMAT_DEFAULT=Value_string::FORMAT_HTML;
}

class Value_text extends Value_string
{
	const
		BAD_TEXT_SYMBOLS='\x00-\x08\x0B\x0C\x0E-\x1F';
	
	public function filter_out_bad_characters($content)
	{
		return preg_replace('/['.static::BAD_TEXT_SYMBOLS.']/u', '', $content);
	}
	
	public function for_display($format=null, $line=[])
	{
		$text=parent::for_display($format, $line);
		if ($format===null) $format=static::FORMAT_DEFAULT;
		if ($format===static::FORMAT_HTML_SAFE) $text=nl2br($text);
		return $text;
	}
}

class Value_title extends Value_text
{
	public
		$min=1,
		$max=200;
	
	public function filter_out_bad_characters($content)
	{
		return preg_replace('/['.static::BAD_TEXT_SYMBOLS.'\v\n\r]/u', '', $content);
	}
}

class Value_keyword extends Value_text
{
	const
		GOOD_SYMBOLS='a-zA-Z\d_%'; // % оставлен для конструкций типа %prefix%, которые предлагается заменять.
		
	public
		$min=1,
		$max=50;

	public function filter_out_bad_characters($content)
	{
		if (preg_match('/[^'.static::GOOD_SYMBOLS.']/u', $content)) return $this->sign_report(new Report_impossible('bad_keyword'));
		return $content;
	}
}

class Value_bool extends Value implements Value_provides_options
{
	const
		FORMAT_NUMERIC=1,
		FORMAT_KEYWORD=2,
		FORMAT_SYMBOL=3,
		FORMAT_RUS=4,
		FORMAT_DEFAULT=Value_bool::FORMAT_SYMBOL;
	
	static
		$string_convert=
		[
			'numeric'=>Value_bool::FORMAT_NUMERIC,
			'keyword'=>Value_bool::FORMAT_KEYWORD,
			'symbol'=>Value_bool::FORMAT_SYMBOL,
			'rus'=>Value_bool::FORMAT_RUS,
			'default'=>Value_bool::FORMAT_DEFAULT
		],
		
		$display_values=
		[
			Value_bool::FORMAT_NUMERIC	=>[1, 0],
			Value_bool::FORMAT_KEYWORD		=>['true', 'false'],
			Value_bool::FORMAT_SYMBOL	=>['&#10003;', '&#10008;'],
			Value_bool::FORMAT_RUS		=>['да', 'нет']
		];

	public function legal_value($content)
	{
		return (bool)$content;
	}
	
	public function from_db($content)
	{
		return $content>0;
	}
	
	public function for_db()
	{
		if ($this->content) return 1;
		return 0;
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
		return static::$display_values[Value_bool::FORMAT_SYMBOL];
	}
}

class Value_number extends Value
{
	use Value_minmax;

	public
		$min=null,
		$max=null;
	
	public function legal_value($content)
	{
		$content=$this->type_convert($content);
		if ( (($min=$this->min())!==null) && ($content<$min) ) return $min;
		if ( (($max=$this->max())!==null) && ($content>$max) ) return $max;
		return $content;
	}
	
	public function type_convert($content)
	{
		return (float)$content;
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

class Value_int extends Value_number implements Value_provides_options
{
	const
		MAX_OPTIONS=100;

	public function type_convert($content)
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

class Value_unsigned_int extends Value_int
{
	public
		$min=0;
}

class Value_percent extends Value_unsigned_int
{
	public
		$max=100;
}

class Value_unsigned_number extends Value_number
{
	public
		$min=0;
}

class Value_enum extends Value implements Value_provides_titled_options
{
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
		
	public function legal_value($content)
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

class Value_url extends Value_string
{
	const URL_EX='/https?:\/\/[\w-]+(\.[\w-]+)+(\/\S*)?/i'; // STUB

	public function legal_value($content)
	{
		$content=parent::legal_value($content);
		if ($content instanceof Report) return $content;
		if (!preg_match(static::URL_EX, $content)) return $this->sign_report(new Report_impossible('bad_url'));
		return $content;
	}
}

?>