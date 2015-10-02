<?
namespace Pokeliga\User;

// связи работ с пользователям нужны особые функции, например, потверждение упомянутым пользователем.

class PostUserlink extends EntityType
{
	const
		STATUS_SUGGESTED=1,
		STATUS_ACCEPTED=2,
		STATUS_REJECTED=3;
		
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'		=>'PostUserlink_basic',
			'by_relation'	=>'PostUsetlink_by_relation'
		],
		$default_table='posts_userlinks',
		
		$link_data=
		[
			'basic'=>
			[
				'object'	=>'post',
				'subject'	=>'user',
				'relation'	=>'relation'
			]
		];
}

class PostUserlink_basic extends Aspect
{
	static
		$common_model=
		[
			'post'=>
			[
				'type'=>'entity',
				'id_group'=>'Post'
			],
			'user'=>
			[
				'type'=>'entity',
				'id_group'=>'User'
			],
			'relation'=>
			[
				'type'=>'keyword'
			],
			'resolution'=>
			[
				'type'=>'enum',
				'options'=>[PostUserlink::STATUS_SUGGESTED, PostUserlink::STATUS_ACCEPTED, PostUserlink::STATUS_REJECTED]
				'default'=>PostUserlink::STATUS_SUGGESTED
			],
			
			'creation_log'=>
			[
				'type'=>'entity',
				'id_group'=>'UserLog',
				'null'=>true,
				'auto_valid'=>null,
				'default'=>null,
				'auto'=>'Fill_entity_by_provider',
				'pre_request'=>['id'],
				'call'=>['_aspect', 'basic', 'creation_log_provider_data'],
				'pathway_track'=>true,
				'import'=>['action_time'=>'set_date', 'user'=>'set_by']
			]
			'resolution_log'=>
			[
				'type'=>'entity',
				'id_group'=>'UserLog',
				'null'=>true,
				'auto_valid'=>null,
				'default'=>null,
				'auto'=>'Fill_entity_by_provider',
				'pre_request'=>['id'],
				'call'=>['_aspect', 'basic', 'resolution_log_provider_data'],
				'pathway_track'=>true,
				'import'=>['action_time'=>'resolution_date', 'user'=>'resolved_by']
			]
		],
		$basic=true,
		$init=false,
		$default_table='posts';
		
	public function resolution_log_provider_data()
	{
		return ['recent_user_log', ['action'=>['reject_post_userlink', 'accept_post_userlink'], 'subject'=>$this->entity->value('id')];
	}
	
	public function creation_log_provider_data()
	{
		return ['recent_user_log', ['action'=>'create_post_userlink', 'subject'=>$this->entity->value('id')];
	}
}

class UserLog_about_PostUserlink extends UserLog_standard
{
	const
		MODEL_MODIFIED=__CLASS__;

	static
		$common_model=null,
		$modify_model=
		[
			'subject'=>
			[
				'type'=>'entity',
				'id_group'=>'PostUserlink'
			],
		],
		$init=false;
}
?>