<?
namespace Pokeliga\User;

// Описывает раздел сайта, задавя права различных пользователей относительно действий.

class UserLevel extends EntityType
{
	const
		INIT=0,
		BANNED=-1,
		PERSON=1,
		WATCHER=5,
		MODER=10,
		TRUSTED=20;
	
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'		=>'UserLeveL_basic'
		],
		$default_table='users_privilege';
}

class UserLeveL_basic extends Aspect
{
	static
		$common_model=
		[
			'user'=>
			[
				'type'=>'entity',
				'id_group'=>'User'
			],
			'section'=>
			[
				'type'=>'entity',
				'id_group'=>'SiteSection'
			],
			'level'=>
			[
				'type'=>'int',
				'default'=>UserLevel::INIT
			],
			'description'=>
			[
				'type'=>'title',
				'null'=>true,
				'auto_valid'=>[null],
				'default'=>null,
			],
			
			'assign_log'=>
			[
				'type'=>'entity',
				'id_group'=>'UserLog',
				'null'=>true,
				'auto_valid'=>null,
				'default'=>null,
				'auto'=>'Fill_entity_by_provider',
				'pre_request'=>['user'],
				'call'=>['_aspect', 'basic', 'update_log_provider_data'],
				'pathway_track'=>true,
				'import'=>['action_time'=>'update_date', 'user'=>'updated_by']
			],
			'describe_log'=>
			[
				'type'=>'entity',
				'id_group'=>'UserLog',
				'null'=>true,
				'auto_valid'=>null,
				'default'=>null,
				'auto'=>'Fill_entity_by_provider',
				'pre_request'=>['user'],
				'call'=>['_aspect', 'basic', 'describe_log_provider_data'],
				'pathway_track'=>true,
				'import'=>['action_time'=>'update_date', 'user'=>'updated_by']
			],
		],
		$basic=true,
		$init=false,
		$default_table='users_privilege';
		
	public function update_log_provider_data()
	{
		return ['recent_user_log', ['action'=>'assign_user_level', 'subject'=>$this->entity->value('user')];
	}
	public function describe_log_provider_data()
	{
		return ['recent_user_log', ['action'=>['assign_user_level', 'describe_user_level'], 'subject'=>$this->entity->value('user')];
	}
}

?>