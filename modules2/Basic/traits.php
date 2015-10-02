<?
namespace Pokeliga\Entlink;

/**
* Черта, облегчающая создание объектов класса, когда мы знаем только часть названия класса. Например, классы валидаторов имеют названия Validator_такой-то и Validator_сякой, но модели и методы возвращают только части "такой-то" и "сякой". Эта черта позволяет избежать операции 'Validator_'.$keyword, сохраняя прототипы в массив с ключами $keyword и клонируя их.
*/
trait Shorthand
{
	static
		// $prototype_class_base='replace_me',
		$shorthand_classes=null,
		$shorthand_class_base=null;
	
	public static function get_shorthand_class($keyword)
	{
		if (static::$shorthand_classes===null) static::gather_shorthand_classes();
		if (array_key_exists($keyword, static::$shorthand_classes))
		{
			if (($module=static::$shorthand_classes[$keyword]) instanceof Module) static::$shorthand_classes[$keyword]=$module->ns.'\\'.static::$shorthand_class_base.'_'.$keyword;
			return static::$shorthand_classes[$keyword];
		}
		throw new \Exception('UNKNOWN SHORTHAND for '.__CLASS__.': '.$keyword);
	}
	
	protected static function gather_shorthand_classes()
	{
		static::$shorthand_classes=[];
		preg_match('/[a-z\d_]+$/i', __CLASS__, $m);
		static::$shorthand_class_base=$m[0];
		foreach (Engine()->get_modules() as $module)
		{
			$shorthands=$module->get_class_shorthands(__CLASS__);
			if (!empty($shorthands)) static::$shorthand_classes+=array_fill_keys($shorthands, $module->module); //FIXME: нужно разобраться с двойственностью Module и ModuleFront
		}
	}
	
	public static function from_shorthand($keyword, ...$args)
	{
		// debug_print_backtrace();
		$class=static::get_shorthand_class($keyword);
		return new $class(...$args);
	}
	
	public static function is_shorthand($object, $shorthand)
	{
		return get_class($object)===static::get_shorthand_class($shorthand);
	}
}

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

trait StaticHost
{
	public
		$static_vars=[];
	
	public function &obtain_static($group, $var_name=null, $default=null)
	{
		if (!array_key_exists($group, $this->static_vars)) $this->static_vars[$group]=[]; // если нужно иное, чем пустой массив, то инициализировать нужно следующим методом.
		if (!array_key_exists($var_name, $this->static_vars[$group])) $this->static_vars[$group][$var_name]=$default;
		return $this->static_vars[$group][$var_name];
	}
	
	public function &obtain_static_group($group, $default=null)
	{
		if (!array_key_exists($group, $this->static_vars)) $this->static_vars[$group]=$default;
		return $this->static_vars[$group];
	}
}

trait StaticOwner
{
	public static function static_host_group() { return get_called_class(); }
	
	public static function static_host() { return Engine(); }
	
	public static function &get_static_from($host, $var_name, $default=null)
	{
		return $host->obtain_static(static::static_host_group(), $var_name, $default);
	}
	
	public static function &get_static_group_from($host, $default=null)
	{
		return $host->obtain_static_group(static::static_host_group(), $default);
	}
	
	public static function &get_static($var_name, $default=null)
	{
		return static::get_static_from(static::static_host(), $var_name, $default);
	}
	
	public static function &get_static_group($default=null)
	{
		return static::get_static_group_from(static::static_host(), $default);
	}
}

// такие объекты содержат посредника, который может как реализовывать интерфейс, так и нет. получается, неизвестно, реализует ли сам объект интерфейс - нужно спрашивать через вызов.
interface Interface_proxy
{
	public function implements_interface($interface_name);
}

trait ArrayConfig
{
	protected function &current_config()
	{
		return $this->config;
	}
	
	protected function default_config()
	{
		return static::$default_config;
	}
	
	public function apply_config($config)
	{
		if (($cur=&$this->current_config())!==null) $default=$cur; else $default=$this->default_config();
		$cur=$this->add_default_config($config, $default);
		$this->use_config($cur);
	}
	
	public function add_default_config($config, $default=null)
	{
		if ($default===null) $default=$this->default_config();
		if (empty($default)) return $config;
		foreach ($default as $key=>$val)
		{
			if (!array_key_exists($key, $config)) $config[$key]=$val;
			// если в конфигурации нет ключа из конфигурации по умолчанию, то он добавляется из конфигурации по умолчанию.
			
			elseif (is_array($config[$key]) and is_array($val)) $config[$key]=$this->add_default_config($config[$key], $val);
			// если в обеих конфигурациях есть один и тот же ключ и оба значения - массивы, то делается рекурсивный вызов.
			
			// FIXME: некорректно работает в случае, если в активной конфигурации сконфигурирован модуль по тому же ключу, что и в конфигурации по умолчанию, но предназначен принимать совершенно другие параметры или по-другому понимает те же параметры. в таком случае конфигурация замусоривается фрагментами таковой по умолчанию.
		}
		return $config;
	}
	
	public function use_config($config) { }
	
	public function get_config($code)
	{
		if (array_key_exists($code, $cur=$this->current_config())) return $cur[$code];
		die('UNIMPLEMENTED YET: missing config');
	}
}

?>