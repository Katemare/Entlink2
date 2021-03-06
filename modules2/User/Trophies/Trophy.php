<?
namespace Pokeliga\User;

class Trophy extends EntityType
{
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'			=>'Trophy_basic'
		],
		$default_table='user_trophies';
}

class Trophy_basic extends Aspect
{
	static
		$common_model=
		[
			'blueprint'=>
			[
				'type'=>'entity',
				'id_group'=>'TrophyBlueprint',
				'import'=>['public', 'trophy_type']
			],
			'owner'=>
			[
				'type'=>'entity',
				'id_group'=>'User',
			],
			'date_received'=>
			[
				'type'=>'timestamp'
			],
			'stashed'=>
			[
				'type'=>'bool',
				'default'=>false
			],
			'order'=>
			[
				'type'=>'unsigned_int',
				'null'=>true,
				'default'=>null,
				'auto_valid'=>[null]
			],
			'level'=>
			[
				'type'=>'unsigned_int',
				'null'=>true,
				'default'=>null,
				'auto_valid'=>[null]
			],
			'for'=>
			[
				'type'=>'id_and_group',
				'null'=>true,
				'default'=>null,
				'auto_valid'=>[null]
			]
		],
		$init=false,
		$basic=true,
		$default_table='user_trophies';
}
?>