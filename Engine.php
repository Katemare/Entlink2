<?
namespace Pokeliga\Entlink
{

include($entlink['modules_dir'].'/Basic/pre_include.php');

// это класс движка, являющийся основой всей системы. он создаётся первым и существует в одном экземпляре.
// он содержит подключение модулей и некоторые функции, общие для системы.
class Engine implements Multiton_host
{
	use Caller, Singleton, Multiton_host_standard, StaticHost, ArrayConfig;
	// движок объявлен как синглтон исключительно потому, что пока не найден способ позволить всем объектам (сущностям, задачам, значениям...) обращаться к движку, в контексте которого они были созданы, и при этом не хранить тысячу ссылок на него и не передавать их при создании. Ситуация, когда требуется две копии движка, представляется очень маловероятной, а значит, не требуется решать её сейчас, когда неизвестно практическое применение.
	
	const
		DEFAULT_MASTER_TEMPLATE=null,
		COMPACTER_KEY='__compacter'; // если первый элемент массива имеет этот ключ с содержимым true, он обрабатывается как аргументы для компактера. не следует использовать этот ключ с иной целью в моделях и командных строках!
	
	static
		$default_config=
		[
			'modules_dir'	=>'modules',
			'protocol'		=>'http',
			'retriever_key'	=>'retriever',
			'router_key'	=>'router',
			'pre_load'=>
			[
				'retriever'=>
				[
					'name'			=>'Retriever',
					'db_host'		=>'localhost',
					'db_login'		=>'root',
					'db_password'	=>'',
					'db_database'	=>'entlink'
				],
				'basic'=>
				[
					'name'			=>'Basic'
				],
				'task'=>
				[
					'name'			=>'Task'
				],
				'template'=>
				[
					// потому что Data зависит от Template... хорошо бы от этого избавиться.
					'name'			=>'Template',
				],
				'data'=>
				[
					'name'			=>'Data'
				],
				'entity'=>
				[
					'name'			=>'Entity'
				],
				
				// времени: требуется оптимизировать и сделать подключение этих модулей опциональным.
				'form'=>
				[
					'name'			=>'Form'
				],
				'router'=>
				[
					'name'			=>'Nav',
					'bad_page_class'=>'\Pokeliga\Nav\Page_bad' // STUB
				]
			],
		];
	
	public
		$config=null,
		$console=false,
		
		// задаются в конфигурации:
		$install_dir='', // путь установки относительно рута
		$modules=[], // эксземпляры настроенных модулей (ModuleFront) по их слагам
		$modules_init=[], // названия классов модулей, которые уже инициированы (прописана автозагрузка классов и так далее)
		
		// задаются автоматически:
		$host=null, 		// http://pokeliga.com/
		$base_url=null,		// http://pokeliga.com/entlink/
		$current_url=null,	// http://pokeliga.com/entlink/adopts/player_profile.php
		$docroot=null,		// /var/www/pokeliga/entlink
		$modules_path=null, // /var/www/pokeliga/entlink/modules

		$namespaces=[], // соответствия пространство имён => модуль для автозагрузки классов.
		$tracks=[], // модули и другие глобальные объекты, которые могут распознавать адресацию в ключевых словах, типа adopts.current_player,
		$templaters=[], // модули и другие глобальные объекты, которые должны реагировать на короткие записи типа {{announcement}}, отдавая шаблоны.
		$route_mappers=[], // модули и другие глобальные объекты, проводящие карты для роутеров.
		$form_slugs=[],
		$err_handler=null; // создаётся автоматически
	
	// ---------------------
	// Инициализация движка
	// ---------------------
	
	// запускается после чтения конфигурации.
	public function setup($config)
	{
		$this->console = php_sapi_name()==='cli';
	
		$this->apply_config($config);
		
		global $_SERVER;
		
		$this->docroot=$_SERVER['DOCUMENT_ROOT'];
		$this->install_dir=str_replace( str_replace('\\', '/', $this->docroot).'/', '', str_replace('\\', '/', __DIR__));
		$this->protocol=$this->config['protocol'].'://';
		
		$this->host=$this->protocol.$_SERVER['SERVER_NAME'];
		$this->current_url=$_SERVER['SCRIPT_NAME'];

		$this->base_url=$this->host.'/'.((empty($this->install_dir))?(''):($this->install_dir.'/'));
		$this->modules_path=$this->server_address($this->config['modules_dir']);
		
		$this->setup_autoload();
		$this->setup_shutdown();
		$this->init_modules($this->config['pre_load']);
		if (array_key_exists($key=$this->config['retriever_key'], $this->modules))
		{
			$this->retriever=$this->modules[$key]->get_retriever();
			$db_config=$this->retrieve_config();
			if ($db_config instanceof \Report_impossible) die('CANT RETRIEVE CONFIG');
			$this->apply_config($db_config);
			if (array_key_exists('modules', $db_config)) $this->init_modules($db_config['modules']);
		}
		
		if (array_key_exists($key=$this->config['router_key'], $this->modules))
		{
			$this->router=$this->modules[$key]->get_root_router();
		}
	}
	
	public function retrieve_config()
	{
		// STUB - пока не ясно, как это делать лучше, но надо с чего-то начинать.
		// не используется Keeper_var и Entity потому, что этот модуль может и не использоваться. с другой стороны, редактирование конфигурации должно быть удобным, чтобы сериализация не мешала (или надо отказаться от неё и как-то по другому хранить сложный массив).
		$query=
		[
			'action'=>'select',
			'table'=>'entities_vars',
			'where'=>
			[
				'id'=>1,
				'id_group'=>get_class($this), // FIX: в будущем, возможно, следует использовать название типа сущности, которое не должно совпадать с названием объекта 
				'code'=>'config',
				'index'=>0
			]
		];
		$result=$this->retriever->run_query($query);
		if ($result instanceof \Report) return $result;
		if (empty($result)) return [];
		return unserialize($result[0]['str']);
	}
	
	public function server_address($path)
	{
		return $this->docroot.'/'.((empty($this->install_dir))?(''):($this->install_dir.'/')).$path; // COMP: возможно, на других системах следует добавить слеш
	}
	
	public function module_address($module, $add='')
	{
		return $this->modules_path.'/'.$module.'/'.$add;
	}
	
	public function use_config($config)
	{
		if (!empty($config['timezone'])) date_default_timezone_set($config['timezone']);
		if (!empty($config['language'])) ini_set('mbstring.language', $config['language']);
	}
	
	// читает список названий модулей, создаёт и инициирует каждый из них.
	public function init_modules($data)
	{
		$data=$this->sort_modules_by_dependancies($data);
		if ($data instanceof \Report_impossible) die('BAD DEPENDANCIES');
		
		foreach ($data as $module_slug=>$module_data)
		{
			if (array_key_exists($module_slug, $this->modules)) die('MODULE SLUG DOUBLE');
			$module=Module::for_engine($this, $module_slug, $module_data);
			$this->modules[$module_slug]=$module;
		}
	}
	
	public function sort_modules_by_dependancies($module_data)
	{
		// STUB! пока просто следует размещать модули в порядке их загрузки.
		
		/*
		$loaders=[];
		foreach ($this->data as $module_slug=>$module_data)
		{
			$module_name=$module_data['module'];
			if (array_key_exists($module_name, $loaders)) continue;
			$loaders[$module_name]=Module::get_loader_data($module_name);
		}
		*/
		
		return $module_data;
	}
	
	public function setup_shutdown()
	{
		register_shutdown_function(function() { debug_dump(); });
	}
	
	// устанавливает функции, подключающие требуемые классы.
	public function setup_autoload()
	{
		spl_autoload_register( [$this, 'quick_autoload'] );
		spl_autoload_register( [$this, 'normal_autoload'] );
		spl_autoload_register( [$this, 'finish_autoload'] );
	}
	
	// эта функция вызывается модулями, когда те хотят зарегистрировать свои "быстрые классы", автозагружаемые по быстрому алгоритму.
	public function register_namespace($namespace, $module)
	{
		$this->namespaces[$namespace]=$module;
	}
	
	public function register_value_types($type_data)
	{
		$this->value_types+=$type_data;
	}
	
	public function register_track($track, $target)
	{
		if (array_key_exists($track, $this->tracks)) die ('DOUBLE TRACK');
		$this->tracks[$track]=$target;
	}
	
	public function register_templater($templater)
	{
		$this->templaters[]=$templater;
	}
	public function register_route_mappers($mapper)
	{
		$this->route_mappers[]=$mapper;
	}
	
	public function register_module_slug($slug, $module)
	{
		if (array_key_exists($slug, $this->module_slugs)) die('DOUBLE MODULE SLUG');
		$this->module_slugs[$slug]=$module;
	}
	
	public function register_form_slug($slug, $form_class)
	{
		if (array_key_exists($slug, $this->form_slugs)) die('DOUBLE FORM SLUG');
		$this->form_slugs[$slug]=$form_class;
	}
	
	// быстрый алгоритм поиска класса для автозагрузки.
	public function quick_autoload($class_name)
	{
		if (array_key_exists($class_name, $this->no_class)) return;
		
		if (empty($this->ns_ex)) $this->ns_ex='/^('.implode('|', array_map('preg_quote', array_keys($this->namespaces))).')\\\\(.+)$/';
		if (preg_match($this->ns_ex, $class_name, $m)) $this->namespaces[$m[1]]->quick_autoload($m[2]);
		/*
		$point=strpos($class_name, '\\', strpos($class_name, '\\')+1);
		$namespace=substr($class_name, 0, $point);
		if (array_key_exists($namespace, $this->namespaces)) $this->namespaces[$namespace]->quick_autoload(substr($class_name, $point+1));
		*/
	}
	
	// сложный алгоритм поиска класса для автозагрузки.
	public function normal_autoload($class_name)
	{
		if (array_key_exists($class_name, $this->no_class)) return;
		foreach ($this->modules as $module)
		{
			$module->normal_autoload($class_name);
			if (class_exists($class_name, false)) break;
		}
	}
	
	public $no_class=[];
	public function finish_autoload($class_name)
	{
		$this->no_class[$class_name]=true;
	}
	
	public function form_class_by_slug($slug)
	{
		if (array_key_exists($slug, $this->form_slugs)) return $this->form_slugs[$slug];
	}
	
	public function master_template_class()
	{
		if (array_key_exists('master_template', $this->config)) return $this->config['master_template'];
		return static::DEFAULT_MASTER_TEMPLATE;
	}
	
	// -------------------------
	// Взаимодействие объектов
	// -------------------------
	
	public function invalidate_cache($cache_key)
	{
		if (empty($cache_key)) return;
		$query=
		[
			'action'=>'delete',
			'table'=>'info_cache',
			'where'=>['code'=>$cache_key[0], 'num'=>$cache_key[1]]
			// позволяет удалить как несколько номеров к одному коду, и несколько кодов к одному номеру, и любые комбинации с кодом из первого набора и номером из второго.
		];
		$this->retriever->run_query($query);
	}
		
	// ----------------------
	// Работа с модулями
	// ----------------------
	
	public function get_modules()
	{
		return $this->modules;
	}
	
	public function module($module)
	{
		if (!array_key_exists($module, $this->modules)) return;
		return $this->modules[$module];
	}
	
	public function module_by_slug($slug)
	{
		if (array_key_exists($slug, $this->module_slugs)) return $this->module_slugs[$slug];
	}
	
	public function value_class_by_type($type_keyword)
	{
		if (!array_key_exists($type_keyword, $this->value_types)) die('UNKNOWN VALUE TYPE: '.$type_keyword);
		return $this->value_types[$type_keyword];
	}
}

} // конец пространства имён Pokeliga\Entlink

namespace // глобально доступные функции
{
	
function Engine()
{
	return \Pokeliga\Entlink\Engine::instance();
}

function Retriever()
{
	return \Pokeliga\Entlink\Engine::instance()->retriever;
}

function Router()
{
	return \Pokeliga\Entlink\Engine::instance()->router;
}

function prepend_namespace($class_name, $ns, $absolutize=true)
{
	if ($class_name[0]==='\\') return $class_name;
	$result=$ns.'\\'.$class_name;
	if ($absolutize) $result='\\'.$result;
	return $result;
}

// название не вполне подходящее: эта функция не проверяет соответствие интерфейсу по наличествующим методам и другим свойствам интерфейса.
// вместо этого она позволяет взять с объекта обещание, что если он содержит динамическую композицию, то он адресует запросы тому компоненту, который реализует интерфейса.
// однако это "утиная" проверка в том смысле, что если заменить её на настоящую утиную проверку - смысл не поменяется.
function duck_instanceof($object, $name)
{
	if ($object instanceof $name) return true;
	if ($object instanceof \Pokeliga\Entlink\Interface_proxy) return $object->implements_interface($name);
	// нет аналогичного способа для классов, а не интерфейсов, потому что в случае такой необходимости следует создавать интерфейс.
	return false;
}

}
?>