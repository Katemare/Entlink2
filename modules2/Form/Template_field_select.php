<?
namespace Pokeliga\Form;

/*
#################################
### Обычный выпадающий список ###
#################################
*/

class Template_field_select extends Template_field implements Template_field_variant_class, Template_field_on_failure
{
	const
		STEP_REQUEST_OPTIONS=-2,
		STEP_APPEND_COMMON_OPTIONS=-1,
		STEP_INIT=-2,
		
		MODE_CUSTOM=1,	// список уникальный, кэшировать нечего. не ожидается даже повторение FieldSet'а в рамках страницы.
		MODE_COMMON=2;	// список в основном состоит из элементов, которые могут быть общие у нескольких списков, так что они кэшируются в конце страницы.
	
	static
		$next_select_id=0,
		$key_by_mode=
		[
			Template_field_select::MODE_CUSTOM=>'form.select',
			Template_field_select::MODE_COMMON=>'form.select_optimized'
		],
		$options_by_group=[];
	
	public
		$elements=['options', 'empty_option', 'selected_options', 'select_id', 'options_group', 'selected_value', 'count'],
		$select_id,
		$options=null,
		$mode,
		$option_class='Template_field_option';
	
	public function resolve_class(&$final=true)
	{
		if (duck_instanceof($this->value, '\Pokeliga\Data\Value_searchable_options')) return Template_field_select_searchable::copy_arguments($this);
		return $this;
	}
	
	public function initiated()
	{
		parent::initiated();
		$this->select_id=static::$next_select_id++;
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_INIT) $this->prepare();
	
		if ($this->step===static::STEP_REQUEST_OPTIONS)
		{
			if ($this->options_posted())
			{
				$main_select=static::$options_by_group[$this->options_group()];
				if ($main_select->completed()) return $this->advance_step();
				return $this->sign_report(new \Report_task($main_select));
			}
			else $this->reserve_options();
			$options=$this->options(false);
			if ($options instanceof \Report) return $options;
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_REQUEST_OPTIONS+1)
		{
			if ($this->options_posted())
			{
				$main_select=static::$options_by_group[$this->options_group()];
				$this->options=$main_select->options();
			}
			else $this->options(); // запоминает полученные опции.
		}
		
		if ($this->step===static::STEP_APPEND_COMMON_OPTIONS)
		{
			if ($this->mode!==static::MODE_COMMON) return $this->advance_step();
			if ($this->options_posted()) return $this->advance_step();
			
			$template=Template_field_select_common_options::copy_arguments($this);
			$template->master_select=$this;
			$this->page->master_template->to_append($template);
			return $this->advance_step();
		}
		return parent::run_step();
	}
	
	public function prepare()
	{
		if (!empty($this->page))
		{
			$this->page->register_requirement('js', Router()->module_url('Form', 'select.js'), Page::PRIORITY_CONSTRUCT_LAYOUT);
			$this->page->register_requirement('css', Router()->module_url('Form', 'select.css'), Page::PRIORITY_CONSTRUCT_LAYOUT);
		}
		$this->auto_mode();
	}
	
	public function auto_mode()
	{
		if ($this->mode!==null) return;
		if (empty($this->page->master_template->appendable)) $this->mode=static::MODE_CUSTOM;
		elseif ($this->has_custom_options()) $this->mode=static::MODE_CUSTOM;
		else $this->mode=static::MODE_COMMON;
	}
	
	public function get_db_key($now=true)
	{
		return static::$key_by_mode[$this->mode];
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='options')
		{
			$template=Template_composed_call::with_call([$this, 'populate_options'], $line);
			$template->default_on_empty='';
			return $template;
		}
		if ($code==='empty_option')
		{
			if (!array_key_exists('title', $line)) return '';
			return $this->make_option_template(null, $line['title'], false, $line);
		}
		if ($code==='selected_options')
		{
			$current=$this->get_selected_value();
			if ($current instanceof \Report_impossible) $template=$this->first_option($line);
			elseif (is_array($current))
			{
				$template=[];
				foreach ($current as $value)
				{
					$template[]=$this->option_by_value($value, $line);
				}
				$template=Template_composed_preset::with_list($template, $line);
			}
			else $template=$this->option_by_value($current, $line);
			if ($template instanceof \Report_impossible) return '';
			return $template;
		}
		if ($code==='count')
		{
			$options=$this->options(false);
			if ($options instanceof \Report_task)
			{
				$callback=function() use ($code, $line)
				{
					return $this->make_template($code, $line);
				};
				return Task_delayed_call::with_call($callback, $options->task);
			}
			if ($options instanceof \Report_impossible) return $options;
			if ($options instanceof \Report) die('BAD COUNT REPORT');
			
			if (is_array($options)) return count($options);
			return $this->sign_report(new \Report_impossible('bad_options'));
		}
		if ($code==='select_id') return $this->select_id;
		if ($code==='options_group') return $this->options_group();
		if ($code==='selected_value')
		{
			$selected=$this->get_selected_value();
			if ($selected instanceof \Report_impossible) return '';
			elseif (is_array($selected)) return implode(',', $selected);
			return $selected;
		}
		
		return parent::make_template($code, $line);
	}
	
	public function options_group()
	{
		if ($this->mode===static::MODE_CUSTOM) return;
		if ($this->in_value_model('options_group')) return $this->value_model_now('options_group');
		if ($this->in_value_model('id_group')) return $this->value_model_now('id_group');
		if (duck_instanceof($this->value, '\Pokeliga\Data\Value_provides_options')) return $this->value_model_now('type');
		return get_class($this->value->master).'_'.$this->value->code;
	}
	
	public function options_posted()
	{
		return
			($this->mode!==static::MODE_CUSTOM) &&
			(property_exists($this->page, 'listed_options_group')) &&
			(!empty($this->page->listed_options_group[$options_group=$this->options_group()])) &&
			(static::$options_by_group[$options_group]!==$this);
	}
	
	// даёт знать, что всё уже схвачено.
	public function reserve_options()
	{
		if (!property_exists($this->page, 'listed_options_group')) $this->page->listed_options_group=[];
		$this->page->listed_options_group[$options_group=$this->options_group()]=true;
		static::$options_by_group[$options_group]=$this;
	}
	
	public function has_custom_options()
	{
		if ($this->in_value_model('options_group')) return false;
	
		return
			$this->in_value_model('select_options') ||
			$this->in_value_model('prepend_options') ||
			$this->in_value_model('append_options') ||
			$this->in_value_model('exclude_options');
	}
	
	public function options_template($line=[])
	{
		$template=Template_composed_call::with_call([$this, 'populate_options'], $line);
		$template->default_on_empty='';
		return $template;
	}
	
	public function populate_options($line=[])
	{
		$tasks=[];
		
		$options=$this->options();
		foreach ($options as $value=>$title)
		{
			$template=$this->make_option_template($value, $title, null, $line);
			$tasks[]=$template;
		}
		
		return $tasks;
	}
	
	public function option_by_value($value, $line=[])
	{
		$options=$this->options();
		if (!array_key_exists($value, $options)) return $this->sign_report(new \Report_impossible('no_option'));
		
		$template=$this->make_option_template($value, $options[$value], null, $line);
		return $template;
	}
	
	public function first_option($line=[])
	{
		$options=$this->options();
		reset($options);
		$value=key($options);
		return $this->option_by_value($value, $line);
	}
	
	public function make_option_template($value, $title=null, $selected=null, $line=[])
	{
		$option_class=$this->option_class;
		$option_line=$line;
		
		$option_line['value']=$value;
		if ($title===null) $title=$value;
		$option_line['title']=$title;
		
		if ($selected===null)
		{
			$current=$this->get_selected_value();
			if ($current instanceof \Report_impossible) $selected=false;
			else $selected=$value == $current;
		}
		$option_line['selected']=$selected;
		
		$template=$option_class::copy_arguments($this);
		$template->line=$option_line;
		
		return $template;
	}
	
	public function options($now=true)
	{
		if ($this->options===null)
		{
			$options=$this->make_options();
			if ( ($now) && ($options instanceof \Report_task) )
			{
				$options->complete();
				$options=$this->make_options(); // теперь задача должна быть выполнена.
			}
			if ($options instanceof \Report_task)
			{
				if ($now) return $this->sign_report(new \Report_impossible('impossible_options'));
				return $options;
			}
			if ($options instanceof \Report_tasks) die ('BAD OPTIONS 1');
			if ($options instanceof \Report_impossible) $options=[0=>'Ошибка!'];
			$this->options=$options;
		}
		if ($this->options===null) { vdump($value); die('NO OPTIONS'); }
		return $this->options;
	}
	
	public function make_options()
	{
		if ($this->in_value_model('select_options')) return $this->value_model('select_options');
		elseif (duck_instanceof($this->value, '\Pokeliga\Data\Value_provides_titled_options')) $options=$this->value->titled_options($this->line);
		elseif ($this->in_value_model('options'))
		{
			$options=$this->value_model('options');
			if ($options instanceof \Report) return $options;
			return array_combine($options, $options);
		}
		elseif (duck_instanceof($this->value, '\Pokeliga\Data\Value_provides_options')) $options=$this->value->options($this->line);
		
		if ($options===null) return $this->sign_report(new \Report_impossible('no_options'));
		if ($options instanceof \Report) return $options;
		
		if ($this->in_value_model('prepend_options')) $options=array_merge($this->value_model_now('prepend_options'), $options);
		if ($this->in_value_model('append_options')) $options=array_merge($options, $this->value_model_now('append_options'));
		return $options;
	}
	
	public function get_selected_value()
	{
		if (!property_exists($this, 'current_option'))
		{
			if (array_key_exists('selected', $this->line)) $result=$this->line['selected'];
			elseif ( (!empty($this->value)) && ($this->value->in_value_model('selected')) ) $result=$this->value->value_model_now('selected');
			elseif (!empty($this->field)) $result=$this->field->content_of($this->code);
			else $result=$this->sign_report(new \Report_impossible('no_selected_option'));
			
			$this->current_option=$result;
		}
		if ($this->current_option===null) return '';
		return $this->current_option;
	}
	
	public function ValueHost_request($code)
	{
		if ($code==='empty_ok') return $this->in_value_model('null') && $this->value_model_now('null');
		return parent::ValueHost_request($code);
	}
	
	public static function content_on_failure()
	{
		return null;
	}
}

// FIXME: зачем оно наследует Temlate_field, если ничего не вводит?
class Template_field_option extends Template_field
{
	public
		$db_key='form.option',
		$elements=['title', 'selected'];
		
	public function make_template($code, $line=[])
	{
		// STUB: возможно, защиту от спецсимволов нужно сделать как-то иначе.
		if ($code==='title')
		{
			if (empty($this->line['title'])) return 'NO TITLE';
			if (is_array($this->line['title'])) { vdump($this->line); vdump($this); die('ARRAY OPTION'); }
			if ($this->line['title'] instanceof \Pokeliga\Task\Task) return $this->line['title'];
			return htmlspecialchars(mb_ucfirst($this->line['title']));
		}
		if ($code==='selected') return ( ($this->line['selected'])?('selected'):('') );
		return parent::make_template($code, $line);
	}
}

/*
####################################################
### Выпадающий список с общим списком вариантов, ###
### повторяющимся в пределах страницы.           ###
####################################################
*/

// FIXME: зачем оно наследует Temlate_field, если ничего не вводит?
class Template_field_select_common_options extends Template_field
{
	public
		$db_key='form.options_for',
		$master_select,
		$elements=['options', 'options_group'];
	
	public function make_template($code, $line=[])
	{
		return $this->master_select->make_template($code, $line);
	}
}

/*
################################################
### Выпадающий список с открыктым перечнем   ###
### вариантов и поиском по вводимой строке.  ###
################################################
*/

class Template_field_select_searchable extends Template_field_select
{

	static
		$key_by_mode=
		[
			Template_field_select::MODE_CUSTOM=>'form.select_searchable',
			Template_field_select::MODE_COMMON=>'form.select_searchable_optimized'
		];
		
	public
		$search=null,
		$select_searchable_elements=['API_arguments', 'count_found'],
		$mode=Template_field_select::MODE_COMMON;
		
	public function recognize_element($code, $line=[])
	{
		if (parent::recognize_element($code)) return true;
		return in_array($code, $this->select_searchable_elements);
	}
	
	public function options_group()
	{
		return $this->value->API_search_arguments();
	}
	
	public function options($now=true)
	{
		if ($this->options!==null) return $this->options;
		return $this->default_found_options_template()->options($now);
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_INIT) $this->prepare();
		if ($this->step===static::STEP_REQUEST_OPTIONS)
		{
			if ($this->options_posted()) return parent::run_step();
			else $this->reserve_options();
			
			$template=$this->default_found_options_template();
			if ($template instanceof \Report) return $template;
			return $this->sign_report(new \Report_task($template));
		}
		return parent::run_step();
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='API_arguments')
		{
			$result=$this->value->API_search_arguments();
			if ($this->value->in_value_model('option_title_template')) $result.='&template='.$this->value->value_model_now('option_title_template');
			// возможно, это совмещение должно быть в черте Value_searchable_entity или какой-то ещё, спровождающей интерфейс Value_searchable_options, однако не хочется переусложнять.
			return $result;
		}
		if ($code==='options')
		{
			return $this->default_found_options_template(); // не учитывает поиска и командной строки, но пока что это и не используется.
		}
		if ($code==='count_found')
		{
			return $this->default_found_options_template()->get_count();
		}
		else return parent::make_template($code, $line);
	}
	
	public $default_found_options=null;
	public function default_found_options_template()
	{
		if ($this->default_found_options===null)
		{
			if ($this->in_value_model('default_search')) $search=$this->value_model_now('default_search');
			else $search=$this->search;
			$this->default_found_options=$this->value->found_options_template($search);
			$this->default_found_options->option_class=$this->option_class;
		}
		return $this->default_found_options;
	}
	
	public function option_by_value($value, $line=[])
	{
		$line=array_merge($this->line, $line);
		$template=$this->value->option_by_value($value, $line);
		return $template;
	}
	
	public function ValueHost_request($code)
	{
		if ($code==='paged') return $this->default_found_options_template()->is_paged();
		return parent::ValueHost_request($code);
	}
}

/*
#####################################################
### Выпадающий список со множественным выделением ###
#####################################################
*/

class Template_field_multiselect extends Template_field_select
{
	public
		$db_key='form.multiselect_input',
		$multiselect_elements=['max'];
	
	public function recognize_element($code, $line=[])
	{
		if (parent::recognize_element($code)) return true;
		return in_array($code, $this->multiselect_elements);
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='max')
		{
			if ($this->in_value_model('max')) return $this->value_model_now('max');
			return '';
		}
		return parent::make_template($code, $line);
	}
}

/*
##########################
### Список радиокнопок ###
##########################
*/

class Template_field_radios extends Template_field_select
{
	public
		$db_key='form.radios',
		$mode=Template_field_select::MODE_CUSTOM,
		$option_class='Template_field_radio';
	
	//	STUB: тут может быть что-то типа Template_field_radios_common
	public function resolve_class(&$final=true)
	{
		return $this;
	}
}

class Template_field_radios_inline extends Template_field_radios 
{
	public
		$db_key='form.radios_inline';
}
?>