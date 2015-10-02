<?
namespace Pokeliga\Entlink;

// класс, от которого образуются все классы модулей.
class Module
{
	use Singleton, StaticHost, StaticOwner;

	const
		FRONT_CLASS_NAME='\Pokeliga\Entlink\ModuleFront',
		DEFAULT_VENDOR='Pokeliga';
		
	static
		$default_config;
	
	public
		$engine,
		$dir, // папка, в которой находятся файлы модуля, внутри папки модулей.
		$ns;
	
	protected
		$quick_classes=[], // список соответствий "название класса - название файла" (без расширения).
		$class_shorthands=[];
	
	// ---------------------
	// Инициализация
	// ---------------------
	
	// этой статической функцией создаются все модули.
	public final static function for_engine($engine, $slug, $config)
	{
		if (!array_key_exists('name', $config)) die('NO MODULE NAME: '.$slug);
		$module_class=static::get_module_header_class($config);
		if (!class_exists($module_class, false)) include_once(static::get_module_header_address($engine, $config));
		$module=$module_class::instance_for_engine($engine, $config);
		// важно, что модуль инициализируется и регистрирует автозагрузку до того, как создаёт фронт, так как фронт может иметь зависимости от классов и интерфейсов, требующих автозагрузки данным же модулем.
		return $module->create_front($engine, $slug, $config);
	}
	
	public final static function dir_from_config($config)
	{
		if (is_string($config)) return $config;
		if (array_key_exists('dir', $config)) $dir=$config['dir']; // FIXME: заменить на ?? с PHP7
		else $dir=$config['name'];
		return $dir;
	}
	
	public final static function get_module_header_address($engine, $config)
	{
		$dir=static::dir_from_config($config);
		$result=$engine->modules_path.'/'.$dir.'/Module_'.$config['name'].'.php';
		return $result;
	}
	
	public final static function get_module_intro_address($engine, $config)
	{
		$dir=static::dir_from_config($config);
		$result=$engine->modules_path.'/'.$dir.'/intro.php';
		return $result;
	}
	
	public final static function retrieve_intro($engine, $config)
	{
		$dir=static::dir_from_config($config);
		$intros=$engine->obtain_static(__CLASS__, 'intros', []);
		if (array_key_exists($dir, $intros)) return $intros[$dir];
		
		$address=static::get_module_intro_address($engine, $config);
		if (!file_exists($address)) return new \Report_impossible('no_intro');
		$intro=include($address);
		$intros[$dir]=$intro;
		return $intro;
	}
	
	public final static function get_module_header_class($config)
	{
		if (array_key_exists('vendor', $config)) $vendor=$config['vendor']; // FIXME: заменить на ?? с PHP7
		else $vendor=static::DEFAULT_VENDOR;
		if ($config['name']==='Basic') $mid='Entlink'; else $mid=$config['name']; // FIXME: нужно позволить другие пространства имён?
		return '\\'.$vendor.'\\'.$mid.'\\Module_'.$config['name'];
	}
	
	// конфигурация передаётся только для извлечения папки.
	public final static function instance_for_engine($engine, $config)
	{
		if (static::instance_exists())
		{
			$instance=static::instance();
			if ($instance->engine!==$engine) die('DOUBLE ENGINE');
			return $instance;
		}
		
		$module=static::instance();
		$module->init_for_engine($engine, $config);
		return $module;
	}
	
	protected function init_for_engine($engine, $config)
	{
		if (!empty($this->engine)) die('DOUBLE MODULE INIT');
		$this->engine=$engine;
		$this->ns=(new \ReflectionClass($this))->getNamespaceName();
		$this->dir=static::dir_from_config($config);
		$this->register_at_engine();
	}
	
	protected function register_at_engine()
	{
		$this->register_namespace();
		$this->register_global_classes();
	}
	
	// отправляет движку сведения для быстрого алгоритма подключения классов.
	protected function register_namespace()
	{
		$this->engine->register_namespace($this->ns, $this);
	}
	
	protected function register_global_classes()
	{
		if (empty($this->global_classes)) return;
		$this->engine->register_global_class($this->global_classes, $this);
	}
	
	public function get_class_shorthands($class)
	{
		if (!array_key_exists($class, $this->class_shorthands)) return;
		return $this->class_shorthands[$class];
	}
	
	protected function create_front($engine, $slug, $config)
	{
		$class=$this->get_front_class_name($config);
		return new $class($this, $slug, $config);
	}
	
	protected function get_front_class_name($config)
	{
		return static::FRONT_CLASS_NAME;
	}
	
	// -------------------------
	// Автоподключение классов
	// -------------------------
	
	// быстрый алгоритм
	public function quick_autoload($relative_class_name)
	{
		if (!array_key_exists($relative_class_name, $this->quick_classes)) return;
		$file=$this->quick_classes[$relative_class_name];
		if ($file===true) $file=$relative_class_name;
		return $this->include_file($file);
	}
	
	// подключает указанный файл модуля.
	public function include_file($file)
	{
		if (empty($this->included_files)) $this->included_files=[];
		if (array_key_exists($file, $this->included_files)) return $this->included_files[$file];
		$path=$this->engine->modules_path.'/'.$this->dir.'/'.$file.'.php';
		if (file_exists($path)) include_once($path);
		$this->included_files[$file]=true;
		return true;
		/*
		echo('INCLUDING '.$path."\n");
		debug_print_backtrace();
		echo "\n\n----\n\n";
		*/
	}
	
	// сложный алгоритм автоподключения классов - по умолчанию отключён.
	public function normal_autoload($class_name) { }
	
}

// эта черта навешивается на модули, которые подразумевают сложный алгоритм автозагрузки классов, распознаваемых по началу их названия - например, Template_что-нибудь находится в локальном файле Template.php.

// не все классы обязаны подключаться с помощью этого механизма. если класс является строго зависимым от другого, он может быть объявлен в том же файле.
trait Module_autoload_by_beginning
{
	// public $classex= ...; // регулярное выражение типа /^(?<file>PageElement|Template|Page)_/ , с которым сличается название класса.
	public function quick_autoload($relative_class_name)
	{
		return parent::quick_autoload($relative_class_name) or $this->autoload_by_beginning($relative_class_name);
	}
	
	public function autoload_by_beginning($relative_class_name)
	{
		if (empty($this->classex_final)) $this->classex_final='/^'.$this->classex.'/';
		if (!preg_match($this->classex_final, $relative_class_name, $m)) return;
		if ( (!empty($this->class_to_file)) && (array_key_exists($m['file'], $this->class_to_file)) ) $file=$this->class_to_file[$m['file']];
		else $file=$m['file'];
		return $this->include_file($file);
	}
}

/*
модуль отвечает за:
- Автозагрузку классов, черт и интерфейсов (потому что классы регистрируются в глобальном пространстве; может быть только один класс с тем или иным названием).
- Создание фронта согласно конфигурации.

фронт отвечает за:
- Конфигурируемый функционал. Желательно, чтобы как можно больше вещей было конфигурируемо, так что большинство функционала должно быть здесь.
- Связь с модулем для доступа к неконфигурируемому функционалу.
- Связь с модулем для доступа к тому, что не нуждается в повторении (повторное включение файлов с определениями) или невозможно для повторения (классы, черты, интерфейсы).

Некоторые модули предназначены для работы с одним фронтом, а другие - со многими (можно иметь несколько копий игры).
*/

class ModuleFront
{
	use ArrayConfig;
	
	const
		STANDARD_PAGE_CLASS='\Pokeliga\Nav\Page_view_from_db';
	
	public
		$module,
		$engine,
		$slug,
		$config,
		$form_slugs=[], // slug=>form_class
		$type_slugs=[],	// slug=>entity_type_class
		$page_slugs=[];	// slug=>page_data
	
	// ---------------------
	// Инициализация
	// ---------------------
	
	public function __construct($module, $slug, $config)
	{
		$this->module=$module;
		$this->slug=$slug;
		$this->apply_config($config);
		$this->setup();
	}
	
	protected function setup()
	{
		$this->engine=$this->module->engine;
		$this->register_as_templater();
		$this->register_form_slugs();
	}
	
	public function default_config()
	{
		$module=$this->module;
		return $module::$default_config;
	}
	
	public function register_as_templater()
	{
		if ($this instanceof \Pokeliga\Templater\Templater) $this->engine->register_templater($this);
		if ($this instanceof \Pokeliga\Nav\RouteMapper) $this->engine->register_route_mappers($this);
	}
	
	public function register_form_slugs()
	{
		foreach ($this->form_slugs as $slug=>$form_class)
		{
			$this->engine->register_form_slug($slug, $this);
		}
	}
	
	// -------------------------------------
	// Поставка форм
	// -------------------------------------
	
	// возвращает класс, а не объект формы потому, что класс может создать объект несколькими способами, и необходимый способ может знать только вызывающий данную функцию код.
	public function get_form_class_by_slug($slug)
	{
		if (!array_key_exists($slug, $this->form_slugs)) return new \Report_impossible('bad_form_slug', $this);
		return prepend_namespace($this->form_slugs[$slug], $this->module->ns);
	}
	
	// -------------------------------------
	// Создание страниц
	// -------------------------------------
	
	public function spawn_page($slug, $parts=[], $route=[])
	{
		$route['module_slug']=$slug;
		if (empty($parts))
		{
			$route['url_formation']=Router::URL_MODULE;
			return $this->spawn_default_page($route);
		}
		$action=array_shift($parts);
		if (array_key_exists($action, $this->type_slugs)) return $this->spawn_type_page($action, $parts, $route);
		if (array_key_exists($action, $this->page_slugs)) return $this->spawn_slug_page($action, $parts, $route);
	}
	
	public function spawn_default_page($route=[]) { }
	
	public function spawn_type_page($type_slug, $parts=[], $route=[])
	{
		$type_class=$this->type_slugs[$type_slug];
		$page=$type_class::spawn_page($type_slug, $parts, $route);
		return $page;
	}
	
	public function spawn_slug_page($page_slug, $parts=[], $route=[])
	{
		if (!array_key_exists('module_slug', $route)) $route['module_slug']=$this->default_module_slug;
		$route['module_action']=$page_slug;
		$route['url_formation']=Router::URL_MODULE_ACTION;
		return static::spawn_page_by_data($this->page_slugs[$page_slug], $parts, $route, static::STANDARD_PAGE_CLASS);
	}
	
	// для методов, не зависящих от настройки, например, связанных с классами.
	public function __call($method, $args)
	{
		return $this->module->$method(...$args);
	}
}

?>