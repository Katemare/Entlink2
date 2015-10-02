<?
namespace Pokeliga\Pokeliga;

class TournamentChar extends EntityType
{		
	static
		$init=false,
		$module_slug='pokeliga',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'=>'TournamentChar_basic',
			'contribution'=>'Contribution_identity'
		],
		$default_table='tournaments_chars';
}

class TournamentChar_basic extends Aspect
{
	static
		$common_model=
		[
			'owner'=>
			[
				// не всегда совпадает с контрибутором, например, если админ создал "ничейного" персонажа или за какого-нибудь игрока.
				'type'=>'entity',
				'id_group'=>'User',
				'null'=>true,
				'default'=>null
			],
			'tournaments'=>
			[
				'type'=>'linkset',
				'id_group'=>'Tournament',
				'select'=>'backlinked',
				'table'=>'tournaments_entries',
				'backlink_field'=>'char',
				'select_conditions'=>[ ['field'=>'tournament', 'op'=>'not_null'] ]
			],
			'battles'=>
			[
				'type'=>'linkset',
				'id_group'=>'Tournament',
				'select'=>'backlinked',
				'table'=>'tournaments_entries',
				'backlink_field'=>'char',
				'select_conditions'=>[ ['field'=>'battle', 'op'=>'not_null'] ]
			]
		],
		$templates=
		[
		],
		$tasks=
		[
		],
		$init=false,
		$basic=true,
		$default_table='tournaments_chars';
}
?>