<?
namespace Pokeliga\Pokeliga;

class TournamentEntry extends EntityType
{
	const
		REVIEW_UNREVIEWED=0,
		REVIEW_APPROVED=1,
		REVIEW_DENIED=2,
		REVIEW_EDIT=3;

	static
		$init=false,
		$module_slug='pokeliga',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'=>'TournamentEntry_basic'
		],
		$default_table='tournaments_entries';
}

class TournamentEntry_basic extends Aspect
{
	static
		$common_model=
		[
			'char'=>
			[
				'type'=>'entity',
				'id_group'=>'TournamentChar'
			],
			
			// у записи должен быть установлен либо турнир, либо битва. в зависимости от этого, выполняется роль заявки натурнир или участника битвы соответственно.
			'tournament'=>
			[
				'type'=>'entity',
				'id_group'=>'Tournament',
				'null'=>true,
				'default'=>null
			],
			'battle'=>
			[
				'type'=>'entity',
				'id_group'=>'TournamentBattle',
				'null'=>true,
				'default'=>null
			],
			'reviewed'=>
			[
				'type'=>'enum',
				'options'=>[ TournamentEntry::REVIEW_UNREVIEWED, TournamentEntry::REVIEW_APPROVED, TournamentEntry::REVIEW_DENIED ],
				'default'=>TournamentEntry::REVIEW_APPROVED
			],
			'ord'=>
			[
				// в заявке на турнир выполняет роль порядка, а в участке боя - стороны.
				'type'=>'unsigned_int',
				'default'=>0
			],
			'place'=>
			[
				'type'=>'unsigned_int',
				'null'=>true,
				'default'=>null
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
		$default_table='tournaments_entries';
}
?>