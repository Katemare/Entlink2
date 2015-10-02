<?
namespace Pokeliga\User;

class Series extends EntityType
{
	static
		$init=false,
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'		=>'Series_basic',
			'identity'	=>'Contribution_identity'
		],
		$default_table='posts_series';
}

class Series_basic extends Aspect
{
	static
		$common_model=
		[
			'series_type'=>
			[
				'type'=>'keyword',
				'default'=>'Series'
			],
			'members'=>
			[
				'type'=>'linkset',
				'id_group'=>'SeriesMember',
				'select'=>'backlinked',
				'backlink_field'=>'series',
			],
			'owner'=>
			[
				// текущей владелец (админ) серии; не обязательно тот же, кто её создал.
				'type'=>'entity',
				'id_group'=>'User'
			]
		],
		$tasks=
		[
			'fits'=>'Task_series_member_fits'
		],
		$templates=
		[
		],
		$init=false,
		$basic=true,
		$default_table='posts_series';
}

?>