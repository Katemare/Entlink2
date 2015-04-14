<?

class FieldSet_list extends FieldSet_sub
{
	use Multistage_input
	{
		Multistage_input::set_model_stage as std_set_model_stage;
	}
	
	const
		SUBFIELDS_CODE='subfields',
		LIST_ID_CODE='list_id',
		MAX_CODE='max',
		
		PLUS_CODE='plus',
		PLUS_DB_KEY='form.field_list_plus',
		
		EMPTY_TEMPLATE_CODE='empty_template',
				
		COUNT_KEY='__count',
		BASE_KEY='__base',
		EMPTY_KEY='__empty',
		
		STAGE_COUNT=1,
		STAGE_FIELDS=2;

	static
		$next_list_id=0;
		
	public
		$model_stages=
		[
			FieldSet_list::STAGE_FIELDS=>true
		],
		$model_stage=FieldSet_list::STAGE_COUNT,
		$input_fields=[FieldSet_list::COUNT_KEY],
		$list_id,
		$template_db_key='form.field_list',
		$main_template_class='Template_fieldset_list',
		$model=
		[
			FieldSet_list::COUNT_KEY=>
			[
				'type'=>'unsigned_int',
				'template'=>'hidden',
				'min'=>0,
				'max'=>100,
				'default'=>0
			]
		],
		$base_entry_model=null;
	
	public function instantiated()
	{
		$this->list_id=static::$next_list_id++;
	}
	
	public function supply_model()
	{
		parent::supply_model();
		if (array_key_exists('min', $this->super_model))
		{
			$this->model['__count']['min']=$this->super_model['min'];
			$this->model['__count']['default_for_display']=$this->super_model['min'];
		}
		if (array_key_exists('max', $this->super_model)) $this->model['__count']['max']=$this->super_model['max'];
		
		$this->model[static::BASE_KEY]=$this->make_base_model();
		if ($this->model[static::BASE_KEY] instanceof Report_impossible) { vdump($this); die ('NO BASE MODEL'); }
		
		$this->model[static::EMPTY_KEY]=$this->model[static::BASE_KEY];
		$this->model[static::EMPTY_KEY]['name']='%ord'.$this->list_id.'%';
		if (empty($this->model[static::EMPTY_KEY]['prefix'])) $this->model[static::EMPTY_KEY]['prefix']='';
		$this->model[static::EMPTY_KEY]['prefix'].='%ord'.$this->list_id.'%';
	}
	
	public function is_fixed()
	{
		return
			$this->in_value_model('min') &&
			$this->in_value_model('max') &&
			($this->value_model_now('min')!==null) &&
			$this->value_model_now('min')===$this->value_model('max');
	}
	
	public function make_base_model()
	{
		if ($this->in_value_model('base_model')) $model=$this->value_model_now('base_model');
		elseif ($this->base_entry_model!==null) $model=$this->base_entry_model;
		elseif ($this->in_value_model('entry_type'))
		{
			$model=
			[
				'type'=>$this->value_model_now('entry_type')
			];
			if ($this->in_value_model('entry_template')) $model['template']=$this->value_model_now('entry_template');
		}
		elseif ($this->in_value_model('entry_fieldset_type'))
		{
			$model=
			[
				'fieldset_type'=>$this->value_model_now('entry_fieldset_type')
			];
		}
		else return $this->sign_report(new Report_impossible('no_base_model'));
		return $model;
	}
	
	public function is_list_code($code) // проверяет, относится ли код значения к одному из значений, представляющих элементы списка. ведь могут быть добавочные значения, как число баллов в перечислении награды к квесту.
	{
		return is_numeric($code);
	}
	
	public function model($code, $soft=false)
	{
		$result=parent::model($code, true);
		if (!($result instanceof Report_impossible)) return $result;
		
		if ($this->is_list_code($code))
		{
			$model=$this->produce_model($code);
			$this->model[$code]=$model;
			$this->prefix_model($code);
		}
		
		return parent::model($code, $soft);
	}
	
	public function produce_model($ord)
	{
		$model=$this->model[static::BASE_KEY];
		unset($model['name']);
		$model['prefix']=$ord; // применяется в FieldSet_sub
		return $model;
	}
	
	public function create_value($code)
	{
		$value=parent::create_value($code);
		if ( ($code===static::EMPTY_KEY) && ($value instanceof FieldSet_list) ) $value->list_id='%list_id%';
		return $value;
	}
	
	public function update_subfields()
	{
		if ($this->model_stage===static::STAGE_COUNT) return $this->change_model_stage(static::STAGE_FIELDS);
		return false;
	}
	
	public function set_model_stage($new_stage)
	{
		if ($new_stage==static::STAGE_FIELDS)
		{
			$count=$this->content_of(static::COUNT_KEY);
			if ($count==0) return false;
			$this->input_fields=array_merge($this->input_fields, range(0, $count-1));
			for($x=0; $x<$count; $x++)
			{
				$this->model($x);
			}
		}
	}
	
	public function process_valid()
	{
		$count=$this->content_of(static::COUNT_KEY);
		$result=[];
		for ($x=0; $x<$count; $x++)
		{
			$result[$x]=$this->content_of($x);
		}
		$this->process_success=true;
		return $this->sign_report(new Report_resolution($result));
	}
	
	public function set($content, $source_code=Value::BY_OPERATION)
	{		
		$list_set=$this->extract_list($content);
		if ($list_set instanceof Report) return Report;
		
		$list_set[static::COUNT_KEY]=count($list_set);
		if (array_key_exists($key=static::EMPTY_KEY, $content)) $list_set[$key]=$content[$key];
		$this->set_by_array($list_set, $source_code);
	}
	
	public function extract_list($content)
	{
		if ($content instanceof EntitySet) $content=$content->values; // FIX: и другие типы ValueSet'ов?
		if (!is_array($content)) return $this->sign_report(new Report_impossible('bad_content'));
		
		$list=[];
		for ($x=0; array_key_exists($x, $content); $x++)
		{
			$this->model($x);
			$list[$x]=$content[$x];
		}
		return $list;
	}
	
	public function template($code, $line=[])
	{
		if ($code===static::MAX_CODE) return $this->max();
		elseif ($code===static::SUBFIELDS_CODE) return $this->subfields_template($line);
		elseif ($code===static::PLUS_CODE) return $this->plus_template($line);
		elseif ($code===static::LIST_ID_CODE) return $this->list_id;
		elseif ($code===static::EMPTY_TEMPLATE_CODE) return $this->empty_template($line);
		elseif ($this->is_list_code($code)) $this->model($code); // создаёт модель.
		
		$template=parent::template($code, $line);
		if ( ( ($this->is_list_code($code)) || ($code===static::EMPTY_KEY)) && ($template instanceof Template) )
		{
			$container=$this->field_container($template, $code);
			return $container;
		}
		return $template;
	}
	
	public function max()
	{
		return $this->model[static::COUNT_KEY]['max'];
	}
	
	public function field_container($subfield_template, $ord)
	{
		if ($subfield_template instanceof Template_field_invisible) return $subfield_template;
		$template=Template_subfield_container::for_subfield_template($subfield_template, $this, $ord);
		return $template;
	}
	
	public function plus_template($line=[])
	{
		if ($this->is_fixed()) return '';
		return Template_from_db::with_db_key(static::PLUS_DB_KEY, $line);
	}
	
	public function empty_template($line=[])
	{
		if ($this->is_fixed()) return '';
		$template=$this->template(static::EMPTY_KEY, $line);
		$empty_template=Template_js_html::from_template($template, $line);
		return $empty_template;
	}
	
	public function subfields_template($line=[])
	{
		$count=$this->value(static::COUNT_KEY);
		if ($count==0) $list='';
		else $list=Template_composed_call::with_call([$this, 'populate_subfields'], $line);
		
		$template=Template_subfield_container::for_subfield_template($list, $this, static::SUBFIELDS_CODE, $line);
		$template->db_key='form.subfields_container';
		
		return $template;
	}
	
	public function populate_subfields($line=[])
	{
		$count=$this->content_of(static::COUNT_KEY); // число уже было запрошено в предыдущем методе.
		if ($count instanceof Report_impossible) return ['NO COUNT'];
		$templates=[];
		for ($x=0; $x<$count; $x++)
		{
			$template=$this->template($x, $line);
			if (($template===null) || ($template instanceof Report_impossible)) return $this->sign_report(new Report_impossible('bad_subfield'));
			$templates[]=$template;
		}
		return $templates;
	}
	
	public function consider_template_line($line)
	{
		if (array_key_exists('entry_prepend', $line))
		{
			$this->model[static::BASE_KEY]['prepend']=$line['entry_prepend'];
			$this->model[static::EMPTY_KEY]['prepend']=$line['entry_prepend'];
		}
		if (array_key_exists('entry_append', $line))
		{
			$this->model[static::BASE_KEY]['append']=$line['entry_append'];
			$this->model[static::EMPTY_KEY]['append']=$line['entry_append'];
		}
		parent::consider_template_line($line);
	}
	
	public function xml_export($field=false)
	{
		if ($field===false)
		{
			$field=InputSet::instant_fill('field', 'keyword');
			if ($field instanceof Report_impossible) $field=null;
		}
		if ($field===null) return $this->main_template();
		else return $this->template($field);
	}
}

class FieldSet_multiselect extends FieldSet_list
{
	const
		DEFAULT_ENTRY_TEMPLATE='hidden',
		SELECT_KEY='select';
	
	static
		$multiselect_model=
		[
			FieldSet_multiselect::SELECT_KEY=>
			[
				'template'=>'multiselect'
			]
		];
	
	public
		$template_db_key='form.multiselect';

	public function supply_model()
	{
		if ($this->in_value_model('select_model')) $this->model[static::SELECT_KEY]=$this->value_model_now('select_model');
		else $this->model=array_merge($this->model, static::$multiselect_model);
		
		parent::supply_model();
		
		// STUB! это должно делаться иначе.
		$this->model[static::SELECT_KEY]['max']=$this->max();
		
		if (!array_key_exists('type', $this->model[static::SELECT_KEY]))
		{
			if ($this->in_value_model('select_type')) $this->model[static::SELECT_KEY]['type']=$this->value_model_now('select_type');
			elseif ($this->in_value_model('entry_type')) $this->model[static::SELECT_KEY]['type']=$this->value_model_now('entry_type');
			else $this->model[static::SELECT_KEY]['type']='enum';
		}
		
		if ( ($this->in_value_model('id_group')) && (!array_key_exists('id_group', $this->model[static::SELECT_KEY])) ) 
		{
			$this->model[static::SELECT_KEY]['id_group']=$this->value_model_now('id_group');
			$this->model[static::BASE_KEY]['id_group']=$this->value_model_now('id_group');
			$this->model[static::EMPTY_KEY]['id_group']=$this->value_model_now('id_group');
		}
		
		if ( ($this->in_value_model('options')) && (!array_key_exists('options', $this->model[static::SELECT_KEY])) )
		{
			$this->model[static::SELECT_KEY]['options']=$this->value_model_now('options');
			$this->model[static::BASE_KEY]['options']=$this->value_model_now('options');
			$this->model[static::EMPTY_KEY]['options']=$this->value_model_now('options');
		}
		
		if ( ($this->in_value_model('select_options')) && (!array_key_exists('select_options', $this->model[static::SELECT_KEY])) )
		{
			$this->model[static::SELECT_KEY]['select_options']=$this->value_model_now('select_options');
			$this->model[static::BASE_KEY]['select_options']=$this->value_model_now('select_options');
			$this->model[static::EMPTY_KEY]['select_options']=$this->value_model_now('select_options');
		}
			
		if ( ($this->in_value_model('exclude_options')) && (!array_key_exists('exclude_options', $this->model[static::SELECT_KEY])) )
		{
			$this->model[static::SELECT_KEY]['exclude_options']=$this->value_model_now('exclude_options');
			$this->model[static::BASE_KEY]['exclude_options']=$this->value_model_now('exclude_options');
			$this->model[static::EMPTY_KEY]['exclude_options']=$this->value_model_now('exclude_options');
		}
	}
	
	public function make_base_model()
	{
		$model=parent::make_base_model();
		if ($model instanceof Report_impossible) $model=$this->fallback_base_model();
		if (!array_key_exists('template', $model)) $model['template']=static::DEFAULT_ENTRY_TEMPLATE;
		return $model;
	}
	
	public function fallback_base_model()
	{
		$model=$this->model[static::SELECT_KEY];
		unset($model['template']);
		return $model;
	}
}

class FieldSet_slugselect extends FieldSet_multiselect
{		
	public
		$template_db_key='form.slugselect';
	
	public function supply_model()
	{
		parent::supply_model();
		$this->model[static::SELECT_KEY]['template']='select';
	}
	
	public function set($content, $source_code=Value::BY_OPERATION)
	{
		$result=parent::set($content, $source_code);
		if ($result instanceof Report) return $result;
		
		$count=$this->content_of(static::COUNT_KEY);
		$list=[];
		for ($x=0; $x<$count; $x++)
		{
			$list[$x]=$this->content_of($x);
		}
		$this->model[static::SELECT_KEY]['selected']=$list;
	}
}

class FieldSet_dual_slugselect extends FieldSet_slugselect
{
	const
		SELECT2_KEY='select2',
		COMBINE_KEY='combine';
		
	static
		$dual_multiselect_model=
		[
			FieldSet_dual_slugselect::SELECT2_KEY=>
			[
				'type'=>'enum',
				'template'=>'select'
			],
			FieldSet_dual_slugselect::COMBINE_KEY=>
			[
				'type'=>'bool',
				'template'=>'button'
			]
		];
	
	public
		$template_db_key='form.dual_slugselect'; // разница исключитьельно в Яваскрипте.

	public function supply_model()
	{
		parent::supply_model();
		$this->model=array_merge($this->model, static::$dual_multiselect_model);
				
		if ( ($this->in_value_model('options')) && (!array_key_exists('options', $this->model[static::SELECT2_KEY])) )
			$this->model[static::SELECT2_KEY]['options']=$this->value_model_now('options');
		
		if ( ($this->in_value_model('select_options')) && (!array_key_exists('select_options', $this->model[static::SELECT2_KEY])) )
			$this->model[static::SELECT2_KEY]['select_options']=$this->value_model_now('select_options');
	}
	
	public function fallback_base_model()
	{
		return ['type'=>'string'];
	}
}

class Template_fieldset_list extends Template_fieldset
{
	public function initiated()
	{
		if (!empty($this->page))
		{
			$this->page->register_requirement('js', Engine()->module_url('Form', 'list.js'), Page::PRIORITY_INTERACTION_FRAMEWORK);
			$this->page->register_requirement('css', Engine()->module_url('Form', 'list.css'));
		}
		parent::initiated();
	}
}

class Template_subfield_container extends Template_from_db
{
	public
		$db_key='form.field_list_subfield',
		$field,
		$subfield,
		$ord,
		$elements=['html_id', 'subfield', 'ord'];
	
	public function initiated()
	{
		parent::initiated();
		$this->setup_subtemplate($this->subfield);
	}
	
	public static function for_subfield_template($subfield_template, $master_fieldset, $ord, $line=[])
	{
		$template=static::with_line($line);
		$template->subfield=$subfield_template;
		$template->field=$master_fieldset;
		$template->ord=$ord;
		return $template;
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='html_id') return 'list'.$this->field->list_id.'_'.$this->ord();
		if ($code==='subfield') return $this->subfield;
		if ($code==='ord') return $this->ord();
		return parent::make_template($code, $line);
	}
	
	public function ord()
	{
		if ($this->ord===FieldSet_list::EMPTY_KEY) return '%ord'.$this->field->list_id.'%';
		return $this->ord;
	}
}
?>