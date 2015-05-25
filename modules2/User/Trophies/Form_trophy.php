<?

abstract class Form_trophy_blueprint extends Form_entity
{
	static
		$basic_model=
		[
			'id'=>
			[
				'type'=>'id',
				'id_group'=>'TrophyBlueprint',
				'template'=>'hidden',
				'for_entity'=>true,
			],
			'title'=>
			[
				'type'=>'title',
				'template'=>'string',
				'for_entity'=>true
			],
			'annotation'=>
			[
				'type'=>'text',
				'template'=>'textarea',
				'for_entity'=>true
			],
			'description'=>
			[
				'type'=>'text',
				'template'=>'textarea',
				'for_entity'=>true
			],
			'cover_image'=>
			[
				'type'=>'id',
				'id_group'=>'Image',
				'template'=>'select',
				'null'=>true,
				'default'=>null,
				'auto_valid'=>[null],
				'replace'=>[0=>null, ''=>null],
				'for_entity'=>true
			],
			'icon'=>
			[
				'type'=>'id',
				'id_group'=>'Image',
				'template'=>'select',
				'for_entity'=>true
			],
			'trophy_type'=>
			[
				'type'=>'enum',
				'options'=>
				[
					TrophyBlueprint::TYPE_SINGLE,	TrophyBlueprint::TYPE_CUMULATIVE,
					TrophyBlueprint::TYPE_RATING, 	TrophyBlueprint::TYPE_RATING_LEVEL
				],
				'template'=>'radio',
				'default'=>TrophyBlueprint::TYPE_SINGLE,
				'for_entity'=>true
			],
			'public'=>
			[
				'type'=>'bool',
				'template'=>'checkbox',
				'default_for_display'=>true,
				'for_entity'=>true
			],
			'adopts_related'=>
			[
				'type'=>'bool',
				'template'=>'checkbox',
				'default_for_display'=>false,
				'for_entity'=>true
			],
			'accessory'=>
			[
				'type'=>'bool',
				'template'=>'checkbox',
				'default_for_display'=>false,
				'for_entity'=>true
			],
			'submit'=>
			[
				'type'=>'keyword',
				'replace'=>[''=>'save'],
				'template'=>'submit'
			]
		];

	public
		$source_setting=InputSet::SOURCE_POST,
		$id_group='TrophyBlueprint',
		$target_page='admin_view_page';
	
	public function rightful()
	{
		return Module_AdoptsGame::instance()->admin();
	}
}

class Form_trophy_blueprint_new extends Form_trophy_blueprint
{
	use Form_new_entity, Contribution_signer;
	
	public function supply_model()
	{
		parent::supply_model();
		unset($this->model['id']);
	}
	
	public function prepare_entity($entity)
	{
		$entity=parent::prepare_entity($entity);
		$this->sign_new_contribution($entity);
		return $entity;
	}
}

class Form_trophy_blueprint_edit extends Form_trophy_blueprint
{
	use Form_edit_entity_simple, Multistage_input
	{
		Form_edit_entity_simple::create_valid_processor as std_create_valid_processor;
	}

	const
		STAGE_INIT=0,
		STAGE_DELETE=1,
		STAGE_EDIT=2;
	
	static
		$modify_model=
		[
			'yes'=>
			[
				'type'=>'bool',
				'template'=>'checkbox',
				'default_for_display'=>false,
				'auto_invalid'=>[false]
			],
			'delete'=>
			[
				'type'=>'bool',
				'template'=>'checkbox',
				'default_for_display'=>false
			],
			'delete2'=>
			[
				'type'=>'bool',
				'template'=>'checkbox',
				'default_for_display'=>false,
				'auto_invalid'=>[false]
			]
		];
	
	public
		$slug='trophy_edit',
		$content_db_key='adopts.form_edit_mission',
		$model_stages=
		[
			self::STAGE_INIT	=>['submit'],
			self::STAGE_DELETE	=>['delete', 'delete2'],
			self::STAGE_EDIT	=>['yes', 'id', 'title', 'annotation', 'description', 'cover_image', 'icon', 'public', 'adopts_related', 'accessory']
		],
		$model_stage=self::STAGE_INIT,
		$input_fields=['delete'];
		
	public function supply_model()
	{
		parent::supply_model();
		$this->model=array_merge($this->model, self::$modify_model);
	}
	
	public function update_subfields()
	{
		if ($this->model_stage===static::STAGE_INIT)
		{
			if ($this->content_of('submit')==='delete') return $this->change_model_stage(static::STAGE_DELETE);
			else return $this->change_model_stage(static::STAGE_EDIT);
		}
		return false;
	}
	
	public function create_valid_processor()
	{
		if ($this->model_stage===static::STAGE_DELETE)
		{
			// перестраховка.
			if ($this->content_of('delete')!==true) return $this->sign_report(new Report_impossible('delete_not_approved'));
			if ($this->content_of('delete2')!==true) return $this->sign_report(new Report_impossible('delete_not_approved'));
			
			if ($this->entity()->my_right('delete')!==true) return $this->sign_report(new Report_impossible('no_delete_right'));
			
			if (!$this->entity()->exists())
				Router()->redirect(Router()->url('adopts/trophies_blueprints.php'));
				//return $this->sign_report(new Report_impossible('doesnt_exist')); 
				
			$queries=$this->delete_queries();
			foreach ($queries as $query)
			{
				$result=Retriever()->run_query($query);
				if ($result instanceof Report) return $result;
			}			
			Router()->redirect(Router()->url('adopts/trophies_blueprints.php'));
			
			// до этой строчки не должно дойти.
			return $this->sign_report(new Report_success());
		}
		else return $this->std_create_valid_processor();
	}
	
	public function delete_queries()
	{
		$queries=[];
		
		$queries[]=
		[
			'action'=>'delete',
			'table'=>'info_trophies',
			'where'=>['id'=>$this->entity()->db_id]
		];
		
		return $queries;
	}
}

?>