<?

namespace Pokeliga\Entlink;

// FIX! Мультитон, Синглтон и Нотон должны, во-первых, не пользоваться статистическими параметрами, а во-вторых - быть взаимозаменяемыми (чтобы не ломать класс при изменении характера его "тонности"). Задача этих черт - общность данных, например, складывающихся запросов к БД. В остальных случаях это и не используется, в общем-то.

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
trait Multiton
{
	public static function &Multiton_instances()
	{
		return static::Multiton_host()->get_Multiton_array(static::get_Multiton_pool());
	}
	
	public static function get_Multiton_pool()
	{
		return __CLASS__;
	}
	
	public static function Multiton_host()
	{
		return Engine();
	}
	
	public static function make_Multiton_key($args)
	{
		if (!is_array($args)) { die('BAD MULTITON ARGS'); }
		elseif (count($args)==0) $key='';
		else $key=array_reduce($args, '\Pokeliga\Entlink\flatten_Multiton_args');
		return $key;
	}
	
	public static function instance()
	{
		$args=func_get_args();
		$instances=&static::Multiton_instances();
		$key=static::make_Multiton_key($args);
		if ( ($key!==null) && (array_key_exists($key, $instances)) )
		{
			$instance=$instances[$key];
		}
		else
		{
			$class_name=static::make_Multiton_class_name($args);
			$class=new \ReflectionClass($class_name);
			$instance=$class->newInstanceArgs($args);
			if ($key!==null)
			{
				$instances[$key]=$instance;
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
		return array_key_exists($key, $instances);
	}
	
	public static function is_ton() { return true; }
}

// в виде отдельной функции потому, что у черт не может быть статических функций.
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

// эти объекты могут быть контейнерами для мультитонных запросов.
interface Multiton_host
{
	public function &get_Multiton_array($class_name);
}

trait Multiton_host_standard
{
	public
		$Multiton=[];
		
	public function &get_Multiton_array($class_name)
	{
		if (!array_key_exists($class_name, $this->Multiton)) $this->Multiton[$class_name]=[];
		return $this->Multiton[$class_name];
	}
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

?>