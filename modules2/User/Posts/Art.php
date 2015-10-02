<?
namespace Pokeliga\User;

class Art extends Post
{
	static
		$init=false,
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'		=>'Post_basic',
			'identity'	=>'Contribution_identity',
			'post_type'	=>'Post_type_art'
		];
}

class Post_type_art extends Post_type_specific
{
	const
		MODEL_MODIFIED=__CLASS__;
		
	static
		$common_model=null,
		$modify_model=
		[
			'legacy_id'=>
			[
				'type'=>'unsigned_int',
				'null'=>true,
				'auto_valid'=>[null],
				'default'=>null
			],
			'file'=>
			[
				'type'=>'entity',
				'id_group'=>'Image'
			],
			'tagspace_links'=>
			[
				'type'=>'linkset',
				'select'=>'generic_linked',
				'position'=>Request_generic_links::FROM_SUBJECT,
				'opposite_id_group'=>['Species', 'AdoptsSpecies', 'Pokemon'],
				'relation'=>'pictures'
			]
		],
		$init=false,
		$default_table='posts_art';
}

?>