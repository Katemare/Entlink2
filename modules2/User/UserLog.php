<?
namespace Pokeliga\User;

// сведения о действиях пользователей в отношении других пользователей и работ.

class UserLog extends EntityType
{		
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'		=>'UserLog_basic',
			'by_action'	=>'UserLog_by_action'
		],
		$variant_aspects=>
		[
			'by_action'=>
			[
				'task_class'=>'Determine_aspect_by_param'
				'param'=>'action',
				'by_value'=>
				[
					'reject_post_userlink'=>'UserLog_about_PostUserlink',
					'accept_post_userlink'=>'UserLog_about_PostUserlink'
				],
				'default_aspect'=>'UserLog_standard'
			]
		],
		$default_table='info_userlog',
		
		$link_data=
		[
			'basic'=>
			[
				'object'	=>'user',
				'subject'	=>'subject',
				'relation'	=>'action'
			]
		];
}

class UserLog_basic extends Aspect
{
	static
		$common_model=
		[
			'user'=>
			[
				'type'=>'entity',
				'id_group'=>'User'
			],
			'action'=>
			[
				'type'=>'keyword'
			],
			'action_time'=>
			[
				'type'=>'timestamp'
			],
			'details'=>
			[
				'type'=>'array'
			]
		],
		$basic=true,
		$init=false,
		$default_table='info_userlog';
}

class UserLog_standard extends Aspect
{
	static
		$common_model=
		[
			'subject'=>
			[
				'type'=>'entity',
				'id_group'=>'User',
				'null'=>true,
				'auto_valid'=>[null],
				'default'=>null
			],
		],
		$init=false,
		$default_table='info_userlog';
}
 
// дорогая операция! по запросу на экземпляр (MySQL не умеет применять limit по группам). использовать только в модерации или в профиле.
class Provide_recent_user_log extends Provider
{
	public
		$conditions;
		
	public function setup_by_args($args)
	{
		$this->conditions=reset($args);
	}
	
	public function create_request()
	{
		$query=
		[
			'action'=>'select',
			'table'=>UserLog::$default_table,
			'where'=>$this->conditions,
			'order'=>[ ['action_time', 'dir'=>'DESC'] ],
			'limit'=>[0, 1]
		];
		return new Request_Ticket('Request_single', [$query]);
	}
}
}

?>