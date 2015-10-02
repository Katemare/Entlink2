<?
namespace Pokeliga\User;

// нет формы новой серии и редактирования серии потому, что обычно применяются дочерние типы (Dex, Collection и так далее), и удобнее унаследовать базовую форму от Form_series, чем две - от Form_series_new и Form_series_edit.

class Form_series extends Form_entity
{
	static
		$basic_model=
		[
			'title'=>
			[
				'type'=>'title',
				'template'=>'input',
				'for_entity'=>true
			],
			'description'=>
			[
				'type'=>'text',
				'template'=>'text',
				'for_entity'=>true
			]
		];
	
	public
		$source_setting=InputSet::SOURCE_POST,
		$id_group='Series';
}

?>