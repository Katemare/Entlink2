<?
namespace Pokeliga\User;

// эта сущность представляет собой подписку, заключающую в себе правила пополнения соответствующей ленты записями. например, подписка на новые работы фанарта, на любые работы с данной меткой, на случайную популярную работу в пределах 3 месяцев каждые 5 минут... многие пользователи могут быть подписаны на одну подписку, чтобы её содержимое кэшировалось и обновлялось по необходимости. сама подписка может быть создана заранее админами или по запросу пользователем. неиспользуемые подписки не обновляются.

class Feed extends EntityType
{
	const
		RULES_RECENT=1,
		RULES_RANDOM=2;
	
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'=>'Feed_basic',
			'rules'=>'Feed_rules'
		],
		$variant_aspects=
		[
			'rules'=>
			[
				'task_class'=>'Task_determine_aspect_by_param',
				'param'=>'rules_type',
				'by_value'=>
				[
					Feed::RULES_RECENT	=>'Feed_recent',
					Feed::RULES_RANDOM	=>'Feed_random',
				]
			]
		],
		$default_table='posts_feeds';
}

class Feed_basic extends Aspect
{
	static
		$common_model=
		[
			'rules_type'=>
			[
				'type'=>'enum',
				'options'=>[Feed::RULES_RECENT, Feed::RULES_RANDOM],
				'default'=>Feed::RULES_RECENT
			],
			'post_pool'=>
			[
				// конкретный подраздел или все.
				'type'=>'entity',
				'id_group'=>'PostPool',
				'null'=>true,
				'auto_valid'=>'null',
				'default'=>null
			],
			'section'=>
			[
				// конкретный раздел или все.
				'type'=>'entity',
				'id_group'=>'SiteSection',
				'null'=>true,
				'auto_valid'=>'null',
				'default'=>null
			],
			'tag'=>
			[
				// с конкретной меткой или все.
				'type'=>'entity',
				'id_group'=>'Tag',
				'null'=>true,
				'auto_valid'=>'null',
				'default'=>null
			],
			'linked_to'=>
			[
				// связанные с конкретной сущностью
				'type'=>'id_and_group',
				'null'=>true,
				'auto_valid'=>'null',
				'default'=>null
			],
			'relation'=>
			[
				// имеющие определённый тип связи.
				'type'=>'keyword',
				'null'=>true,
				'auto_valid'=>'null',
				'default'=>null
			],
			'author'=>
			[
				// конкретного автора или все.
				'type'=>'entity',
				'id_group'=>'User',
				'null'=>true,
				'auto_valid'=>'null',
				'default'=>null
			],
			'collection'=>
			[
				'type'=>'entity',
				'id_group'=>'Collection',
				'null'=>true,
				'auto_valid'=>'null',
				'default'=>null
			]
		],
		$init=false,
		$basic=true,
		$default_table='posts_feed';
}

class Feed_rules extends Aspect
{
	static
		$common_model=
		[
			'frequency'=>
			[
				'type'=>'unsigned_int'
			],
			'feed_hash'=>
			[
				'type'=>'keyword',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['rules_type', 'post_pool', 'section', 'tag', 'author'],
				'call'=>['_aspect', 'rules', 'rules_hash'],
			]
		],
		$templates=
		[
		],
		$init=false,
		$default_table='posts_feed';
	
	public function rules_hash()
	{
		$rules_type		=$this->entity->value('rules_type');
		$post_pool_id	=(int)$this->entity->value('post_pool');
		$section_id		=(int)$this->entity->value('section');
		$tag_id			=(int)$this->entity->value('tag');
		$author_id		=(int)$this->entity->value('author');
		
		return $rules_type.$post_pool_id.'_'.$section_id.'_'.$tag_id.'_'.$author_id;
	}
}

class Feed_recent extends Feed_rules
{
	const
		MODEL_MODIFIED=__CLASS__;
		
	static
		$common_model=null,
		$modify_model=
		[
			'frequency'=>
			[
				'type'=>'unsigned_int',
				'const'=>5
			],
			'event'=>
			[
				'type'=>'enum',
				'options'
			]
		],
		$init=false,
		$default_table='posts_feed';
}

class Feed_random extends Feed_rules
{
	const
		MODEL_MODIFIED=__CLASS__;
		
	static
		$common_model=null,
		$modify_model=
		[
			'timegap'=>
			[
				'type'=>'timeperiod',
				'null'=>true,
				'auto_valid'=>'null',
				'default'=>null
			],
			'popular'=>
			[
				'type'=>'bool',
				'default'=>true
			],
			'frequency'=>
			[
				'type'=>'unsigned_int',
				'validators'=>['valid_list'],
				'valid_list'=>[5, 10, 15, 20, 30, 60]
			],
			'feed_hash'=>
			[
				'type'=>'keyword',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['rules_type', 'post_pool', 'section', 'tag', 'author', 'timegap', 'popular'],
				'call'=>['_aspect', 'rules', 'rules_hash'],
			]
		],
		$init=false,
		$default_table='info_tags';
		
	public function rules_hash()
	{
		$hash=parent::rules_hash();
		$timegap	=$this->entity->value('timegap');
		$popular		=$this->entity->value('popular');
		
		return $hash.'_'.$timegap.( $popular ? 'P' : '' );
	}
}

?>