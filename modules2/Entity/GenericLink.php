<?
namespace Pokeliga\Entity;

// WIP: здесь будет сущность, соответствующая записям в таблице info_links.

class GenericLink extends EntityType
{
	static
		$init=false,
		$module_slug='entity',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'		=>'GenericLink_basic',
		],
		$default_table='info_links',
		
		$link_data=
		[
			'basic'=>
			[
				'object'	=>'object',
				'subject'	=>'subject',
				'relation'	=>'relation'
			]
		];
}

class GenericLink_basic extends Aspect
{
	static
		$common_model=
		[
			'object'=>
			[
				'type'			=>'id_and_group',
				'field'			=>'entity1_id',
				'id_group_field'=>'entity1_group'
			],
			'subject'=>
			[
				'type'			=>'id_and_group',
				'field'			=>'entity2_id',
				'id_group_field'=>'entity2_group'
			],
			'relation'=>
			[
				'type'=>'keyword',
			],
		],
		$init=false,
		$basic=true,
		$default_table='info_links';
}

?>