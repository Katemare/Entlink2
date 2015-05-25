<?

trait Form_image_delete
{
/*
	public
		$input_fields=['submit'],
		$model_stage=Form_image_point::STAGE_INIT,
		$model_stages=
		[
			Form_image_point::STAGE_EDIT=['id', 'coord_x', 'coord_y', 'submit', 'parent_fragment'],
			Form_image_point::STAGE_DELETE=['delete']
		];
*/
	public function update_subfields()
	{
		if ($this->model_stage===Form_image_point::STAGE_INIT)
		{
			if ($this->content_of('submit')===Form_image_point::SUBMIT_DELETE) return $this->change_model_stage(Form_image_point::STAGE_DELETE);
			else return $this->change_model_stage(Form_image_point::STAGE_EDIT);
		}
		else return false;
	}
	
	// STUB! это должен быть такой же стандартный функционал с привлечением Keeper'ов, как сохранение.
	public function create_valid_processor_delete()
	{
		if ($this->content_of('delete')!==true) return $this->sign_report(new Report_impossible('delete_not_approved'));
		if (!$this->entity()->exists())
			Router()->redirect(Router()->url('gallery/gallery.php'));
			//return $this->sign_report(new Report_impossible('doesnt_exist')); 
			
		$queries=$this->delete_queries();
		foreach ($queries as $query)
		{
			$result=Retriever()->run_query($query);
			if ($result instanceof Report) return $result;
		}			
		$this->redirect_after_delete();
		
		// до этой строчки не должно дойти.
		return $this->sign_report(new Report_success());
	}
	
	public function delete_queries()
	{
		$basic_aspect=$this->entity()->get_aspect('basic');
		$query=
		[
			'action'=>'delete',
			'table'=>$basic_aspect::$default_table,
			'where'=>['id'=>$this->entity()->db_id]
		];
		return [$query];
	}
	
	public function redirect_after_delete()
	{
		Router()->redirect(Router()->url('gallery/gallery.php'));
	}
}

abstract class Form_image_point extends Form_entity
{
	const
		MAP_INPUT_CODE='map',
		STAGE_INIT=0,
		STAGE_EDIT=1,
		STAGE_DELETE=2,
		SUBMIT_DELETE='delete';
	
	static
		$basic_model=
		[
			'image'=>
			[
				'type'=>'id',
				'id_group'=>'Image',
				'template'=>'hidden',
				'for_entity'=>true
			],
			// FIX: в принципе эти зависимости координат может и не добавлять, потому что они уже будут проверены при создании сущности. нужно выработать философию на этот счёт.
			'coord_x'=>
			[
				'type'=>'coord_x',
				'template'=>'hidden',
				'validators'=>['less_or_equal_to_sibling'],
				'normal_content'=>[''], // важно, чтобы ввод '' в поле координаты сохранялся, но считался недействительным. позволит отличить ввод '' от нуля.
				'auto_invalid'=>[''],
				'less_or_equal'=>'width',
				'dependancies'=>['width'],
				'for_entity'=>true
			],
			'coord_y'=>
			[
				'type'=>'coord_y',
				'template'=>'hidden',
				'validators'=>['less_or_equal_to_sibling'],
				'normal_content'=>[''],
				'auto_invalid'=>[''],
				'less_or_equal'=>'height',
				'dependancies'=>['height'],
				'for_entity'=>true
			],
			'width'=>
			[
				'type'=>'width'
			],
			'height'=>
			[
				'type'=>'height'
			],
			'parent_fragment'=>
			[
				'type'=>'id',
				'id_group'=>'ImageLocation',
				'template'=>'select_fragment',
				'image_source'=>'image',
				'null'=>true,
				'default'=>null,
				'auto_valid'=>[null],
				'replace'=>[0=>null],
				'for_entity'=>true,
				'existing'=>true
				// допвалидаторы не нужны - соответствие проверит сущность.
			],
			'submit'=>
			[
				'type'=>'string',
				'template'=>'submit'
			]
		];
		
	public
		$source_setting=InputSet::SOURCE_POST,
		$id_group='ImageLocation',
		$location_type=Image::LOCATION_POINT,
		$map_template_class='Template_image_point_input',
		$input_fields=['image', 'coord_x', 'coord_y', 'parent_fragment', 'submit'];
	
	public function image()
	{
		return $this->produce_value('image')->get_entity();
	}
	
	// STUB
	public function rightful()
	{
		return Module_AdoptsGame::instance()->admin();
	}
	
	public function fill_value($value)
	{
		$value=$this->produce_value($value);
		if ($value instanceof Value_dimension)
		{
			$image=$this->image();
			$dimension=$image->value($value->code);
			if ($dimension instanceof Report_impossible) $value->set_state(Value::STATE_FAILED);
			else $value->set($dimension, Value::NEUTRAL_CHANGE);
			return;
		}
		
		parent::fill_value($value);
	}
	
	public function template($code, $line=[])
	{
		if ($code===static::MAP_INPUT_CODE) return $this->map_template($line);
		return parent::template($code, $line);
	}
	
	public function map_template($line=[])
	{
		$class=$this->map_template_class;
		$template=$class::with_line($line);
		$template->context=$this->image();
		$template->target_field_names[$template::X_NAME_CODE]=$this->name('coord_x');
		$template->target_field_names[$template::Y_NAME_CODE]=$this->name('coord_y');
		return $template;
	}
	
	public function prepare_entity($entity)
	{
		$entity->set('location_type', $this->location_type);
		$entity=parent::prepare_entity($entity);
		return $entity;
	}
	
	public function redirect_successful()
	{
		Router()->redirect($this->entity()->value('image_entity')->value('with_locations_url'));
	}
}

trait Form_image_new_location
{
	public function consider_template_line($line)
	{
		if (array_key_exists('image', $line)) $this->set_value('image', $line['image']);
	}
	
	public function session_name()
	{
		return parent::session_name().$this->content_of('image');
	}
}

class Form_image_new_point extends Form_image_point
{
	use Form_new_entity, Form_image_new_location;
	
	public
		$slug='file_new_image_point',
		$content_db_key='file.form_new_image_point',
		$action='new_point_process.php',
		$js_on_submit='is_point_inputted';
}

class Form_image_edit_point extends Form_image_point
{
	use Form_edit_entity_simple, Multistage_input, Form_image_delete
	{
		Form_edit_entity_simple::create_valid_processor as std_create_valid_processor;
	}
	
	// WIP
	static
		$edit_model=
		[
			'id'=>
			[
				'type'=>'id',
				'template'=>'hidden',
				'id_group'=>'ImageLocation',
				'from_entity'=>true
			],
			'delete'=>
			[
				'type'=>'bool',
				'template'=>'checkbox',
				'default_for_display'=>false
			],
			'image'=>
			[
				'type'=>'id',
				'id_group'=>'Image',
				'from_entity'=>true
			],
		];
	
	public
		$slug='file_edit_image_point',
		$content_db_key='file.form_edit_image_point',
		$action='edit_point_process.php',
		$input_fields=['submit'],
		$model_stage=Form_image_point::STAGE_INIT,
		$model_stages=
		[
			Form_image_point::STAGE_EDIT=>['id', 'coord_x', 'coord_y', 'submit', 'parent_fragment'],
			Form_image_point::STAGE_DELETE=>['delete']
		];
		
	public function supply_model()
	{
		parent::supply_model();
		$this->model=array_merge($this->model, self::$edit_model);
		/*
		foreach (self::$erase_for_edit as $code)
		{
			unset($this->model[$code]);
		}
		*/
	}
	
	public function image()
	{
		return $this->entity()->value('image_entity');
	}
	
	public function create_valid_processor()
	{
		if ($this->model_stage===static::STAGE_EDIT) return $this->std_create_valid_processor();
		elseif ($this->model_stage===static::STAGE_DELETE) return $this->create_valid_processor_delete();
		else die ('BAD MODE');
	}
}

abstract class Form_image_fragment extends Form_image_point
{
	const
		MAP_INPUT_CODE='map';

	static
		$fragment_model=	// для обращения через self::
		[
			'coord_x2'=>
			[
				'type'=>'coord_x',
				'template'=>'hidden',
				'validators'=>['less_or_equal_to_sibling', 'greater_or_equal_to_sibling'],
				'normal_content'=>[''],
				'auto_invalid'=>[''],
				'less_or_equal'=>'width',
				'greater_or_equal'=>'coord_x',
				'dependancies'=>['width', 'coord_x'],
				'for_entity'=>true
			],
			'coord_y2'=>
			[
				'type'=>'coord_y',
				'template'=>'hidden',
				'validators'=>['less_or_equal_to_sibling', 'greater_or_equal_to_sibling'],
				'normal_content'=>[''],
				'auto_invalid'=>[''],
				'less_or_equal'=>'height',
				'greater_or_equal'=>'coord_y',
				'dependancies'=>['height', 'coord_y'],
				'for_entity'=>true
			]
		],
		$fragment_erase=['parent_fragment'];
		
	public
		$id_group='ImageLocation',
		$location_type=Image::LOCATION_FRAGMENT,
		$map_template_class='Template_image_fragment_input',
		$input_fields=['image', 'coord_x', 'coord_y', 'coord_x2', 'coord_y2', 'submit'];
	
	public function supply_model()
	{
		parent::supply_model();
		$this->model=array_merge($this->model, self::$fragment_model);
		foreach (self::$fragment_erase as $code)
		{
			unset($this->model[$code]);
		}
	}
	
	public function map_template($line=[])
	{
		$template=parent::map_template($line);
		$template->target_field_names[$template::X2_NAME_CODE]=$this->name('coord_x2');
		$template->target_field_names[$template::Y2_NAME_CODE]=$this->name('coord_y2');
		return $template;
	}
	
	public function redirect_successful()
	{
		Router()->redirect($this->produce_value('image_entity')->value('with_locations_url'));
	}
}

class Form_image_new_fragment extends Form_image_fragment
{
	use Form_new_entity, Form_image_new_location;
	
	public
		$slug='file_new_image_fragment',
		$content_db_key='file.form_new_image_fragment',
		$action='new_fragment_process.php',
		$js_on_submit='is_fragment_inputted';
}

class Form_image_edit_fragment extends Form_image_fragment
{
	use Form_edit_entity_simple, Multistage_input, Form_image_delete
	{
		Form_edit_entity_simple::create_valid_processor as std_create_valid_processor;
		Form_image_delete::delete_queries as std_delete_queries;
	}
	
	// WIP
	static
		$edit_model=
		[
			'id'=>
			[
				'type'=>'id',
				'template'=>'hidden',
				'id_group'=>'ImageLocation',
				'from_entity'=>true
			],
			'delete'=>
			[
				'type'=>'bool',
				'template'=>'checkbox',
				'default_for_display'=>false
			]
		],
		$erase_for_edit=['image'];
	
	public
		$slug='file_edit_image_fragment',
		$content_db_key='file.form_edit_image_fragment',
		$action='edit_fragment_process.php',
		$input_fields=['submit'],
		$model_stage=Form_image_point::STAGE_INIT,
		$model_stages=
		[
			Form_image_point::STAGE_EDIT=>['id', 'coord_x', 'coord_y', 'coord_x2', 'coord_y2', 'submit'],
			Form_image_point::STAGE_DELETE=>['delete']
		];
		
	public function supply_model()
	{
		parent::supply_model();
		$this->model=array_merge($this->model, self::$edit_model);
		foreach (self::$erase_for_edit as $code)
		{
			unset($this->model[$code]);
		}
	}
	
	public function delete_queries()
	{
		$queries=$this->std_delete_queries();
		
		$basic_aspect=$this->entity()->get_aspect('basic');
		$queries[]=
		[
			'action'=>'update',
			'table'=>$basic_aspect::$default_table,
			'set'=>['parent_fragment'=>null],
			'where'=>['parent_fragment'=>$this->entity()->db_id]
		];
		return $queries;
	}
	
	public function image()
	{
		return $this->entity()->value('image_entity');
	}
	
	public function create_valid_processor()
	{
		if ($this->model_stage===static::STAGE_EDIT) return $this->std_create_valid_processor();
		elseif ($this->model_stage===static::STAGE_DELETE) return $this->create_valid_processor_delete();
		else die ('BAD MODE');
	}
}


class Template_image_point_input extends Template_from_db
{
	const
		X_NAME_CODE='x_name',
		Y_NAME_CODE='y_name',
		MAP_ID_CODE='map_id';
		
	static
		$target_field_codes=[Template_image_point_input::X_NAME_CODE, Template_image_point_input::Y_NAME_CODE],
		$next_map_id=0;
		
	public
		$db_key='file.image_point_input',
		$target_field_names=[],
		$elements=[Template_image_point_input::X_NAME_CODE, Template_image_point_input::Y_NAME_CODE, Template_image_point_input::MAP_ID_CODE],
		$map_id;
	
	public function __construct()
	{
		parent::__construct();
		$this->map_id=static::$next_map_id++;
	}
	
	public function target_field_name($code)
	{
		if (!array_key_exists($code, $this->target_field_names))
		{
			if (array_key_exists($code, $this->line)) $this->target_field_names[$code]=$this->line[$code];
			else $this->target_field_names[$code]=$code;
		}
		return $this->target_field_names[$code];
	}

	public function run_step()
	{
		if ($this->step===static::STEP_INIT)
		{
			$this->page->register_requirement('css', Router()->module_url('File', 'image_location.css'));
			$this->page->register_requirement('js', Router()->module_url('File', 'image_location.js'));
		}
		return parent::run_step();
	}
	
	public function make_template($code, $line=[])
	{
		if (in_array($code, static::$target_field_codes)) return $this->target_field_name($code);
		if ($code===static::MAP_ID_CODE) return $this->map_id;
	}
}

class Template_image_fragment_input extends Template_image_point_input
{
	const
		X2_NAME_CODE='x2_name',
		Y2_NAME_CODE='y2_name';
		
	static
		$target_field_codes=
		[
			Template_image_point_input::X_NAME_CODE, Template_image_point_input::Y_NAME_CODE,
			Template_image_fragment_input::X2_NAME_CODE, Template_image_fragment_input::Y2_NAME_CODE
		];
		
	public
		$db_key='file.image_fragment_input',
		$elements=
		[
			Template_image_point_input::X_NAME_CODE, Template_image_point_input::Y_NAME_CODE, Template_image_point_input::MAP_ID_CODE,
			Template_image_fragment_input::X2_NAME_CODE, Template_image_fragment_input::Y2_NAME_CODE
		];
}

class Template_field_select_fragment extends Template_field_select
{
	public function resolve_class(&$final=true)
	{
		return $this;
	}

	public function make_options()
	{
		$image_source=$this->value_model_now('image_source');
		$image=$this->field->produce_value($image_source);
		$entity=$image->get_entity(); // FIX: пока не предполагает действий в случае, если значение ещё не заполнено (но такая ситуация сейчас не бывает).
		
		$fragments=$entity->value_object('fragments'); // FIX: также пока не предполагает реакции на то, что для получения объекта требуется подтверждение сущности, но в текущем использовании это не создаёт проблем.
		$options=$fragments->options();
		if ( (is_array($options)) && ($this->in_value_model('null')) && ($this->value_model_now('null')==true) ) $options=[0=>'Нет']+$options;
		return $options;
	}
}
?>