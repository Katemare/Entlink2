<?

namespace Pokeliga\Entity;

class Test extends EntityType
{
	static
		$base_aspects=
		[
			'basic'=>'Pokeliga\Entity\Test_basic',
		],
		$default_table='test';
}

class Test_basic extends Aspect
{
	static
		$common_model=
		[
			'test_string'=>
			[
				'type'=>'string',
			],
			'test_bool'=>
			[
				'type'=>'bool'
			],
			'test_number'=>
			[
				'type'=>'number'
			],
			'test_array'=>
			[
				'type'=>'array',
				'null'=>true
			],
			'test_entity'=>
			[
				'type'=>'entity',
				'null'=>true,
				'field'=>'test_id',
				'keeper'=>'id_and_group'
			]
		],
		$basic=true,
		$default_table='test';
}

?>