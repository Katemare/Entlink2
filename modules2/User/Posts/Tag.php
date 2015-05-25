<?
// эти классы пока описывают метки, заданные модераторами. облако пользовательских меток требует другой системы.

class Tag extends EntityType
{
	const
		PURPOSE_GENERAL		=1,	// может быть поставлена где угодно, кем угодно.
		PURPOSE_SPECIFIC	=2,	// может быть поставлена только в разделах, где она числится в affiliated, но кем угодно.
		PURPOSE_SPECIAL		=3,	// как предыдущий пункт, но может поставить только автор или модер. имеют специальный эффект и не могут быть удалены! потому что их айди забиты в константы для активации эффектов :(
		PURPOSE_TECHNICAL	=4, // как предыдущий пункт, но метка не отображается для пользователей, только при редактировании. страницы и элементы с демонстрацией сущности скорее всего показывают метку другим образом, например, как цвет рамки.
		PURPOSE_BANNED		=5; // эта метка запрещена к использованию как бесполезная или нецелевая.
	
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'			=>'Tag_basic',
			'contribution'	=>'Contribution_identity',
			'adopts'		=>'Tag_adopts' // FIX! должно добавляться модулем игры.
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
			'purpose'=>
			[
				'type'=>'enum',
				'options'=>[Tag::PURPOSE_GENERAL, Tag::PURPOSE_SPECIFIC, Tag::PURPOSE_SPECIAL, Tag::PURPOSE_TECHNICAL, Tag::PURPOSE_BANNED],
				'default'=>Tag::PURPOSE_GENERAL
			],
			'synonyms'=>
			[
				'type'=>'title_array',
				'keeper'=>'var_array',
				'unique'=>true
			]
		],
		$basic=true,
		$init=false,
		$default_table='info_tags';
}
?>