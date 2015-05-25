<?

class File extends EntityType
{
	static
		$init=false,
		$module_slug='file',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'=>'File_basic'
		],
		$default_table='files';
		
	public static function create_search_ticket($search, $range_query)
	{
		$from_name=static::search_text_from_range_ticket($range_query, static::$default_table, 'nam', $search);
		$from_path=static::search_text_from_range_ticket($range_query, static::$default_table, 'path', $search);

		return new RequestTicket_union([$from_name, $from_path]);
	}
}

class File_basic extends Aspect
{
	static
		$common_model=
		[
			'path'=>
			[
				'type'=>'string'
			],
			'pretty_path'=>
			[
				'type'=>'string',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['path'],
				'dependancies'=>['path'],
				'call'=>['_aspect', 'basic', 'pretty_path']
			],
			'name'=>
			[
				'type'=>'string',
				'field'=>'nam'
			],
			/*
			'title'=>
			[
				'type'=>'string',
				'null'=>true
			],
			'file_type'=>
			[
				'type'=>'enum',
				'options'=>['image', 'other'],
				'auto'=>'task'
			],
			*/
			'url'=>
			[
				'type'=>'url',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['path', 'name'],
				'dependancies'=>['path', 'name'],
				'call'=>['_aspect', 'basic', 'url']
			],
			'server_address'=>
			[
				'type'=>'string',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['path', 'name'],
				'dependancies'=>['path', 'name'],
				'call'=>['_aspect', 'basic', 'server_address']
			],
			'info_url'=>
			[
				'type'=>'url',
				'keeper'=>false,
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['id'],
				'call'=>['_aspect', 'basic', 'info_url']
			],
		],
		$templates=
		[
			'link'=>'#file.file_link',
			'option_title'=>true
		],
		$init=false,
		$basic=true,
		$default_table='files';
		
	public function url()
	{
		return Router()->url($this->entity->value('path').'/'.$this->entity->value('name'));
	}
	
	public function server_address()
	{
		return Engine()->server_address($this->entity->value('path').'/'.$this->entity->value('name'));
	}
	
	public function info_url()
	{
		return Router()->url('gallery/file_info.php?id='.$this->entity->value('id')); // STAB
	}
	
	public function pretty_path()
	{
		$path=$this->entity->value('path');
		if (preg_match('/^files\/(?<pretty>.+)$/', $path, $m)) return $m['pretty'];
		return $path;
	}
	
	public function make_complex_template($code, $line=[], &$do_setup=true)
	{
		if ($code==='option_title') return Template::master_instance()->template('file.option_title');
	}
}

class Request_dir_files extends Request_by_field
{
	use Singleton;
	
	public function __construct()
	{
		parent::__construct(File::$default_table, 'path');
	}
}

class Request_dir_files_by_name extends Request_by_field
{
	static
		$instances=[];
		
	public
		$path;
	
	public function __construct($path)
	{
		parent::__construct(File::$default_table, 'nam');
		$this->path=$path;
	}
	
	public function make_query()
	{
		$query=parent::make_query();
		$query['where']['path']=$this->path;
		return $query;
	}
}

class Provide_file_by_path extends Provide_by_single_request
{
	public
		$path, $name;

	public function setup_by_args($args)
	{
		$this->path=reset($args);
		$this->name=next($args);
	}
	
	public function create_request()
	{
		return Request_dir_files_by_name::instance($this->path);
	}
	
	public function get_data_set()
	{
		return $this->get_request()->get_data_set($this->name);
	}
}
?>