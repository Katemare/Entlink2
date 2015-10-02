<?
namespace Pokeliga\User;

class Page_user_api extends Page_xml
{
	const
		INPUTSET_CLASS='InputSet_user_api';

	public
		$possible_messages=['contribution_description'],
		$contribution;
	
	public function analyze_content()
	{
		$message=$this->message();
		if ($message==='contribution_description')
		{
			$id=$this->input->content_of('id');
			$group=$this->input->content_of('group');
			$entity=$this->pool()->entity_from_provider(['contribution_by_id', $id, $group], $group);
			$entity->verify();
			if ($entity->state===Entity::STATE_FAILED) return $this->sign_report(new \Report_impossible('bad_contribution'));
			$this->contribution=$entity;
			return $this->advance_step();
		}
		return $this->sign_report(new \Report_impossible('bad_message'));
	}
	
	public function content()
	{
		$message=$this->message();
		if ($message==='contribution_description')
		{
			$data=['id'=>$this->contribution->db_id, 'group'=>$this->contribution->id_group, 'description'=>$this->contribution->value('description')];
			$template=Template_composed_xml::with_content($data);
			return $template;
		}
	}
}

class InputSet_user_api extends InputSet_complex
{
	use Multistage_input;
	
	const
		STAGE_INIT=0,
		STAGE_CONTRIBUTION_DESCRIPTION=1;
	
	public
		$source_setting=InputSet::SOURCE_GET_POST,
		$model_stages=
		[
			InputSet_user_api::STAGE_CONTRIBUTION_DESCRIPTION=>['id', 'group']
		],
		$model_stage=InputSet_user_api::STAGE_INIT,
		$input_fields=['message', 'request_code'],
		$api_model=
		[
			'id'=>
			[
				'type'=>'unsigned_int'
			],
			'group'=>
			[
				'type'=>'keyword'
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
			if ($this->content_of('message')==='contribution_description') return $this->change_model_stage(static::STAGE_CONTRIBUTION_DESCRIPTION);
		}
		return false;
	}
}
?>