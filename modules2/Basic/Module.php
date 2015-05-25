<?

// класс, от которого образуются все классы модулей.
class Module
{
	use Singleton, Report_spawner, Page_spawner;

	const
		STANDARD_PAGE_CLASS='Page_view_from_db';
	
	public
		$engine,
		$name=null, // хорошо бы устанавливать имя автоматически, но это бы потребовало строковый анализ названия класса - лучше один раз указать и не загружать процессор.
		$quick_classes=[], // список соответствий "название класса - название файла" (без расширения).
		$track_code=null,
		
		$slug=null, // можно указать одно ключевое слово здесь или много - в массиве $slugs.
		$default_module_slug=null, // заполняется автоматически.
		$master_template_class=null,
		$form_slugs=[], // slug=>form_class
		$type_slugs=[],	// slug=>entity_type_class
		$page_slugs=[];	// slug=>page_data
	
	// ---------------------
	// Инициализация
	// ---------------------
	
	// этой статической функцией создаются все модули.
	public static function from_engine($module_name, $engine)
	{
		$init_file=$engine->modules_path.'/'.$module_name.'/Module_'.$module_name.'.php';
		include_once($init_file);
		$class='Module_'.$module_name;
		$module=new $class();
		return $module;
	}
	
	public function setup()
	{
		$this->register_module_slugs();
		$this->register_quick_classes();
		$this->register_track();
		$this->register_as_templater();
		$this->register_form_slugs();
	}
	
	public function register_module_slugs()
	{
		if ($this->slug===null) return;
		if (!empty($this->slug)) $slugs=[$this->slug];
		elseif (!empty($this->slugs)) $slugs=$this->slugs;
		$this->default_module_slug=reset($slugs);
		foreach ($slugs as $slug)
		{
			$this->engine->register_module_slug($slug, $this);
		}
	}
	
	// отправляет движку сведения для быстрого алгоритма подключения классов.
	public function register_quick_classes()
	{
		if (empty($this->quick_classes)) return;
		$this->engine->register_quick_classes(array_keys($this->quick_classes), $this);
	}
	
	public function register_track()
	{
		$track=null;
		if ($this->track_code===true) $track=$this->name;
		elseif (is_string($this->track_code)) $track=$this->track_code;
		if ($track===null) return;
		
		$this->engine->register_track($track, $this);
	}
	
	public function register_as_templater()
	{
		if (! ($this instanceof Templater)) return;
		$this->engine->register_templater($this);
	}
	
	public function register_form_slugs()
	{
		foreach ($this->form_slugs as $slug=>$form_class)
		{
			$this->engine->register_form_slug($slug, $form_class);
		}
	}
	
	// -------------------------
	// Автоподключение классов
	// -------------------------
	
	// быстрый алгоритм
	public function quick_autoload($class_name)
	{
		$file=$this->quick_classes[$class_name];
		$this->include_file($file);
	}
	
	// подключает указанный файл модуля.
	public function include_file($file)
	{
		static $included=[]; // в принципе, include_once и так проверяет, был ли файл уже поключён, но чтобы не совершать лишний раз file_exists.
		if (array_key_exists($file, $included)) return;
		$path=$this->engine->modules_path.'/'.$this->name.'/'.$file.'.php';
		if (file_exists($path)) include_once($path);
		$included[$file]=true;
	}
	
	// сложный алгоритм автоподключения классов - по умолчанию отключён.
	public function normal_autoload($class_name)
	{
		
	}
	
	// -------------------------------------
	// Взаимодействие с шаблонизатором
	// -------------------------------------
	
	// распознаёт ключевые слова шаблонизатора - не являются ли они глобальным обращением к одному из элементов модуля.
	public function create_PageElement($keyword, $subkeyword=null)
	{
		return null;
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
}

// эта черта навешивается на модули, которые подразумевают сложный алгоритм автозагрузки классов, распознаваемых по началу их названия - например, Template_что-нибудь находится в локальном файле Template.php.

// не все классы обязаны подключаться с помощью этого механизма. если класс является строго зависимым от другого, он может быть объявлен в том же файле.
trait Module_autoload_by_beginning
{
	// public $classex= ...; // регулярное выражение типа /^(?<file>PageElement|Template|Page)_/ , с которым сличается название класса.
	public function normal_autoload($class_name)
	{
		return $this->autoload_by_beginning ($class_name);
	}
	
	public function autoload_by_beginning($class_name)
	{
		if (preg_match($this->classex, $class_name, $m))
		{
			if ( (!empty($this->class_to_file)) && (array_key_exists($m['file'], $this->class_to_file)) ) $file=$this->class_to_file[$m['file']];
			else $file=$m['file'];
			$this->include_file($file);
			return class_exists($class_name, false);
		}
		return false;	
	}
}

?>