<?

// техническая группа меток, которая может применяться в разных местах сайта.

class TagGroup extends EntityGroup
{
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'=>'TagGroup_basic',
			'identity'=>'Contribution_identity',
		];
}

class TagGroup_basic extends Aspect
{
	const
		MODEL_MODIFIED=__CLASS__;
	
	static
		$common_model=null,
		$modify_model=
		[
			'group_type'=>
			[
				'type'=>'keyword',
				'const'=>'TagGroup' // можно так сделать, поскольку не предполагается, что группа меток превратится в какую-то другую.
			],
			'entities'=>
			[
				'type'=>'linkset',
				'id_group'=>'Tag',
				'select'=>'generic_linked',
				'position'=>Request_generic_links::FROM_OBJECT,
				'opposite_id_group'=>'EntityGroup',
				'relation'=>'contains'
			],
		],
		$init=false,
		$basic=true,
		$default_table='entities_groups';
}

?>