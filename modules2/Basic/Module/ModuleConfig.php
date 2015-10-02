<?

namespace Pokeliga\Entlink;

class ModuleConfig extends \Pokelga\Entity\EntityType
{
	static
		$base_aspects=
		[
			'basic'=>'Pokeliga\Entlink\ModuleConfig_basic',
			// 'specific'=>'Pokeliga\Entlink\ModuleConfig_specific'
		],
		/*
		$variant_aspects=
		[
			'specific'=>
			[
				'task_class'			=>'Pokeliga\Entlink\Task_determine_aspect_by_param',
				'param'					=>'name',
				'default_aspect_base'	=>'Pokeliga\\',
				'default_aspect_suffix'	=>'\ModuleConfig_specific'
				// получается Pokeliga\<Name>\ModuleConfig_specific
			]
		]*/
		;
}

class ModuleConfig_basic extends \Pokeliga\Entity\Aspect
{
	static
		$basic=true,
		$common_model=
		[
			'name'=>
			[
				'type'=>'module_name'
			],
			'slug'=>
			[
				'type'=>'slug'
			]
			'dir'=>
			[
				'type'=>'dir',
			],
			'module_header_path'=>
			[
				'type'=>'file_address',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['name', 'dir'],
				'call'=>['_aspect', 'basic', 'module_header_path']
			],
			'on'=>
			[
				'type'=>'bool',
				'default'=>true,
				'validators'=>['unique_module_on_slug'] // только среди включённых конфигураций каждый слаг должен быть уникальным.
			],
			'details'=>
			[
				'type'=>'array'
			]
		],
		$templates=
		[
			'edit_form'=>true
		];
	
	public function module_header_path()
	{
		$name=$this->entity->value('name');
		$dir=$this->entity->value('dir');
		return Module::get_module_header_address(['name'=>$name, 'dir'=>$dir]);
	}
}

?>