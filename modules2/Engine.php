<?
include('Basic/traits.php');
include('Basic/Module.php');

// это класс движка, являющийся основой всей системы. он создаётся первым и существует в одном экземпляре.
// он содержит подключение модулей и некоторые функции, общие для системы.
class Engine
{
	use Caller, Singleton;
	// движок объявлен как синглтон исключительно потому, что пока не найден способ позволить всем объектам (сущностям, задачам, значениям...) обращаться к движку, в контексте которого они были созданы, и при этом не хранить тысячу ссылок на него и не передавать их при создании. Ситуация, когда требуется две копии движка, представляется очень маловероятной, а значит, не требуется решать её сейчас, когда неизвестно практическое применение.
	
	const
		DEFAULT_MASTER_TEMPLATE=null,
		COMPACTER_KEY='__compacter'; // если первый элемент массива имеет этот ключ с содержимым true, он обрабатывается как аргументы для компактера. не следует использовать этот ключ с иной целью в моделях и командных строках!
	
	public
		$config=null,
		$console=false,
		$protocol='http', // дополняется до http://
		
		// задаются в конфигурации:
		$install_dir='',
		$modules=[], // заполняется в конфигурации
		
		// задаются автоматически:
		$host=null, 		// http://pokeliga.com/
		$base_url=null,		// http://pokeliga.com/entlink/
		$current_url=null,	// http://pokeliga.com/entlink/adopts/player_profile.php
		$docroot=null,		// /var/www/pokeliga/entlink
		$modules_path=null, // /var/www/pokeliga/entlink/modules

		$quick_classes=[], // соответствия "класс - модуль" для быстрого поиска классов.
		$tracks=[], // модули и другие глобальные объекты, которые могут распознавать адресацию в ключевых словах, типа adopts.current_player,
		$templaters=[], // модули и другие глобальные объекты, которые должны реагировать на короткие записи типа {{announcement}}, отдавая шаблоны.
		$form_slugs=[],
		$module_slugs=[],
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
		if (!empty($config['android_dev'])) $_SERVER['DOCUMENT_ROOT']='../..';
		
		$this->install_dir=$this->config['install_dir'];
		$this->protocol=$this->config['protocol'].'://';
		
		$this->host=$this->protocol.$_SERVER['SERVER_NAME'];
		$this->current_url=$_SERVER['SCRIPT_NAME'];
		$this->docroot=$_SERVER['DOCUMENT_ROOT'];
		$this->base_url=$this->host.'/'.((empty($this->install_dir))?(''):($this->install_dir.'/'));
		$this->modules_path=$this->server_address($config['modules_dir']);
		
		$this->setup_autoload();		
		$this->init_modules();
		
		// STUB
		if (class_exists('Retriever'))
		{
			$this->retriever=new Retriever();
			$this->retriever->setup();
		}
		
		if (class_exists('Router'))
		{
			$this->router=new Router();
		}
		
		//$this->err_handler=new ErrorHandler(); // временно отключено
	}
	
	public function server_address($path)
	{
		return $this->docroot.'/'.((empty($this->install_dir))?(''):($this->install_dir.'/')).$path; // COMP: возможно, на других системах следует добавить слеш
	}
	
	public function module_address($module, $add='')
	{
		return $this->modules_path.'/'.$module.'/'.$add;
	}
	
	static
		$default_config=
		[
			'db_host'=>'localhost',
			'db_login'=>'root',
			'db_password'=>'',
			'db_database'=>'entlink',
			'db_operator'=>'mysqli',
			'imagemagick'=>false,
			'development'=>false,
			'modules_dir'=>'modules',
			'modules'=>['Basic'],
			'install_dir'=>'',
			'protocol'=>'http'
		];
		
	public function apply_config($config)
	{
		$this->config=array_merge(static::$default_config, $config);
	}
	
	// подключает название модуля до того, как модули инициированы.
	public function attach_module($module)
	{
		if (is_array($module)) $this->modules=array_merge($this->modules, $module);
		else $this->modules[]=$module;
	}
	
	// читает список названий модулей, создаёт и инициирует каждый из них.
	public function init_modules()
	{
		$this->attach_module($this->config['modules']);
		$created_modules=[];
		foreach ($this->modules as $module_code)
		{
			$module=Module::from_engine($module_code, $this);
			$module->engine=$this;
			$module->setup();
			$created_modules[$module_code]=$module;
		}
		$this->modules=$created_modules;
	}
	
	// устанавливает функции, подключающие требуемые классы.
	public function setup_autoload()
	{
		spl_autoload_register( [$this, 'quick_autoload'] );
		spl_autoload_register( [$this, 'normal_autoload'] );
		spl_autoload_register( [$this, 'finish_autoload'] );
	}
	
	// эта функция вызывается модулями, когда те хотят зарегистрировать свои "быстрые классы", автозагружаемые по быстрому алгоритму.
	public function register_quick_classes($classes, $module)
	{
		$this->quick_classes=array_merge($this->quick_classes, array_fill_keys($classes, $module));
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
		if (array_key_exists($class_name, $this->quick_classes)) $this->quick_classes[$class_name]->quick_autoload($class_name);
	}
	
	// сложный алгоритм поиска класса для автозагрузки.
	public function normal_autoload($class_name)
	{
		if (array_key_exists($class_name, $this->no_class)) return;
		foreach ($this->modules as $module)
		{
			if (!is_object($module)) continue;
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
}

function Engine()
{
	return Engine::instance();
}

function Retriever()
{
	return Engine::instance()->retriever;
}

function Router()
{
	return Engine::instance()->router;
}
?>