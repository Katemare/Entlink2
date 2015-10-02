<?
namespace Pokeliga\User;

// эта сущность описывает связь работы с меткой - будь то модераторская, пользовательская или иная (?).

class Tagged extends EntityType
{
	const
		TAG_OFFICIAL		=1,
		TAG_USER			=2,
		TAG_SUGGESTED_LINK	=3,
		
		SOURCE_CONTRIBUTOR	=1,
		SOURCE_MODERATOR	=2,
		SOURCE_VIEWER		=3,
		
		VISIBILITY_WEIGHT	=10;
	
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'=>'Tagged_basic',
			'specific'=>'Tagged_specific'
		],
		$variable_aspects=>
		[
			'specific'=>
			[
				'task_class'=>'Task_determine_aspect',
				'param'=>'tag_type',
				'by_value'=>
				[
					Tagged::TAG_OFFICIAL=>'Tagged_by_official',
					Tagged::TAG_USER	=>'Tagged_by_user',
					Tagged::TAG_SUGGESTED_LINK=>'Tagged_suggested_link'
				],
			]
		],
		$default_table='contributions_tagged';
}

class Tagged_basic extends Aspect
{
	static
		$common_model=
		[
			'contribution'=>
			[
				'type'=>'id_and_group'
			],
			'tag_type'=>
			[
				'type'=>'enum',
				'options'=>[Tagged::TAG_OFFICIAL, Tagged::TAG_USER, Tagged::TAG_SUGGESTED_LINK]
			],
			'weight'=>
			[
				'type'=>'unsigned_int',
				'default'=>0
			],
			'source'=>
			[
				'type'=>'enum',
				'options'=>[Tagged::SOURCE_CONTRIBUTOR, Tagged::SOURCE_MODERATOR, Tagged::SOURCE_VIEWER]
			],
			'source_user'=>
			[
				'type'=>'entity',
				'id_group'=>'User'
			],
			'visible'=>
			[
				'type'=>'bool',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['source', 'weight'],
				'call'=>['_aspect', 'basic', 'visible']
			],
			'votable'=>
			[
				'type'=>'bool',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['visible'],
				'call'=>['_aspect', 'basic', 'votable']			
			],
			'set_by'=>
			[
				'type'=>'linkset',
				'id_group'=>'User',
				'select'=>'generic_linked',
				'position'=>Request_generic_links::FROM_SUBJECT,
				'opposite_id_group'=>'Tagged',
				'relation'=>'set'
			],
			'voted_by'=>
			[
				'type'=>'linkset',
				'id_group'=>'User',
				'select'=>'generic_linked',
				'position'=>Request_generic_links::FROM_SUBJECT,
				'opposite_id_group'=>'Tagged',
				'relation'=>'vote'
			],
			'supported_by'=>
			[
				'type'=>'linkset',
				'id_group'=>'User',
				'select'=>'from_siblings',
				'sources'=>['set_by', 'voted_by']
			]			
		],
		$init=false,
		$basic=true,
		$default_table='info_tags';
	
	public function visible()
	{
		return in_array($this->entity->value('source'), [Tagged::SOURCE_CONTRIBUTOR, Tagged::SOURCE_MODERATOR], 1) or $this->entity->value('weight')>=Tagged::VISIBILITY_WEIGHT;
	}
	
	public function votable()
	{
		return $this->entity->value('visible');
	}
}

class Tagged_specific extends Aspect
{
	static
		$common_model=
		[
			'tag'=>
			[
				'type'=>'entity',
				'null'=>true,
				'const'=>null
			],
			'usertag'=>
			[
				'type'=>'title',
				'null'=>true,
				'const'=>null
			],
			'target'=>
			[
				'type'=>'id_and_group',
				'null'=>true,
				'const'=>null
			],
			'relation'=>
			[
				'type'=>'keyword',
				'null'=>true,
				'const'=>null
			]
		],
		$templates=
		[
			'display'=>true
		],
		$init=false,
		$default_table='info_tags';
}

class Tagged_by_official extends Tagged_specific
{
	const
		MODEL_MODIFIED=__CLASS__;
		
	static
		$common_model=null,
		$modify_model=
		[
			'tag'=>
			[
				'type'=>'entity',
				'id_group'=>'Tag'
			]
		],
		$templates=
		[
			'display'=>'#standard.official_tag'
		],
		$init=false,
		$default_table='info_tags';
}

class Tagged_by_user extends Tagged_specific
{
	const
		MODEL_MODIFIED=__CLASS__;
		
	static
		$common_model=null,
		$modify_model=
		[
			'usertag'=>
			[
				'type'=>'title'
			]
		],
		$templates=
		[
			'display'=>'#standard.user_tag'
		],
		$init=false,
		$default_table='info_tags';
}

class Tagged_suggested_link extends Tagged_specific
{
	const
		MODEL_MODIFIED=__CLASS__;
		
	static
		$common_model=null,
		$modify_model=
		[
			'target'=>
			[
				'type'=>'id_and_group'
			],
			'relation'=>
			[
				'type'=>'keyword'
			]
		],
		$templates=>
		[
			'display'=>'#standard.suggested_link_tag'
		],
		$init=false,
		$default_table='info_tags';
}
?>