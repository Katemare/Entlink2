<?
// эти классы пока описывают метки, заданные модераторами. облако пользовательских меток требует другой системы.

class Tag extends EntityType
{
	static
		$init=false,
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'=>'Tag_basic',
			'contribution'=>'Contribution_identity'
		],
		$default_table='info_tags';
}

class Tag_basic extends Aspect
{
	static
		$common_model=
		[
			'slug'=>
			[
				'type'=>'keyword'
			],
			'scope'=>
			[
				'type'=>'title'
			]
		],
		$init=false,
		$default_table='info_tags';
}
?>