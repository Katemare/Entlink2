<?

class Value_array extends Value implements Value_provides_options, Pathway
{
	use Value_minmax;
	
	const
		MAX_OPTIONS=100,
		DIVIDER='/\s*,\s*/',
		FOR_INPUT_DIVIDER=', ?',
		FOR_INPUT_JOINER=', ',
		DEFAULT_SUBVALUE_FACTORY='Value',
		
		ALL_KEYWORD='_list',
		COUNT_KEYWORD='_count';
	
	public
		$min=null,
		$max=null;
	
	public function from_db($content)
	{
		return $this->from_input($content);
	}
	
	public function for_db()
	{
		return $this->for_input();
	}
	
	public function from_input($content)
	{
		if (is_string($content)) $result=preg_split(static::DIVIDER, $content);
		elseif (!is_array($content)) $result=[$content];
		else $result=$content;
		
		if ($this->in_value_model('unique_array')) $result=array_unique($result);
		return $result;
	}
	
	public function for_input()
	{
		$content=$this->content();
		if ($content instanceof Report) return $content;
		if (!is_array($content)) return $content;
		return implode(static::FOR_INPUT_JOINER, $this->content());
	}
		
	public function legal_value($content)
	{
		if (!is_array($content)) return $this->sign_report(new Report_impossible('not_array'));
		
		$good_content=[];
		foreach ($content as $key=>$data)
		{
			$result=$this->legal_element($data, $key);
			if ($result instanceof Report) continue;
			$good_content[$key]=$result;
		}
		
		if ( (($min=$this->min())!==null) && (count($good_content)<$min) ) return $this->sign_report(new Report_impossible('array_too_small'));
		if ( (($max=$this->max())!==null) && (count($good_content)>$max) ) $good_content=array_slice($good_content, 0, $max);
		if ( ($this->in_value_model('unique')) && ($this->value_model_now('unique')==true) ) $good_content=array_unique($good_content);
	
		return $good_content;
	}
	
	public function legal_element($data, $key)
	{
		if ( (is_string($data)) && ($this->in_value_model('element_ex')) && (!preg_match($this->value_model_now('element_ex'), $data)) )
		{
			// vdump($this->value_model_now('element_ex'));
			// vdump($data);
			return $this->sign_report(new Report_impossible('bad_element'));
		}
		return $data;
	}
	
	public function options($line=[])
	{
		if (!is_array($this->content)) return;
		if (count($this->content)>static::MAX_OPTIONS) return;
		return $this->content;
	}
	
	public function for_display($format=null, $line=[])
	{
		return $this->for_input();
	}
	
	public $subvalues=[];
	public function subvalue($code)
	{
		if (!array_key_exists($code, $this->subvalues))
		{
			$content=$this->content();
			if (!is_array($content)) $subvalue=false;
			elseif (!array_key_exists($code, $content)) $subvalue=false;
			else $subvalue=$this->create_subvalue($code);
			$this->subvalues[$code]=$subvalue;
		}
		if ($this->subvalues[$code]===false) return $this->sign_report(new Report_impossible('no_subvalue'));
		return $this->subvalues[$code];
	}
	
	public function create_subvalue($code)
	{
		$content=$this->content()[$code];
		$factory=$this->subvalue_factory($code, $content);
		return $factory::from_content($content, $this->last_source);
	}
	
	public function subvalue_factory($code, $content)
	{
		return static::DEFAULT_SUBVALUE_FACTORY;
	}
	
	public function reset()
	{
		$this->subvales=[];
		parent::reset();
	}
	
	public function ValueHost_request($code)
	{
		$content=$this->content();
		if ( (is_array($content)) && (array_key_exists($code, $content)) ) return $this->sign_report(new Report_resolution($content[$code]));
		return parent::ValueHost_request($code);
	}
	
	public function template_for_filled($code, $line=[])
	{
		if ($code===null) return parent::template_for_filled($code, $line);
		if ($code===static::ALL_KEYWORD) return $this->template_all($line);
		if ($code===static::COUNT_KEYWORD) return count($this->content);
		
		$subvalue=$this->subvalue($code);
		if ($subvalue instanceof Report_impossible) return parent::template_for_filled($code, $line);
		return $subvalue->template(null, $line);
	}
	
	public function template_all($line=[])
	{
		$template=Template_list_call::with_call([$this, 'populate_template_all'], $line);
		return $template;
	}
	
	public function populate_template_all($line=[])
	{
		if (!$this->has_state(static::STATE_FILLED)) return $this->sign_report(new Report_impossible('bad_all_call'));
		
		$list=[];
		foreach ($this->content() as $code=>$temp)
		{
			$subvalue=$this->subvalue($code);
			$list[]=$subvalue;
		}
		return $list;
	}
	
	public function follow_track($track)
	{
		return $this->subvalue($track);
	}
}

class Value_int_array extends Value_array
{	
	public function legal_element($data, $key)
	{
		return (int)$data;
	}
	
	public function for_display($format=null, $line=[])
	{
		return implode(', ', $this->content());
	}
}

class Value_keyword_array extends Value_array
{
	public function legal_element($data, $key)
	{
		if (preg_match('/[^'.Value_keyword::GOOD_SYMBOLS.']/u', $data)) return $this->sign_report(new Report_impossible('bad_keyword'));
		return $data;
	}
}

class Value_serialized_array extends Value_array
{
	public function from_db($content)
	{
		if ( ($content===null) && ($this->in_value_model('empty_is_null')) && ($this->value_model_now('empty_is_null')) ) return [];
		return unserialize($content);
	}
	
	public function for_db()
	{
		$content=$this->content;
		if ($content===null) return $content;
		if ( (count($content)==0) && ($this->in_value_model('empty_is_null')) && ($this->value_model_now('empty_is_null')) ) return null;
		return serialize($this->content());
	}
}

?>