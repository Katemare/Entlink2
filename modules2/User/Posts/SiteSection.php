<?
namespace Pokeliga\User;

// Описывает раздел сайта, задавя права различных пользователей относительно действий.

class SiteSection extends EntityType
{		
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'		=>'SiteSection_basic',
			'contribution'=>'Contribution_identity'
		],
		$default_table='info_sections';
}

class SiteSection_basic extends Aspect
{
	static
		$common_model=
		[
			'post_types'=>
			[
				'type'=>'keyword_array',
				'keeper'=>'var_array'
			],
			'moders'=>
			[
				'type'=>'linkset',
				'id_group'=>'User',
				'select'=>'backlinked',
				'backlink_field'=>'section',
				'select_id_field'=>'user',
				'select_conditions'=>
				[
					['field'=>'level', 'op'=>'>=', 'value'=>UserLevel::MODER]
				]
			],
			'watchers'=>
			[
				'type'=>'linkset',
				'id_group'=>'User',
				'select'=>'backlinked',
				'backlink_field'=>'section',
				'select_id_field'=>'user',
				'select_conditions'=>
				[
					['field'=>'level', 'op'=>'>=', 'value'=>UserLevel::WATCHER]
				]
			],
			'banned'=>
			[
				'type'=>'linkset',
				'id_group'=>'User',
				'select'=>'backlinked',
				'backlink_field'=>'section',
				'select_id_field'=>'user',
				'select_conditions'=>
				[
					['field'=>'level', 'op'=>'<=', 'value'=>UserLevel::BANNED]
				]
			],
		],
		$basic=true,
		$init=false,
		$default_table='posts';
}

?>