<?
namespace Pokeliga\File;

class ImageLocation extends EntityType
{
	static
		$init=false,
		$module_slug='file',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$templates=[],
		$base_aspects=
		[
			'basic'=>'ImageLocation_basic',
			'details'=>'ImageLocation_type' // ImageLocation_point, ImageLocation_fragment
		],
		$variant_aspects=['details'=>'Task_imagelocation_determine_details_aspect'];
}

class ImageLocation_basic extends Aspect
{
	static
		$common_model=
		[
			'image'=>
			[
				'type'=>'entity',
				'id_group'=>'Image',
				'import'=>['width', 'height']
			],
			'coord_x'=>
			[
				'type'=>'coord_x',
				'validators'=>['less_or_equal_to_sibling'],
				'less_or_equal'=>'width',
				'dependancies'=>['width']
			],
			'coord_y'=>
			[
				'type'=>'coord_y',
				'validators'=>['less_or_equal_to_sibling'],
				'less_or_equal'=>'height',
				'dependancies'=>['height']
			],
			'location_type'=>
			[
				'type'=>'enum',
				'options'=>[Image::LOCATION_POINT, Image::LOCATION_FRAGMENT]
			]
		],
		$templates=[],
		$init=false,
		$basic=true,
		$default_table='files_image_locations';
}

abstract class ImageLocation_type extends Aspect
{
	static
		$common_model=
		[
			'coord_x2'=>
			[
				'type'=>'coord_x',
				'const'=>null
			],
			'coord_y2'=>
			[
				'type'=>'coord_y',
				'const'=>null
			],
			'coords'=>
			[
				'type'=>'coords',
				'dependancies'=>['coord_x', 'coord_y', 'coord_x2', 'coord_y2']
			],
			'parent_fragment'=>
			[
				'type'=>'entity',
				'id_group'=>'ImageLocation',
				'const'=>null
			],
			'safe_title'=>
			[
				'type'=>'title',
				'keeper'=>false,
				'const'=>'STUB'
			]
		],
		$templates=
		[
			'title'=>'#file.error_bad_location_type',
			'edit_form'=>'#standard.error_cant_edit'
		],
		$default_table='files_image_locations',
		$init=false;
	
	// должна вернуть массив с координатами при условии, что все данные уже получены.
	public abstract function coords();
}

class Task_imagelocation_determine_details_aspect extends Task_determine_aspect
{
	const
		ON_POINT='ImageLocation_point',
		ON_FRAGMENT='ImageLocation_fragment';
		
	public $requested=['location_type']; // требуется только один параметр.
	
	public function progress()
	{
		$type=$this->entity->request('location_type');
		if ($type instanceof \Report_resolution)
		{
			$type=$type->resolution;
			$resolution=null;
			if ($type===Image::LOCATION_POINT) $resolution=static::ON_POINT;
			elseif ($type===Image::LOCATION_FRAGMENT) $resolution=static::ON_FRAGMENT;
			
			if ($resolution===null) $this->impossible('bad_location_type');
			else
			{
				$this->resolution=$resolution;
				$this->finish();
			}
		}
		elseif ($type instanceof \Report_tasks) $type->register_dependancies_for($this);
		elseif ($type instanceof \Report_impossible) $this->impossible('no_type');
	}
}

class ImageLocation_point extends ImageLocation_type
{
	const MODEL_MODIFIED=__CLASS__;
	
	static
		$common_model=null,
		$modify_model=
		[
			'coords'=>
			[
				'type'=>'coords',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['coord_x', 'coord_y'],
				'call'=>['_aspect', 'details', 'coords'],
				'dependancies'=>['coord_x', 'coord_y', 'coord_x2', 'coord_y2'] // пока что зависимости должны быть такие же, как у "основного" значения.
			],
			'parent_fragment'=>
			[
				'type'=>'entity',
				'id_group'=>'ImageLocation',
				'null'=>true,
				'default'=>null,
				'auto_valid'=>[null],
				'validators'=>['subentity_value_is', 'contains_point'],
				'subentity_value_is'=>['location_type'=>Image::LOCATION_FRAGMENT, 'image'=>[Engine::COMPACTER_KEY=>true, 'sibling_valid_content', 'image']],
				'backlink_code'=>'image',
				'point_coords'=>[Engine::COMPACTER_KEY=>true, 'sibling_valid_content', 'coords']
			]
		],
		$templates=
		[
			'title'=>'#file.image_point_title',
			'edit_form'=>true
		],
		$init=false;
		
	public function coords()
	{
		return ['x'=>$this->entity->value('coord_x'), 'y'=>$this->entity->value('coord_y')];
	}
	
	public function make_complex_template($name, $line=[], &$do_setup=true)
	{
		if ($name==='edit_form')
		{
			$form=Form_image_edit_point::create_for_display();
			$form->entity=$this->entity;
			$template=$form->main_template($line);
			return $template;
		}
	}
}

class ImageLocation_fragment extends ImageLocation_type
{
	const MODEL_MODIFIED=__CLASS__;
	
	static
		$common_model=null,
		$modify_model=
		[
			'coord_x2'=>
			[
				'type'=>'coord_x',
				'validators'=>['less_or_equal_to_sibling', 'greater_or_equal_to_sibling'],
				'greater_or_equal'=>'coord_x',
				'less_or_equal'=>'width',
				'dependancies'=>['width', 'coord_x']
			],
			'coord_y2'=>
			[
				'type'=>'coord_y',
				'validators'=>['less_or_equal_to_sibling', 'greater_or_equal_to_sibling'],
				'less_or_equal'=>'height',
				'greater_or_equal'=>'coord_y',
				'dependancies'=>['height', 'coord_y']
			],
			'coords'=>
			[
				'type'=>'coords',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['coord_x', 'coord_y', 'coord_x2', 'coord_y2'],
				'call'=>['_aspect', 'details', 'coords'],
				'dependancies'=>['coord_x', 'coord_y', 'coord_x2', 'coord_y2'] // пока что зависимости должны быть такие же, как у "основного" значения.
			],
		],
		$templates=
		[
			'title'=>'#file.image_fragment_title',
			'edit_form'=>true
		],
		$init=false;
		
	public function coords()
	{
		return
		[
			'x'=>$this->entity->value('coord_x'), 'y'=>$this->entity->value('coord_y'),
			'x2'=>$this->entity->value('coord_x2'), 'y2'=>$this->entity->value('coord_y2')
		];
	}
	
	public function make_complex_template($name, $line=[], &$do_setup=true)
	{
		if ($name==='edit_form')
		{
			$form=Form_image_edit_fragment::create_for_display();
			$form->entity=$this->entity;
			$template=$form->main_template($line);
			return $template;
		}
	}
}

class Request_image_locations_with_type extends Request_by_field
{
	static
		$instances=[];
	
	public
		$type;
	
	public function __construct($type=null)
	{
		$this->type=$type;
		parent::__construct(ImageLocation_basic::$default_table, 'image');
	}
	
	public function make_query()
	{
		$query=parent::make_query();
		if ($this->type!==null) $query['where']['location_type']=$this->type;
		return $query;
	}
	
	// сброс поведения мультитона до стандартного состояния.
	public static function make_Multiton_class_name($args)
	{
		return static::std_make_Multiton_class_name($args);
	}
}

class Select_image_locations extends Select_by_single_request
{
	const
		REQUEST_CLASS='Request_image_locations_with_type';
		
	public
		$request=null;
	
	public function type()
	{
		if ($this->in_value_model('location_type')) return $this->value_model_now('location_type');
	}
	
	public function create_request()
	{
		$class=static::REQUEST_CLASS;
		return new RequestTicket($class, [$this->type()], [$this->entity->db_id]);
	}
}
?>