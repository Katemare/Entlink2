<?

class TrophyBlueprint extends EntityType
{
	const
		TYPE_SINGLE		=0,	// трофей можно получить лишь единожды. Повторное получение ничего не даёт.
		TYPE_CUMULATIVE	=1, // трофей можно получить много раз, увеличивая количество.
		TYPE_RATING		=2, // трофей имеет стадии. Старшая стадия заменяет младшую.
		TYPE_RATING_LEVEL=4, // трофей представляет собой стадию в том или ином рейтинге и не может быть получен сам по себе.
		
		// для рейтингов.
		PIC_SPECIFIC	=0,	// каждый уровень обозначается картинкой данного уровня.
		PIC_CUMULATIVE	=1;	// каждый уровень обозначается картинками, слева направо, всех достигнутых уровней.
	
	static
		$init=false,
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'			=>'TrophyBlueprint_basic',
			'contribution'	=>'Contribution_identity',
			'complex'		=>'TrophyBlueprint_complex',
			'adopts'		=>'TrophyBlueprint_adopts' // STUB: должно подключаться при загрузке модуля AdoptsGame
		],
		$variant_aspects=['complex'=>'Task_trophy_blueprint_determine_complex_aspect'],
		$default_table='info_trophies';
}

class TrophyBlueprint_basic extends Aspect
{
	static
		$common_model=
		[
			'icon'=>
			[
				'type'=>'id',
				'id_group'=>'Image'
			],
			'image'=>
			[
				'type'=>'id',
				'id_group'=>'Image',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['cover_image', 'icon'],
				'call'=>['_aspect', 'basic', 'image']
			],
			'trophy_type'=>
			[
				'type'=>'enum',
				'options'=>
				[
					TrophyBlueprint::TYPE_SINGLE,	TrophyBlueprint::TYPE_CUMULATIVE,
					TrophyBlueprint::TYPE_RATING, 	TrophyBlueprint::TYPE_RATING_LEVEL
				],
				'default'=>TrophyBlueprint::TYPE_SINGLE
			],
			'public'=>
			[
				// могут ли другие пользователи видеть такие трофеи друг у друга.
				'type'=>'bool',
				'default'=>true
			],
			
			'awarded_to_users'=>
			[
				'type'=>'linkset',
				'id_group'=>'Trophy',
				'select'=>'backlinked',
				'backlink_field'=>'owner'
			],
			'awarded_to_users_public'=>
			[
				'type'=>'linkset',
				'id_group'=>'Trophy',
				'select'=>'backlinked',
				'backlink_field'=>'owner',
				'select_condition'=>['stashed'=>false]
			]
		],
		$templates=
		[
			'showcase'			=>'#standard.trophy_showcase',
			'icon_tooltiped'	=>'#standard.trophy_icon_with_tooltip',
			'icon_titled'		=>'#standard.trophy_icon_and_title'
		],
		$init=false,
		$basic=true,
		$default_table='info_trophies';
	
	public function image()
	{
		$cover=$this->entity->value('cover_image');
		if ( (!empty($cover)) && (!($cover instanceof Report_impossible)) ) return $cover;
		return $this->entity->value('icon');
	}
}

class TrophyBlueprint_complex extends Aspect
{
	static
		$common_model=
		[
			'subtrophies'=>
			[
				'type'=>'linkset_ordered',
				'id_group'=>'TrophyBlueprint',
				'keeper'=>'var_array'
			],
			'levels'=>
			[
				// уровень для рейтинга или количество для накапливаемой награды.
				'type'=>'unsigned_int',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['subtrophies'],
				'call'=>['_aspect', 'complex', 'levels']
			],
			'display_type'=>
			[
				'type'=>'enum',
				'options'=>[TrophyBlueprint::PIC_SPECIFIC, TrophyBlueprint::PIC_CUMULATIVE]
			]
		],
		$templates=
		[
			'with_level'=>true
		],
		$init=false,
		$basic=false,
		$default_table='info_trophies';
	
	public function levels()
	{
		$subtrophies=$this->entity->value('subtrophies');
		return count($subtrophies->values);
	}
	
	public function make_complex_template($name, $line=[], &$do_setup=true)
	{
		if ($name==='with_level')
		{
			$level=$line['level'];
			$tasks=[];
			
			$report=$this->entity->request('subtrophies');
			if ($report instanceof Report_impossible) return $report;
			if ($report instanceof Report_tasks) $tasks=array_merge($tasks, $report->tasks);
			else $subtrophies=$report->resolution->values;
			
			$report=$this->entity->request('display_type');
			if ($report instanceof Report_impossible) return $report;
			if ($report instanceof Report_tasks) $tasks=array_merge($tasks, $report->tasks);
			else $display_type=$report->resolution;
			
			if (!empty($tasks))
			{
				$call=new Call([$this, __METHOD__], $name, $line);
				return Task_delayed_call::with_call($call, $report);
			}
			
			if (!array_key_exists($level, $subtrophies)) return '';
			
			if ($display_type===TrophyBlueprint::PIC_SPECIFIC) return $subtrophies[$level]->template(null, $line);
			
			// $list=[];
			// foreach ($
		}
	}
}

class TrophyBlueprint_non_complex extends TrophyBlueprint_complex
{
	const
		MODEL_REDECLARED='TrophyBlueprint_non_complex';
	
	static
		$common_model=
		[
			'subtrophies'=>
			[
				'type'=>'linkset_ordered',
				'id_group'=>'TrophyBlueprint',
				'null'=>true,
				'const'=>null,
				'auto_valid'=>[null],
				'keeper'=>false
			],
			'levels'=>
			[
				'type'=>'unsigned_int',
				'keeper'=>false,
				'const'=>0
			],
			'display_type'=>
			[
				'type'=>'enum',
				'null'=>true,
				'auto_valid'=>[null],
				'const'=>null
			]
		],
		$templates=
		[
			'with_level'=>true
		],
		$init=false;
		
	public function make_complex_template($name, $line=[], &$do_setup=true)
	{
		if ($name==='with_level')
		{
			$level=$line['level'];
			$template=Template_trophy_with_level::for_trophy($this->entity, $level, $line);
			return $template;
		}
	}
}

class Template_trophy_with_level extends Template_from_db
{
	public
		$level,
		$elements=['level'];
	
	public static function for_trophy($trophy, $level, $line=[])
	{
		$template=static::with_line($line);
		$template->context=$trophy;
		$template->level=$level;
		return $template;
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='level') return $this->level;
		return parent::make_template($code, $line);
	}
}

class Task_trophy_blueprint_determine_complex_aspect extends Task_determine_aspect
{
	const
		ON_COMPLEX		='TrophyBlueprint_complex',
		ON_NON_COMPLEX	='TrophyBlueprint_non_complex';
	
	public $requested=['trophy_type']; // требуется только один параметр.
	
	public function progress()
	{
		$type=$this->entity->request('trophy_type');
		if ($type instanceof Report_resolution)
		{
			$type=$type->resolution;
			$resolution=null;
			if ($type===TrophyBlueprint::TYPE_RATING) $resolution=static::ON_COMPLEX;
			else $resolution=static::ON_NON_COMPLEX;
			
			if ($resolution===null) $this->impossible('bad_trophy_type');
			else
			{
				$this->resolution=$resolution;
				$this->finish();
			}
		}
		elseif ($type instanceof Report_tasks) $type->register_dependancies_for($this);
		elseif ($type instanceof Report_impossible) $this->impossible('no_type');
	}
}

class TrophyBlueprint_adopts extends Aspect
{
	static
		$common_model=
		[
			'adopts_related'=>
			[
				'type'=>'bool',
				'default'=>false
			],
			'accessory'=>
			[
				'type'=>'bool',
				'default'=>false
			],
			'awarded_to_pokemon'=>
			[
				'type'=>'linkset',
				'id_group'=>'PokemonTrophy',
				'select'=>'backlinked',
				'backlink_field'=>'owner'
			],
			'awarded_to_pokemon_public'=>
			[
				'type'=>'linkset',
				'id_group'=>'PokemonTrophy',
				'select'=>'backlinked',
				'backlink_field'=>'owner',
				'select_condition'=>['stashed'=>false]
			]
		],
		$init=false,
		$basic=false,
		$default_table='info_trophies';
}
?>