<?
namespace Pokeliga\Form;

class Page_form_api extends Page_xml
{
	const
		INPUTSET_CLASS='InputSet_form_api';

	public
		$possible_messages=['form_field', 'select_options'],
		$deliver_html=['form_field'],
		$form,
		$field;
	
	public function analyze_content()
	{
		$message=$this->message();
		if ($message==='form_field')
		{
			$class=$this->input->content_of('form');
			if (!class_exists($class)) return $this->sign_report(new \Report_impossible('bad_form_class'));
			if (!string_instanceof($class, 'FieldSet')) return $this->sign_report(new \Report_impossible('bad_form_class'));
			if (!$class::xml_exportable()) return $this->sign_report(new \Report_impossible('bad_form_class'));
			$this->form=$class::create_for_xml();
			
			return $this->advance_step();
		}
		elseif ($message==='select_options')
		{
			$params=$this->request_code();
			parse_str($params, $data);
			$model=['template'=>'select_xml'];
			if (array_key_exists('group', $data))
			{
				$group=$data['group'];
				if (!class_exists($group)) return $this->sign_report(new \Report_impossible('bad_entity_class'));
				if (!string_instanceof($group, 'EntityType')) return $this->sign_report(new \Report_impossible('bad_entity_class'));
				$model['type']='id';
				$model['id_group']=$group;
				
				if (array_key_exists('range', $data))
				{
					$model['range']=$data['range'];
					
					// STUB! в будущем такие вопросы должен решать Value_id, подсказывающий Select для ограничения возможных айди.
					$range_model=[];
					if (array_key_exists('mission', $data)) $range_model['mission']=$data['mission'];
					if (array_key_exists('mask', $data)) $range_model['mask']=$data['mask'];
					if (!empty($range_model)) $model['range_model']=$range_model;
				}
			}
			else return $this->sign_report(new \Report_impossible('bad_select_params'));
			
			$model=[ 'select'=>$model ];
			$this->form=FieldSet::create_for_display_from_model($model);
			
			return $this->advance_step();
		}
		return $this->sign_report(new \Report_impossible('bad_message'));
	}
	
	public function content()
	{
		$message=$this->message();
		if ($message==='form_field')
		{
			$template=$this->form->xml_export();
			return $template;
		}
		elseif ($message==='select_options')
		{
			$template=$this->form->template('select');
			$template->search=$this->input->value('search');
			return $template;
		}
	}
}

class InputSet_form_api extends InputSet_complex
{
	use Multistage_input;
	
	const
		STAGE_INIT=0,
		STAGE_FORM_FIELD=1,
		STAGE_SELECT_OPTIONS=2;
	
	public
		$source_setting=InputSet::SOURCE_GET_POST,
		$model_stages=
		[
			InputSet_form_api::STAGE_FORM_FIELD		=>['form'],
			InputSet_form_api::STAGE_SELECT_OPTIONS	=>['search']
		],
		$model_stage=InputSet_form_api::STAGE_INIT,
		$input_fields=['message', 'request_code'],
		$api_model=
		[
			'form'=>
			[
				'type'=>'keyword'
			],
			'search'=>
			[
				'type'=>'string',
				'auto_invalid'=>['']
			]
		];
	
	public static function from_model($model=null, $setting=null)
	{
		$inputset=parent::from_model($model, $setting);
		$inputset->model+=$inputset->api_model; // без замены полей, которые уже заданы.
		return $inputset;
	}
	
	public function update_subfields()
	{
		if ($this->model_stage===static::STAGE_INIT)
		{
			if ($this->content_of('message')==='form_field') return $this->change_model_stage(static::STAGE_FORM_FIELD);
		}
		return false;
	}
}

class Template_field_select_xml extends Template_field_select_searchable
{
	public
		// $db_key='form.xml_select_searchable',
		$db_key='form.found_options',
		$option_class='Template_field_option_xml';
		
		
	public function resolve_class(&$final=true)
	{
		return $this;
	}
	
	public function has_custom_options()
	{
		return false;
	}
	
	public $default_found_options=null;
	public function default_found_options_template()
	{
		if ($this->default_found_options===null)
		{
			$template=parent::default_found_options_template();
			$template->db_key='form.xml_found_options';
			return $template;
		}
		return parent::default_found_options_template();
	}
}

class Template_field_option_xml extends Template_field_option
{
	public $db_key='form.xml_option';
}
?>