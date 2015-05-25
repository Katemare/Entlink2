<?

class Image extends File
{
	const
		LOCATION_POINT=1,
		LOCATION_FRAGMENT=2;

	static
		$init=false,
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'=>'File_basic',
			'image'=>'File_image'
		];
}

class File_image extends Aspect
{
	static
		$common_model=
		[
			'width'=>
			[
				'type'=>'width',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['server_address'],
				'dependancies'=>['server_address'],
				'call'=>['_aspect', 'image', 'width']
			],
			'height'=>
			[
				'type'=>'height',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['server_address'],
				'dependancies'=>['server_address'],
				'call'=>['_aspect', 'image', 'height']
			],
			'locations'=>
			[
				'type'=>'linkset',
				'id_group'=>'ImageLocation',
				'select'=>'image_locations',
				'pathway_track'=>true
			],
			'points'=>
			[
				'type'=>'linkset',
				'id_group'=>'ImageLocation',
				'select'=>'image_locations',
				'pathway_track'=>true,
				'location_type'=>Image::LOCATION_POINT
			],
			'fragments'=>
			[
				'type'=>'linkset',
				'id_group'=>'ImageLocation',
				'select'=>'image_locations',
				'pathway_track'=>true,
				'location_type'=>Image::LOCATION_FRAGMENT
			],
			'new_fragment_url'=>
			[
				'type'=>'url',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['id'],
				'dependancies'=>['id'],
				'call'=>['_aspect', 'image', 'new_fragment_url']
			],
			'new_point_url'=>
			[
				'type'=>'url',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['id'],
				'dependancies'=>['id'],
				'call'=>['_aspect', 'image', 'new_point_url']
			],
			'with_locations_url'=>
			[
				'type'=>'url',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['id'],
				'dependancies'=>['id'],
				'call'=>['_aspect', 'image', 'with_locations_url']
			],
			'imagemap_html_id'=>
			[
				'type'=>'string',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['id'],
				'dependancies'=>['id'],
				'call'=>['_aspect', 'image', 'imagemap_html_id']
			]
		],
		$templates=
		[
			'img'=>'Template_img',
			'preview'=>'#file.image_preview',
			'with_locations'=>'#file.image_with_locations',
			'edit_form'=>true
		],
		$init=false,
		$default_table='files';
	
	public
		$getimagesize=null;
	
	public function make_complex_template($name, $line=[], &$do_setup=true)
	{
		if ($name==='edit_form') return 'UNIMPLEMENTED YET: edit image';
	}
	
	// для вызова в рамках заполнения ширины, длины и других значений, которые уже заранее запросили server_address.
	public function getimagesize($param=null)
	{
		if ($this->getimagesize===null)
		{
			$server_address=$this->entity->value('server_address');
			$this->getimagesize=getimagesize($server_address);
		}
		
		if ($this->getimagesize===false) return $this->sign_report(new Report_impossible('no_file'));
		if ($param===null) return $this->getimagesize;
		if (!array_key_exists($param, $this->getimagesize)) return $this->sign_report(new Report_impossible('no_image_param'));
		return $this->getimagesize[$param];
	}
	
	public function width()
	{
		return $this->getimagesize(0);
	}
	
	public function height()
	{
		return $this->getimagesize(1);
	}
	
	public function new_fragment_url()
	{
		return Router()->url('gallery/new_fragment.php?file='.$this->entity->value('id'));
	}
	
	public function new_point_url()
	{
		return Router()->url('gallery/new_point.php?file='.$this->entity->value('id'));
	}
	
	public function with_locations_url()
	{
		return Router()->url('gallery/image_locations.php?file='.$this->entity->value('id'));
	}
	
	public function imagemap_html_id()
	{
		return 'image_map_'.$this->entity->value('id');
	}
}

// необходимо для идентификации таких значений для особенной работы с ними.
class Value_dimension extends Value_unsigned_int
{
}

class Value_width extends Value_dimension
{
}

class Value_height extends Value_dimension
{
}
?>