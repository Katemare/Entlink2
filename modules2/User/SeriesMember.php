<?
namespace Pokeliga\User;

class SeriesMember extends EntityType
{
	const
		EDIT_FORM_CLASS='replace_me',
		DELETE_FORM_CLASS='replace_me';
		
	static
		$init=false,
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'		=>'SeriesMember_basic',
			'identity'	=>'Contribution_identity'
		],
		$default_table='posts_series_members';
}

class SeriesMember_basic extends Aspect
{	
	static
		$common_model=
		[
			'membership_type'=>
			[
				'type'=>'keyword',
				'default'=>'SeriesMember'
			],
			'series'=>
			[
				'type'=>'entity',
				'id_group'=>'Series'
			],
			'member'=>
			[
				// в серии могут быть разнородные элементы, даже другие серии!
				'type'=>'id_and_group'
			],
			'order'=>
			[
				'type'=>'float',
				'default'=>100
			],
			'member_code'=>
			[
				'type'=>'title',
				'null'=>true,
				'default'=>null,
				'auto_valid'=>[null]
			],
			'show_code'=>
			[
				// позволяет не отображать код вообще.
				'type'=>'bool',
				'default'=>true
			],
			'display_code'=>
			[
				'type'=>'title',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['member_code', 'no_code', 'order'],
				'call'=>['_aspect', 'basic', 'display_code'],
			],
		],
		$tasks=
		[
		],
		$templates=
		[
			'edit_form'=>true,
			'delete_form'=>true
		],
		$init=false,
		$basic=true,
		$default_table='posts_series_members';
		
	public function display_code()
	{
		if ($this->entity->value('show_code')===false) return '';
		$member_code=$this->entity->value('member_code');
		if (is_string($member_code)) return $member_code;
		return $this->entity->value('order');
	}
	
	public function make_complex_template($name, $line=[], &$do_setup=true)
	{
		if ($name==='edit_form')
		{
			$type=$this->entity->type;
			$class=$type::EDIT_FORM_CLASS;
			$form=$class::create_for_display();
			$form->entity=$this->entity;
			$template=$form->main_template($line);
			return $template;
		}
		elseif ($name==='delete_form')
		{
			$type=$this->entity->type;
			$class=$type::DELETE_FORM_CLASS;
			$form=$class::create_for_display();
			$form->entity=$this->entity;
			$template=$form->main_template($line);
			return $template;
		}
	}
}

?>