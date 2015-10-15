<?
namespace Pokeliga\Data;

/*

Работа с данным-массивом может проходить так.

Из кода, у объекта-Value:
через content() - собственно массив.
через subvalues() - объект ArraySet, дающий доступ к элементам массива как объектам Value.
через RegSet() - объект RegisterSet с регистрами.
через value(<код>) или request(<код>) - публичные регистры (счёт), затем содержимое массива. коллизии быть не должно потому, что счёт имеет смысл только для массивов с ключами-числами, а у массивов со строковыми ключами просто не следует использовать код '_count' (лучше 'count'). если такая необходимость возникнет, тогда и будем думать.
через value_reg(<код>) или request_reg(<код>) - доступ к регистрам.

Из текста шаблона:
@arr_value.<код> - публичные регистры (счёт), затем содержимое массива. как с вызовами value() и request().
@arr_value._arr.<код> - содержимое массива.
@arr_value._reg.<код> - доступ к RegisterSet.
Можно обращаться по формату шаблона, чтобы отрегулировать отображение: {{arr_value.meow_date|format=Y}}.

Через интерфейс Pathway:
_reg - к RegisterSet
_arr - к ArraySet
остальное - к содержимому массива, не к регистрам. у регистров массива всё равно нет подзначений и всяких подзапросов.

FIXME: пока никак не регламентируются ключи открытых массивов.
*/

class ValueType_array extends ValueType implements Value_provides_options, Pathway, Value_has_registers
{
	use ValueType_minmax, Value_registers
	{
		Value_registers::reset_regs as std_reset_regs;
	}
	
	const
		MAX_OPTIONS=100,
		DIVIDER='/\s*,\s*/',
		IMPLODE_FOR_HUMAN_DIVIDER='/, ?/',
		IMPLODE_FOR_HUMAN_JOINER=', ',
		IMPLODE_JOINER=',',
		DEFAULT_SUBVALUE_FACTORY='Value',
		DEFAULT_SCALAR_REGISTER='serialized',
		
		ALL_KEYWORD='_list',
		COUNT_KEYWORD='_count',
		ARRAY_TRACK='_arr';
	
	public
		$reg_model=
		[
			'serialized'=>
			[
				'type'=>'string',
				'extract_call'=>'extract_reg_serialized'
			],
			'imploded'=>
			[
				'type'=>'string',
				'extract_call'=>'extract_reg_imploded'
			],
			'imloded_for_human'=>
			// для пользовательского ввода: при задании игнорирует пробелы между разделителями.
			[
				'type'=>'string',
				'extract_call'=>'extract_reg_imloded_for_human'
			],
			self::COUNT_KEYWORD=>
			[
				// этот регистр имеет смысл только в массивах с содержимым произвольного размера, то есть обычно - 
				'type'=>'unsigned_int',
				'extract_call'=>'extract_reg_count'
			]
		],
		$public_regs=[self::COUNT_KEYWORD],
		$elements_model=[],
		$generic_element_model='auto', // любой элемент подходит, смотреть по содержимому.
		$subvalues=null, // место под дополнительный объект RegisterSet.
		$min=null,
		$max=null;
	
	public function extract_reg_serialized()
	{
		return serialize($this->content);
	}
	public function extract_reg_imploded()
	{
		return implode(static::IMPLODE_JOINER, $this->content);
	}
	public function extract_reg_imloded_for_human()
	{
		return implode(static::IMPLODE_FOR_HUMAN_JOINER, $this->content);
	}
	
	public function extract_reg_count()
	{
		return count($this->content);
	}

	public function compose_from_regs($data)
	{
		if (array_key_exists('serialized', $data)) return unserialize($data['serialized']);
		elseif (array_key_exists('imploded', $data)) return mb_split('/'.preg_quote(static::IMPLODE_JOINER).'/', $data['imploded']);
		elseif (array_key_exists('human_imploded', $data)) return mb_split(static::IMPLODE_FOR_HUMAN_DIVIDER, $data['human_imloded']);
		return $this->sign_report(new \Report_impossible('bad_regs'));
	}
	
	public function reset_regs()
	{
		$this->std_reset_regs();
		if (!empty($this->subvalues)) unset($this->subvalues); // в отличие от обычных регистров, тут легче забыть объект, чем бороться с издержками динамической модели. цена в производительности невелика.
	}
	
	public static function type_conversion($content)
	{
		return (array)$content;
	}
	
	public function settings_based_conversion($content)
	{
		$good_content=[];
		foreach ($content as $key=>$data)
		{
			$result=$this->to_good_element($data, $key);
			if ($result instanceof \Report) continue;
			$good_content[$key]=$result;
		}
		
		if ( (($min=$this->min())!==null) && (count($good_content)<$min) ) return $this->sign_report(new \Report_impossible('array_too_small'));
		if ( (($max=$this->max())!==null) && (count($good_content)>$max) ) $good_content=array_slice($good_content, 0, $max);
		if ( ($this->in_value_model('unique_array')) && ($this->value_model_now('unique_aray')===true) ) $good_content=array_unique($good_content);
	
		return $good_content;
	}
	
	public function subvalues()
	{
		if ($this->subvalues===null) $this->subvalues=$this->create_subvalues();
		return $this->subvalues;
	}
	
	public function create_subvalues()
	{
		$arrset=ArraySet::for_value($this, $this->elements_model);
		$arrset->generic_model=$this->generic_element_model;
		return $arrset;
	}
	
	public function anything_goes()
	{
		return empty($this->elements_model) and $this->generic_element_model==='auto';
	}
	
	public function to_good_element($data, $key)
	{		
		if ($this->anything_goes()) return $data;
		
		$subvalues=$this->subvalues();
		$test_value=$subvalues->create_value($key);
		if ($test_value instanceof \Report_impossible) return $test_value;
		$test_value->set($data, $this->last_source);
		if ($test_value->has_state($test_value::STATE_FAILED)) return $this->sign_report(new \Report_impossible('bad_element'));

		return $test_value->content;
	}
	
	public function options($line=[])
	{
		if (count($this->content)>static::MAX_OPTIONS) return;
		return $this->content;
	}
	
	public function subvalue($code)
	{
		return $this->subvalues()->produce_value($code);
	}
	
	public function is_reg_public($register)
	{
		if (!array_key_exists($register, $this->reg_model)) return false;
		return $this->public_regs===true or in_array($register, $this->public_regs, true);
	}
	
	public function request($code)
	{
		if ($this->is_reg_public($code)) return $this->request_reg($code);
		return $this->subvalues()->request($code);
	}
	
	public function value($code)
	{
		if ($this->is_reg_public($code)) return $this->value_reg($code);
		return $this->subvalues()->value($code);
	}
	
	public function for_display($format=null, $line=[])
	{
		return $this->value_reg('imloded_for_human');
	}
	
	public function template_for_filled($code, $line=[])
	{
		if ($code===null) return parent::template_for_filled($code, $line);
		if ($code===static::ALL_KEYWORD) return $this->template_all($line);
		
		$subvalue=$this->subvalue($code);
		if ($subvalue instanceof \Report_impossible) return parent::template_for_filled($code, $line);
		return $subvalue->template(null, $line);
	}
	
	public function template_all($line=[])
	{
		$template=Template_list_call::with_call([$this, 'populate_template_all'], $line);
		return $template;
	}
	
	public function populate_template_all($line=[])
	{
		if (!$this->has_state(static::STATE_FILLED)) return $this->sign_report(new \Report_impossible('bad_all_call'));
		
		$list=[];
		foreach ($this->content() as $code=>$temp)
		{
			$subvalue=$this->subvalue($code);
			$list[]=$subvalue;
		}
		return $list;
	}
	
	public function follow_track($track, $line=[])
	{
		if ($track===static::ARRAY_TRACK) return $this->subvalues();
		if ($track===static::REGSET_TRACK) return $this->RegSet();
		return $this->subvalues()->follow_track($track, $line);
	}
}

// используется для проверки действительности значений ValueType_array и их демонстрации.
class ArraySet extends RegisterSet
{
	public function fill_value($value)
	{
		$master_content=$this->master->request();
		if (!($master_content instanceof \Report_success))
		{
			parent::fill_value($value);
			return;
		}
		
		$master_content=$master_content->resolution();
		if (array_key_exists($value->code, $master_content)) $value->set($master_content[$value->code], $this->master->last_source);
		else $value->set_failed(new \Report_unknown_code($value->code, $this));
	}
}

// несколько популярных конфигураций, к тому же хранимых по умолчанию иным образом, чтобы не писать везде одни и те же строчки модели.
class ValueType_int_array extends ValueType_array
{
	const
		DEFAULT_SCALAR_REGISTER='imploded';
		
	public
		$generic_element_model='int';
}

class ValueType_keyword_array extends ValueType_array
{
	const
		DEFAULT_SCALAR_REGISTER='imploded';
		
	public
		$generic_element_model='keyword';
}

class ValueType_title_array extends ValueType_array
{
	const
		DEFAULT_SCALAR_REGISTER='imploded';
		
	public
		$generic_element_model='title';
}

?>