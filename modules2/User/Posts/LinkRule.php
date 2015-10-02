<?
namespace Pokeliga\User;

/*
эта сущность описывает правило добавления связи к посту (Post), включая возможность такого добавления (задаётся ссылками на LinkRule в PostPool), средства редактирования и демонстрации. описываться может как связь GenericLink, так и другие сущности, которые можно рассматривать как связи: например, эволюция покемона, инвентарь игрока. не стоит использовать только для связей один-в-один, потому что их легче просто представить полем типа 'id'.

сущности LinkRule не соответствуют 1:1 одному отношению (relation)! например, отношение "участие" (contains) между работами (Post) и всем остальным в зависимости от того LinkRule, которым оно представлено в разделе, будет называться "изображены" (фанарт), "участвуют" (фанфики) или "тема" (руководства). таким образом одному и тому же кодовому слову отношения может соответствовать много разных LinkRule.

несколько разных LinkRule могут соответствовать одному типу связи. например, сущность Evolution может соединять более двух сущностей: исходный покемон, целевой покемон и предмет, необходимый для эволюции. LinkRule может описывать связь исходный-целевой покемон; или связь исходный покемон - предмет (равно как и другие комбинации, если они имеют смысл). Тем не менее, обычно понятие _открытого_ списка разных типов связей имеет смысл только для связей общего характера, таких как GenericLink и PostUserlink.

также эта сущность помогает разбирать открытый набор связей, имеющий разные отношения (в отличие от закрытого, однородного набора связей).
*/

class LinkRule extends EntityType
{
	const
		// эти константы используются в конкретных случаях набора ссылок на работу. указывает, работа является объектом (первой сущностью в связи) или же субъектом (второй сущностью в связи).
		POV_OBJECT	=1,
		POV_SUBJECT	=2;
		
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'		=>'LinkRule_basic'
		],
		$default_table='posts_link_rules';
}

class LinkRule_basic extends Aspect
{
	static
		$common_model=
		[
			// эти поля касаются описания модели связи.
			'link_type'=>
			[
				// класс, описывающий связь. это может быть как GenericLink согласно таблице info_links, так и другие сущности, связывающие две сущности - например, PostUserlink (работу с пользователем), Inventory (игрока с предметом), Evolution (два вида покемонов).
				'type'=>'keyword',
				'default'=>'GenericLink'
			],
			'link_model_key'=>
			[
				// ключ в таблице $link_data класса, названного в link_type.
				'type'=>'keyword',
				'default'=>'basic'
			],
			'link_model'=>
			[
				'type'=>'array',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['link_type', 'link_model_key'],
				'call'=>['_aspect', 'basic', 'link_model']
			],
			'object_field'=>
			[
				'type'=>'keyword',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['link_model'],
				'call'=>['_aspect', 'basic', 'object_field']
			],
			'subject_field'=>
			[
				'type'=>'keyword',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['link_model'],
				'call'=>['_aspect', 'basic', 'object_field']
			],
			'relation_field'=>
			[
				'type'=>'keyword',
				'null'=>true,
				'auto_valid'=>[null],
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['link_model'],
				'call'=>['_aspect', 'basic', 'object_field']
			],
			
			'relation'=>
			[
				// ключевое слово, обозначающее отношение. некоторые типы связей могут не иметь отношения (тогда по смыслу отношением является сам тип связи). для типов связей, предполагающих отношения, значение "null" означает "любое".
				'type'=>'keyword',
				'null'=>true,
				'auto_valid'=>null,
				'default'=>null
			],
			
			'slug'=>
			[
				'type'=>'keyword',
				'unique'=>true
			],
			'title_from_object'=>
			[
				// как это отношение называется с позиции объекта.
				'type'=>'title'
			],
			'title_from_subject'=>
			[
				// как это отношение называется с позиции субъекта.
				'type'=>'title'
			],
			'taglike'=>
			[
				'type'=>'bool',
				'default'=>false
			]
		],
		$init=false,
		$basic=true,
		$default_table='posts_link_rules';
		
	public function link_model()
	{
		$link_type=$this->entity->value('link_type');
		$key=$this->entity->value('link_model_key');
		return $link_type::$link_data[$key];
	}
	public function object_field()
	{
		$link_model=$this->entity->value('link_model');
		return $link_model['object'];
	}
	public function subject_field()
	{
		$link_model=$this->entity->value('link_model');
		return $link_model['subject'];
	}
	public function relation_field()
	{
		$link_model=$this->entity->value('link_model');
		if (!array_key_exists('relation', $link_model)) return null;
		return $link_model['relation'];
	}
}

?>