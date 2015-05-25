<?
class User extends EntityType
{
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'		=>'User_basic',
			'auth'		=>'User_auth',
			'author'	=>'User_contributor',
			'awards'	=>'User_awards',
			'player'	=>'User_player', // STUB: это должно подключаться модулем игры.
			'admin'		=>'User_admin'
		],
		$variant_aspects=['player'=>'Task_user_determine_player_aspect'], // STUB: это должно подключаться модулем игры.
		$default_table='userlist';
		
	// STUB
	public static function logged_in()
	{
		return static::current_user_id()>0;
	}
	
	// STUB
	public static function current_user_id()
	{
		global $USid;
		return $USid;
	}
	
	static $current_user=false;
	public static function current_user($pool=null)
	{
		if (static::$current_user!==false) return static::$current_user;
		
		if (!static::logged_in()) $result=null;		
		else
		{		
			if ($pool===null) $pool=EntityPool::default_pool(); // FIX! необходимо предусмотреть возможность клонирования пула.	
			$result=$pool->entity_from_db_id(static::current_user_id(), 'User');
		}
		static::$current_user=$result;
		return $result;
	}
}

class User_basic extends Aspect
{
	static
		$common_model=
		[
			'login'=>
			[
				'type'=>'title',
				'unique'=>true,
				'field'=>'uslogin'
			],
			'title'=>
			[
				'type'=>'title',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['login'],
				'call'=>['_aspect', 'basic', 'title'],
			],
			'nickname'=>
			[
				'type'=>'string',
				'unique'=>true
			],
			
			'profile_url'=>
			[
				'type'=>'url',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['login'],
				'call'=>['_aspect', 'basic', 'profile_url'],
			]
		],
		$tasks=
		[
			'check_right'=>'Task_user_right'
		],
		$templates=
		[
			'link'=>'#standard.linked_title'
		],
		$init=false,
		$basic=true,
		$default_table='userlist';
	
	public function profile_url()
	{
		return 'http://pokeliga.com/users/profile.php?user='.urlencode($this->entity->value('login'));
	}
	
	public function title()
	{
		return $this->entity->value('login');
	}
}

class User_auth extends Aspect
{
}

class User_contributor extends Aspect
{
	const
		MAX_SUGGESTED_MISSIONS=10;
	
	static
		$common_model=
		[
			'suggest_missions_right'=>
			[
				'type'=>'bool',
				'keeper'=>'var',
				'default'=>true
			],
			'contributed_missions'=>
			[
				'type'=>'linkset',
				'id_group'=>'Mission',
				'select'=>'backlinked',
				'backlink_field'=>'contributor',
				'select_table'=>'contributions',
				'select_conditions'=>['id_group'=>'Mission']
				// FIX! подобные поля - первый кандидат на использование EntityQuery вместо Query.
			],
			'suggested_missions'=>
			[
				'type'=>'linkset',
				'id_group'=>'Mission',
				'select'=>'backlinked',
				'backlink_field'=>'contributor',
				'select_table'=>'contributions',
				'select_conditions'=>
				[
					'id_group'=>'Mission',
					'moderated'=>[Post::MOD_WIP, Post::MOD_STABILIZED, Post::MOD_REJECTED]
				]
			],
			'approved_missions'=>
			[
				'type'=>'linkset',
				'id_group'=>'Mission',
				'select'=>'backlinked',
				'backlink_field'=>'contributor',
				'select_table'=>'contributions',
				'select_conditions'=>
				[
					'id_group'=>'Mission',
					'moderated'=>[Post::MOD_APPROVED, Post::MOD_POLISH, Post::MOD_AUTO_APPROVED]
				]
			],
			'can_suggest_missions'=>
			[
				'type'=>'bool',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['suggested_missions', 'suggest_missions_right'],
				'call'=>['_aspect', 'author', 'can_suggest_missions']
			],
		],
		$init=false,
		$default_table='userlist';
		
	public function can_suggest_missions()
	{
		if ($this->entity->value('suggest_missions_right')!==true) return false;
		if ($this->entity->value('suggested_missions')->value('count')>=static::MAX_SUGGESTED_MISSIONS) return false;
		return true;
	}
}

class User_admin extends Aspect
{
	static
		$common_model=
		[
			'admin'=>
			[
				'type'=>'bool',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['login'],
				'dependancies'=>['login'],
				'call'=>['_aspect', 'admin', 'is_admin']
			]
		],
		$init=false,
		$default_table='user_privilege';
		
	// STUB
	public function is_admin()
	{
		$login=$this->entity->value('login');
		return in_array($login, Module_AdoptsGame::$divan, true);
	}
}

class Task_user_right extends Task_for_entity
{
	public
		$rights,
		$on_true, 	// если хотя бы одно истинно. чтобы, например, проверять сразу наличие любого из прав: 'edit', 'admin'.
		$on_false, 	// если все ложны.
		$results=[],
		$requested=false;
		
	public function apply_arguments()
	{
		$this->rights=reset($this->args);
		if (!is_array($this->rights)) $this->rights=[$this->rights];
		$this->on_true=next($this->args);
		$this->on_false=next($this->args);
	}
	
	public function progress()
	{
		$tasks=[];
		
		$allow=null;
		foreach ($this->rights as $right)
		{
			if (array_key_exists($right, $this->results)) $result=&$this->results[$right];
			else
			{
				$report=$this->entity->request($right);
				if ($report instanceof Report_task) $result=$report->task;
				elseif ($report instanceof Report_tasks) die('BAD USER RIGHT REPORT');
				elseif ($report instanceof Report_resolution) $result=$report->resolution;
				elseif ($report instanceof Report_impossible) $result=false;
				$this->results[$right]=&$result;
			}
			
			if ($result instanceof Task)
			{
				if ($result->failed()) $result=false;
				elseif ($result->successful()) $result=$result->resolution;
				else $tasks[]=$result;
			}
			if ($result===true) $allow=true;
		}
		
		if ($allow===true) $this->finish_with_resolution($this->on_true);
		elseif (!empty($tasks)) $this->register_dependancies($tasks);
		else $this->finish_with_resolution($this->on_false);
	}
}

class Provide_user_by_login extends Provide_by_unique_field_case_insensitive
{
	public
		$table='userlist',
		$field='uslogin';
		
	public function setup_by_args($args)
	{
		$this->value_content=$args[0];
	}
}
?>