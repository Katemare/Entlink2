<?

interface Template_field_on_failure
{
	public static function content_on_failure();
}

interface Template_field_process_content
{
	public static function process_content($content);
}

interface Template_field_invisible // полям с этим классом не нужно обеспечивать средства, необходимые для отображения.
{
}

interface Template_field_variant_class
{
	public function resolve_class(&$final=true); // подбирает другой класс шаблона в зависимости аргументов.
}

class Template_field extends Template_from_db implements ValueModel, ValueLink
{
	use Prototyper, ValueModel_from_link;

	static
		$prototype_class_base='Template_field_';
	
	public
		$field=null,
		$code=null,
		$value=null,
		$field_elements=['name', 'value', 'html_id'];
	
	public static function for_fieldset($type_keyword, $field, $code, $line=[])
	{
		$template=static::from_prototype($type_keyword);
		$template->field=$field;
		$template->code=$code;
		$template->value=$field->produce_value($code);
		$template->line=$line;
		
		$template=static::finalize_template($template);
		
		return $template;
	}
	
	public static function for_value($type_keyword, $value, $line=[])
	{
		$template=static::from_prototype($type_keyword);
		$template->value=$value;
		$template->line=$line;
		
		$template=static::finalize_template($template);
		
		return $template;
	}
	
	public static function copy_arguments($template_field)
	{
		$template=new static();
		$template->line=$template_field->line;
		$template->value=$template_field->value;
		$template->code=$template_field->code;
		$template->field=$template_field->field;
		return $template;
	}
	
	public static function finalize_template($template)
	{
		if (!($template instanceof Template_field_variant_class)) return $template;
		
		$final=null;
		while ($final!==true)
		{
			if ($final===null) $final=true;
			$template=$template->resolve_class($final);
		}
		return $template;
	}
	
	public function get_value()
	{
		return $this->value;
	}
	
	public function recognize_element($code, $line=[])
	{
		if (parent::recognize_element($code)) return true;
		return in_array($code, $this->field_elements);
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='name')
		{
			if (empty($this->field)) return '';
			return $this->field->name($this->code);
		}
		if ($code==='value')
		{
			// STUB: вероятно, защита от опасных символов должна выполняться иначе.
			if (array_key_exists('value', $this->line)) return htmlspecialchars($this->line['value']);
			$content=$this->value->for_input();
			if (is_array($content)) { vdump($content); vdump($this); die('BAD ARRAY'); }
			if ($content instanceof Report_impossible) return '';
			if ( (empty($content)) && ($this->in_value_model('empty_to_display')) ) return $this->value_model_now('empty_to_display');
			else return htmlspecialchars((string)$content);
		}
		if ($code==='html_id')
		{
			if (array_key_exists('html_id', $this->line)) return $this->line['html_id'];
			return '';
		}
	}
	
	public function form()
	{
		if ($this->field instanceof Form) return $this->field;
		if (!empty($this->field->form)) return $this->field->form;
		return false;
	}
	
	// значение вводимого поля, а не значения, касающиеся шаблона.
	public function field_value()
	{
		return $this->field->produce_value($this->code);
	}
	
	public function finish($success=true)
	{
		if ( ($success) && (!empty($this->value)) )
		{
			// нужно в одном месте: для использования простых полей в FieldSet_list, пока другого способа не придумано.
			if ($this->in_value_model('prepend')) $this->resolution=$this->value_model_now('prepend').$this->resolution;
			if ($this->in_value_model('append')) $this->resolution.=$this->value_model_now('append');
		}
		parent::finish($success);
	}
}

class Template_field_input extends Template_field
{
	public
		$db_key='form.input',
		$type='text',
		$elements=['type'];
	
	public function make_template($code, $line=[])
	{
		if ($code==='type') return $this->type;
		return parent::make_template($code, $line);
	}
}

class_alias('Template_field_input', 'Template_field_string');

class Template_field_input_line extends Template_field_input
{
	public
		$db_key='form.input_line';
}

class Template_field_input_number extends Template_field_input
{
	const
		DEFAULT_DIGITS=5,
		MIN_DIGITS=3;
	
	public
		$db_key='form.input_number',
		$elements=['type', 'size'];
		
	public function make_template($code, $line=[])
	{
		if ($code==='size')
		{
			$max=$this->field_value()->max();
			if ($max===null) return static::DEFAULT_DIGITS;
			return max(strlen($max), static::MIN_DIGITS);
		}
		return parent::make_template($code, $line=[]);
	}
}

class Template_field_hidden extends Template_field_input implements Template_field_invisible
{
	public
		$type='hidden';
}

class Template_field_checkbox extends Template_field_input implements Template_field_on_failure
{
	public
		$type='checkbox',
		$db_key='form.checkbox',
		$checkbox_elements=['selected', 'value', 'title'];
	
	public function recognize_element($code, $line=[])
	{
		if (parent::recognize_element($code)) return true;
		return in_array($code, $this->checkbox_elements);
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='selected')
		{
			if ($this->field->content_of($this->code)) return ' checked';
			else return '';
		}
		elseif ($code==='value') return 1;
		elseif ($code==='title')
		{
			if (empty($this->line['title'])) return '';
			else return htmlspecialchars(mb_ucfirst($this->line['title']));
		}
		return parent::make_template($code, $line);
	}
	
	public static function content_on_failure()
	{
		return false;
	}
}

class Template_field_textarea extends Template_field
{
	public
		$db_key='form.textarea',
		$rows=5,
		$cols=50,
		$elements=['rows', 'cols'];
	
	public function make_template($code, $line=[])
	{
		if ( ($element=parent::make_template($code, $line)) !== null) return $element;
		if ($this->in_value_model($code)) return $this->value_model_now($code);
		if ($code==='rows') return $this->rows;
		if ($code==='cols') return $this->cols;
	}
}

class Template_field_button extends Template_field_input
{
	public
		$db_key='form.button',
		$type='button',
		$elements=['type', 'title'];
	
	public function make_template($code, $line=[])
	{
		if ($code==='title')
		{
			if (array_key_exists('title', $this->line)) return $this->line['title'];
			elseif ($this->in_value_model('title')) return $this->value_model_now('title');
			return $this->make_template('value', $line);
		}
		if ($code==='html_id')
		{
			$result=parent::make_template($code, $line);
			if ($result==='')
			{
				$form=$this->form();
				if (empty($form)) return '';
				$add=$this->make_template('value'); // STUB! не сработает, если в качестве такового шаблона возвращается объект, а не строка.
				if (empty($add)) $add=$this->code;
				return $form->html_id().'_'.$add; 
			}
			else return $result;
		}
		return parent::make_template($code, $line);
	}
}

class Template_field_submit extends Template_field_button
{
	public
		$type='submit';
}

class Template_field_radio extends Template_field_input implements Template_field_variant_class
{
	public
		$type='radio',
		$db_key='form.checkbox', // подходит.
		$radio_elements=['title', 'selected'];
	public function resolve_class(&$final=true)
	{
		if (array_key_exists('value', $this->line)) return $this;
		return Template_field_radios::copy_arguments($this);
	}
	
	public function recognize_element($code, $line=[])
	{
		if (parent::recognize_element($code)) return true;
		return in_array($code, $this->radio_elements);
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='selected')
		{
			$checked=false;
			if (!empty($this->line['selected'])) $checked=true;
			elseif
			(
				(array_key_exists('value', $this->line)) &&
				(!(($content=$this->value->content()) instanceof Report)) &&
				($this->value->content()==$this->line['value'])
			)
				$checked=true;
			if ($checked) return ' checked';
			else return '';
		}
		elseif ($code==='title')
		{
			if (empty($this->line['title'])) return '';
			else return htmlspecialchars(mb_ucfirst($this->line['title']));
		}
		return parent::make_template($code, $line);
	}
}
?>