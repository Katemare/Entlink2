<?
// черта, облегчающая создание объектов класса, когда мы знаем только часть названия класса. Например, классы валидаторов имеют названия Validator_такой-то и Validator_сякой, но модели и методы возвращают только части "такой-то" и "сякой". Эта черта позволяет избежать операции 'Validator_'.$keyword, сохраняя прототипы в массив с ключами $keyword и клонируя их.
trait Prototyper
{
	static
		// $prototype_class_base='replace_me',
		$prototypes=[];
	
	public static function get_prototype($keyword)
	{
		if (!array_key_exists($keyword, static::$prototypes))
		{
			$class=static::compose_prototype_class($keyword);
			$prototype=new $class();
			static::$prototypes[$keyword]=$prototype;
		}
		return static::$prototypes[$keyword];
	}
	
	public static function from_prototype($keyword)
	{
		return clone static::get_prototype($keyword);
	}
	
	public static function compose_prototype_class($keyword)
	{
		return static::$prototype_class_base.$keyword;
	}
}

// то же, что предыдущая черта, но когда название класса совпадает с ключевыми словом.
trait Prototyper_bare
{
	use Prototyper;
	
	public static function compose_prototype_class($keyword)
	{
		return $keyword;
	}
}

// всякий раз, когда нам нужно вернуть данные о прошедшей операции вместо обычного ответа, мы возвращаем объект класса, унаследованного от этого. Если вернулся объект класса Report, то это всегда отчёт об операции и никогда - не посредственный результат выполнения метода. Это избавляет от неоднозначности таких ответов как, например, false (результат - false или же операция не удалась?).
class Report
{
	public
		$source=null;
		
	public function human_readable()
	{
		return get_class($this);
	}
}

class Report_tasks extends Report
{
	public
		$tasks=[],
		$process=null;
	
	public function __construct($tasks)
	{
		$this->tasks=$tasks;
	}
	
	public function create_process()
	{
		if ($this->process===null) $this->process=new Process_collection_absolute($this->tasks);
		return $this->process;	
	}
	
	public function complete()
	{
		return $this->create_process()->complete();
	}
	
	public function register_dependancies_for($task, $identifier=null)
	{
		$task->register_dependancies($this->tasks, $identifier);
	}
	
	public function report()
	{
		if ($this->process===null) return new Report_impossible('no_process');
		return $this->process->report();
	}
	
	public function human_readable()
	{
		/*
		$result=[];
		foreach ($this->tasks as $task)
		{
			$result[]=$task->human_readable();
		}
		*/
		return parent::human_readable().': '.count($this->tasks).' tasks'; //.implode(', ', $result);
	}
}

// облегчает возвращение отчёта с одной операцией и не вынуждает прибегать к методам отчёта для его обработки. Движок постоянно обменивается отчётами и обрабатывает их, так что анализ отчёта следует свести до базовых операций принадлежности к классу (instanceof) и извлечения параметров.
class Report_task extends Report_tasks
{
	public $task=null;
	
	public function __construct($task)
	{
		parent::__construct([$task]);
		$this->task=$task;
	}
	
	public function create_process()
	{
		if (is_null($this->process)) $this->process=$this->task->create_process();
		return $this->process;
	}
	
	public function complete()
	{
		return $this->create_process()->complete();
	}
	
	public function register_dependancies_for($task, $identifier=null)
	{
		$task->register_dependancy($this->task, $identifier);
	}
}

class Report_final extends Report
{
}

class Report_impossible extends Report_final
{
	public
		$errors=[];
		
	public function __construct($errors=null)
	{
		if (is_null($errors)) return;
		if (is_array($errors)) $this->errors=$errors;
		else $this->errors=[$errors];
	}
	
	public function human_readable()
	{
		return parent::human_readable().': '.var_export($this->errors, true);
	}

}

class Report_success extends Report_final
{
}

class Report_in_progress extends Report {}

class Report_resolution extends Report_success
{
	public $resolution;
	
	public function __construct($resolution=null)
	{
		$this->resolution=$resolution;
	}
	
	public function human_readable()
	{
		if (is_object($this->resolution)) $details=get_class($this->resolution);
		else
		{
			ob_start();
			echo($this->resolution);
			$export=ob_get_contents();
			ob_end_clean();
			$details=htmlspecialchars(substr($export, 0, 200));
		}
		
		return parent::human_readable().': '.$details;
	}
}

trait Report_spawner
{
	public function sign_report($report)
	{
		global $debug;
		if (!$debug) return $report;
		if ($report->source!==null) {vdump($this); die ('SIGNED REPORT'); } // в будущем, возможно, будет клонировать и переподписывать отчёт.
		$report->source=$this;
		return $report;
	}
}

// функционал классов, существующих в единственном экземпляре.
trait Singleton
{
	static $instance=null;
	// ВАЖНО! у каждого класса, наследующего Singleton (то есть если один из родителей использовал Singleton), нужно повторить строчку выше, иначе обращение static::$instance будет к ближайшему объявлению родительских классов.
	
	public static function instance()
	{
		if (static::$instance!==null) return static::$instance;
		static::$instance=new static;
		return static::$instance;
	}
	
	public static function deinstance()
	{
		static::$instance=null;
	}
	
	public static function instance_exists()
	{
		return static::$instance!==null;
	}
	
	public static function is_ton() { return true; }
}

// функционал классов, существующих во многих экземплярах, но идентифицируемых по ключу, а не создаваемых заново каждый раз.
// не все подобные классы используют эту черту, потому что не всегда индентификация идёт по ключу.
trait Multiton
{
	static $instances=[];
	// ВАЖНО! см. примечание к Singleton'у, выше.
	
	public static function make_Multiton_key($args)
	{
		if (!is_array($args)) { die('BAD MULTITON ARGS'); }
		elseif (count($args)==0) $key='';
		else $key=array_reduce($args, 'flatten_Multiton_args');
		return $key;
	}
	
	public static function instance()
	{
		$args=func_get_args();
		$key=static::make_Multiton_key($args);
		if ( ($key!==null) && (array_key_exists($key, static::$instances)) )
		{
			$instance=static::$instances[$key];
		}
		else
		{
			$class_name=static::make_Multiton_class_name($args);
			$class=new ReflectionClass($class_name);
			$instance=$class->newInstanceArgs($args);
			if ($key!==null)
			{
				static::$instances[$key]=$instance;
			}
		}
		static::check_instance($instance, $args);
		return $instance;
	}
	
	// выполняется всякий раз при запросе инстанса, не важно, извлечён он из кэша или создан новый.
	public static function check_instance($instance, $args) { }
	
	public static function make_Multiton_class_name($args)
	{
		return get_called_class();
	}
	
	public static function instance_exists()
	{
		$args=func_get_args();
		$key=static::make_Multiton_key(...$args);
		if ($key===null) return false;
		return array_key_exists($key, static::$instances);
	}
	
	public static function is_ton() { return true; }
}

function flatten_Multiton_args($carry, $item)
{
	if ($item===true) $flat='true';
	elseif ($item===false) $flat='false';
	elseif ($item===null) $flat='null';
	elseif (is_array($item)) $flat='['.array_reduce($item, 'flatten_Multiton_args').']';
	elseif (is_object($item))
	{
		if ($item instanceof Multiton_argument) $flat=$item->Multiton_argument();
		elseif ($item instanceof Closure) $flat=spl_object_hash($item); // и остаётся надеяться, что не появится другая анонимная функция с таким же хэшем... увы, функционал расширить не получится.
		else { vdump($item); die('UNIMPLEMENTED YET: Multiton object keys'); }
	}
	else $flat=$item;
	
	if ($carry===null) return $flat;
	return $carry.'|'.$flat;
}

// эти объекты могут быть частью аргументов для Мультитона.
interface Multiton_argument
{
	public function Multiton_argument();
}

trait Noton // реагирует на instance, но не сохраняет копию в кэше.
{
	// действует точно так же, как new Request...(аргументы). не дублирует функционал make_Multiton_class_name (например, возвращающий Request_by_id вместо Request_by_field, когда поле id), но потери пока что невелики.
	public static function instance()
	{
		$class_name=get_called_class();
		$args=func_get_args();
		$instance=new $class_name(...$args);
		return $instance;
	}
	
	public static function is_ton() { return false; }
}

// проверять на актуальные механизмы Мультитона и Синглтона нужно конструкцией (method_exists($имя_класса_или_объект, 'is_ton')) && ($имя_класса_или_объект->is_ton())

// даёт каждому объекту данного класса и его наследников уникальный айди. Полезно для того, чтобы проверять наличие объекта в списках - вместо поиска в массиве достаточно проверить наличие числового ключа.
trait Object_id
{
	static $next_object_id=1; // если у унаследованного класса переобъявить этот параметр, то у него и его наследников будет собственная область айди!
	
	public $object_id;
	
	public function generate_object_id()
	{
		$this->object_id=static::$next_object_id++;
	}
}

// это оболочка для Closure, позволяющая указать добавочные аргументы и запомнить дополнительные параметры вызова. В принципе может работать и с массивами-callback'ами, но Closure быстрее и поэтому предпочтительней. А там, где можно обойтись Closure без Call или вообще прямым вызовом, следует обходиться ими.
class Call
{
	use Object_id;
	
	public
		$callback,
		$standard_args=[],
		// $strip_arguments=0
		$post_args=[];
		
	public function __construct($callback, ...$standard_args)
	{
		$this->callback=$callback;
		$this->standard_args=$standard_args;
		$this->generate_object_id();
	}
	
	public function __invoke(...$args)
	{
		$this->before_invoke();
		//if ($this->strip_arguments>0) array_slice($args, $this->strip_arguments);
		$this->process_args();		
		
		$callback=$this->callback;
		return $callback (...$this->standard_args, ...$args, ...$this->post_args);
	}
	
	public function process_args()
	{
	}
	
	public function before_invoke()
	{
	}
	
	public function bindTo($object) // повторяет одноимённый метод callback, но не полностью, а в рамках, использующихся в данном движке.
	{
		$new_call=clone $this;
		if ($new_call->callback instanceof Closure) $new_call->callback=$this->callback->bindTo($object);
		elseif ( (is_array($new_call->callback)) && (is_object($new_call->callback[0])) ) $new_call->callback[0]=$object;
		else die ('CANT BIND OBJECT');
		return $new_call;
	}
	
	public function __clone()
	{
		$this->generate_object_id();
	}
}

// эта черта навешивается на классы, которые накапливают вызовы и совершают их после некоторого события.
// эта альтернативная система крючкам, гарантирующая, что функции будут вызваны после конкретных процедур конкретного объекта.
// крючки же создаются и обрабатываются с некоторой неопределённостью того, кто ответит и в каком порядке.
trait Caller
{
	public $caller_calls=[];

	public function make_calls($pool='default', ...$args)
	{
		if (!array_key_exists($pool, $this->caller_calls)) return;
		foreach ($this->caller_calls[$pool] as $call)
		{
			$this->make_call($call, $args);
		}
	}

	public function make_final_calls($pool='default', ...$args)
	{
		$this->make_calls($pool, ...$args);
		unset($this->caller_calls[$pool]);
	}
	
	public function make_call($call, $args=[])
	{
		$call(...$args);
	}
	
	public function add_call($call, $pool='default')
	{
		if (empty($call)) return;
		if (! ($call instanceof Call) ) $call=new Call($call);
		if (!array_key_exists($pool, $this->caller_calls)) $this->caller_calls[$pool]=[$call->object_id=>$call];
		else $this->caller_calls[$pool][$call->object_id]=$call;
		return $call->object_id;
	}
	
	public function remove_call($id, $pool)
	{
		if (!array_key_exists($pool, $this->caller_calls)) return;
		if ($id instanceof Call) $id=$id->object_id;
		unset($this->caller_calls[$pool][$id]);
	}
	
	// $calls в качестве ключей должно иметь айди задач!
	public function add_calls($calls, $pool='default')
	{
		if (empty($call)) return;
		if (!array_key_exists($pool, $this->caller_calls)) $this->caller_calls[$pool]=$calls;
		else $this->caller_calls[$pool]+=$calls;
	}
}

// дополнительно передаёт в вызовы ссылку на себя.
trait Caller_backreference
{
	use Caller;
	
	public function make_call($call, $args=[])
	{
		$call($this, ...$args);
	}
}

// это класс движка, являющийся основой всей системы. он создаётся первым и существует в одном экземпляре.
// он содержит подключение модулей и некоторые функции, общие для системы.
class Engine
{
	const
		COMPACTER_KEY='__compacter'; // если первый элемент массива имеет этот ключ с содержимым true, он обрабатывается как аргументы для компактера. не следует использовать этот ключ с иной целью в моделях и командных строках!
		
	use Caller, Singleton;
	// движок объявлен как синглтон исключительно потому, что пока не найден способ позволить всем объектам (сущностям, задачам, значениям...) обращаться к движку, в контексте которого они были созданы, и при этом не хранить тысячу ссылок на него и не передавать их при создании. Ситуация, когда требуется две копии движка, представляется очень маловероятной, а значит, не требуется решать её сейчас, когда неизвестно практическое применение.
	
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
		if (array_key_exists('Retriever', $this->modules))
		{
			$this->retriever=new Retriever();
			$this->retriever->setup();
		}
		
		//$this->err_handler=new ErrorHandler(); // временно отключено
	}
	
	public function server_address($path)
	{
		return $this->docroot.'/'.((empty($this->install_dir))?(''):($this->install_dir.'/')).$path; // COMP: возможно, на других системах следует добавить слеш
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
	
	// -------------------------
	// Работа с URL
	// -------------------------
	
	// возвращает базовый адрес плюс строковую надстройку.
	public function url($add='')
	{
		$result=$this->base_url.$add;
		return $result;
	}
	
	public function module_url($module, $add='')
	{
		return $this->base_url.$this->config['modules_dir'].'/'.$module.'/'.$add;
	}
	
	public function module_address($module, $add='')
	{
		return $this->modules_path.'/'.$module.'/'.$add;
	}
	
	// возвращает текущий адрес плюс аргументы
	public function url_args_only($args=null)
	{
		if ($args===null) return $this->url_self();
		$args=$this->compose_url_args($args);
		$result=$this->url_self().$args;
		return $result;
	}
	
	// возвращает строго текущий адрес
	public function url_self()
	{
		$result=$this->host.$this->current_url;
		return $result;
	}	
	
	// превращает массив аргументов для URL в их строковую запись для адресной строки.
	public function compose_url_args($args=null)
	{
		if ($args===null) $result='';
		elseif (is_string($args)) $result=$args;
		elseif (is_array($args))
		{
			$result=array();
			foreach ($args as $arg=>$value)
			{
				$result[]=$arg.'='.urlencode($value);
			}
			if (count($result)<1) $result='';
			else $result='?'.implode('&', $result);
		}
		return $result;
	}
	
	public function compose_url($url, $args)
	{
		if ($url===null) $url=$this->url_self();
		$parsed_url=parse_url($url);
		if (array_key_exists('query', $parsed_url)) parse_str($parsed_url['query'], $query);
		else $query=[];
		foreach ($args as $key=>$arg)
		{
			if ($arg!==null) continue;
			unset($query[$key]);
			unset($args[$key]);
		}
		$args=array_merge($query, $args);
		
		$parsed_url['query']=http_build_query($args);
		return $this->unparse_url($parsed_url);
	}
	
	public function unparse_url($parsed_url)
	{
		$scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
		$pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
		$pass     = ($user || $pass) ? "$pass@" : '';
		$path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
		$query    = !empty($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
		$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
		return "$scheme$user$pass$host$port$path$query$fragment";
	} 
	
	public function redirect($address, $args=null)
	{
		if (is_array($args)) $args=$this->compose_url_args($args);
		if (is_string($args)) $address.=$args;
		Header('Location:'.$address);
		exit;
	}
	
	public function get_back($add=null)
	{
		global $_SERVER;
		$back=$_SERVER['HTTP_REFERER'];
		$this->redirect($back, $add);
	}
	
	// меняет местами две переменные. в php всё ещё нет такой функции...
	public function swap(&$var1, &$var2)
	{
		$temp=$var1;
		$var1=$var2;
		$var2=$temp;
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
}

// класс, от которого образуются все классы модулей.
class Module
{
	use Singleton, Report_spawner;
	
	public
		$engine,
		$name=null, // хорошо бы устанавливать имя автоматически, но это бы потребовало строковый анализ названия класса - лучше один раз указать и не загружать процессор.
		$quick_classes=[], // список соответствий "название класса - название файла" (без расширения).
		$track_code=null,
		
		$form_slugs=[]; // slug=>form_class
	
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
		$this->register_quick_classes();
		$this->register_track();
		$this->register_as_templater();
		$this->register_form_slugs();
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


function Engine()
{
	return Engine::instance();
}

function Retriever()
{
	return Engine::instance()->retriever;
}
?>