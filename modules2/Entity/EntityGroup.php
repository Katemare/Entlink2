<?
namespace Pokeliga\Entity;

// эта сущность представляет собой подписку, заключающую в себе правила пополнения соответствующей ленты записями. например, подписка на новые работы фанарта, на любые работы с данной меткой, на случайную популярную работу в пределах 3 месяцев каждые 5 минут... многие пользователи могут быть подписаны на одну подписку, чтобы её содержимое кэшировалось и обновлялось по необходимости. сама подписка может быть создана заранее админами или по запросу пользователем. неиспользуемые подписки не обновляются.

class EntityGroup extends EntityType
{
	static
		$init=false,
		$module_slug='entity',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'=>'EntityGroup_basic',
			'identity'=>'Contribution_identity'
		],
		$default_table='entities_groups';
		
	public function resolve_type()
	{
		if (get_class($this)!==get_class()) return $this; // эта реализация только для данного класса, а не его наследников.
		
		$get_refined_type=function()
		{
			$group_type=$this->entity->value('group_type');
			return $this->refine_type($group_type);
		};
		$refine_type=function() use ($get_refined_type)
		{
			$new_type=$get_refined_type();
			$new_type->retype_entity();
		};
		if ($this->entity->is_to_verify())
		{
			$this->entity->add_call($refine_type, 'verified');
			return $this;
		}
		else return $get_refined_type();
	}
}

class EntityGroup_basic extends Aspect
{
	static
		$common_model=
		[
			'group_type'=>
			[
				'type'=>'keyword',
				'default'=>'EntityGroup'
			],
			'entities'=>
			[
				'type'=>'linkset',
				'select'=>'generic_linked',
				'position'=>Request_generic_links::FROM_OBJECT,
				'opposite_id_group'=>'EntityGroup',
				'relation'=>'contains'
			],
			'owner'=>
			[
				'type'=>'entity',
				'id_group'=>'User'
			],
			'moders'=>
			[
				'type'=>'linkset',
				'id_group'=>'User',
				'position'=>Request_generic_links::FROM_SUBJECT,
				'opposite_id_group'=>'EntityGroup',
				'relation'=>'moderates'
			]
		],
		$init=false,
		$basic=true,
		$default_table='entities_groups';
}

?>