<?

namespace Pokeliga\Entlink;

class ModuleEntity extends \Pokeliga\Entity\EntityType
{
	static
		$base_aspects=
		[
			'basic'=>__NAMESPACE__.'\ModuleEntity_basic'
		];
}

class ModuleEntity_basic extends \Pokeliga\Entity\Aspect
{
	static
		$common_model=
		[
			'name'=>
			[
				'type'=>'keyword',
				'auto'=>'Pokeliga\Entlink\Fill_module_field'
			],
			'vendor'=>
			[
				'type'=>'title',
				'auto'=>'Pokeliga\Entlink\Fill_module_field'
			],
			'dir'=>
			[
				'type'=>'dirname'
			],
			'annotation'=>
			[
				'type'=>'text',
				'auto'=>'Pokeliga\Entlink\Fill_module_field'
			],
			'description'=>
			[
				'type'=>'html',
				'auto'=>'Pokeliga\Entlink\Fill_module_field'
			],
			'authors'=>
			[
				'type'=>'title',
				'auto'=>'Pokeliga\Entlink\Fill_module_field'
			],
			'version'=>
			[
				'type'=>'title',
				'auto'=>'Pokeliga\Entlink\Fill_module_field'
			],
			'updated'=>
			[
				'type'=>'timestamp',
				'auto'=>'Pokeliga\Entlink\Fill_module_field'
			],
			'url'=>
			[
				'type'=>'url',
				'auto'=>'Pokeliga\Entlink\Fill_module_field'
			],
			'support'=>
			[
				'type'=>'text',
				'auto'=>'Pokeliga\Entlink\Fill_module_field'
			]
		];
}

class Select_installed_modules extends \Pokeliga\Entity\Select
{
	use \Pokeliga\Entity\Select_complex;
	
	public function progress()
	{
		$dir=scandir(Engine()->modules_path);
		$result=[];
		foreach ($dir as $d)
		{
			if ($d==='.') continue;
			if ($d==='..') continue;
			if (!is_dir(Engine()->modules_path.'/'.$d)) continue;
			$intro=Module::retrieve_intro(Engine(), $d);
			if ($intro instanceof \Report_impossible) continue;
			$result[$d]=$intro;
		}
		$modules=[];
		foreach ($result as $dir=>$intro)
		{
			$module=$this->pool()->virtual_entity('Pokeliga\Entlink\ModuleEntity');
			$module->set('dir', $dir);
			$modules[]=$module;
		}
		
		$this->finish_with_resolution($this->linkset_from_entities($modules));
	}
}

class Fill_module_field extends \Pokeliga\Entity\Filler_for_entity
{
	public function module_dir()
	{
		return $this->entity->value('dir');
	}
	
	public function progress()
	{
		$dir=$this->module_dir();
		$intro=Module::retrieve_intro(Engine(), $dir);
		if (array_key_exists($this->value->code, $intro)) $this->finish_with_resolution($intro[$this->value->code]);
		else $this->impossible('no_intro_data');
	}
}
?>