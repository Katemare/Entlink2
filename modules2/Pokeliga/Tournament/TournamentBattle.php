<?
namespace Pokeliga\Pokeliga;

class TournamentBattle extends EntityType
{		
	const
		STATE_UNANNOUNCED=0,
		STATE_ANNOUNCED=1,
		STATE_CURRENT=2,
		STATE_FINISHED=3,
		STATE_RESOLVED=4;

	static
		$init=false,
		$module_slug='pokeliga',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'=>'TournamentBattle_basic',
			'contribution'=>'Contribution_identity'
		],
		$default_table='tournaments_battles';
}

class TournamentBattle_basic extends Aspect
{
	static
		$common_model=
		[
			'tournament'=>
			[
				'type'=>'entity',
				'id_group'=>'Tournament'
			],
			'index'=>
			[
				'type'=>'unsigned_int'
			],
			'state'=>
			[
				'type'=>'enum',
				'options'=>[ TournamentBattle::STATE_UNANNOUNCED, TournamentBattle::STATE_ANNOUNCED, TournamentBattle::STATE_CURRENT, TournamentBattle::STATE_FINISHED, TournamentBattle::STATE_RESOLVED ],
				'default'=>TournamentBattle::STATE_ANNOUNCED
			],
			'summary'=>
			[
				'type'=>'html',
				'null'=>true,
				'default'=>null,
			],
			'resolution'=>
			[
				'type'=>'text',
				'null'=>true,
				'default'=>null
			],
			'resolution_data'=>
			[
				'type'=>'serialized_array',
				'null'=>true,
				'default'=>null
			],
			'chars'=>
			[
				'type'=>'linkset',
				'id_group'=>'TournamentChar',
				'select'=>'backlinked',
				'table'=>'tournaments_entries',
				'select_id_field'=>'char',
				'backlink_field'=>'battle'
			],
			'entries'=>
			[
				'type'=>'linkset',
				'id_group'=>'TournamentEntry',
				'select'=>'backlinked',
				'table'=>'tournaments_entries',
				'backlink_field'=>'battle'
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
		$default_table='tournaments_battles';
}
?>