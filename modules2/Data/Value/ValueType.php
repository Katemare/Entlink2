<?
namespace Pokeliga\Data;

abstract class ValueType implements ValueModel, ValueLink, \Pokeliga\Template\Templater
{
	use ValueModel_from_link, \Pokeliga\Entlink\Shorthand;
	
	const
		DEFAULT_FIELD_TEMPLATE=null,			// можно указать поле ввода по умолчанию.
		DEFAULT_TEMPLATE_CODE='default',		// код шаблонизатора, выводящий шаблон по умолчанию.
		DEFAULT_TEMPLATE_CLASS=null,			// класс шаблона по умолчанию. если отсутствует, то выводится обработка методом for_display().
		DEFAULT_TEMPLATE_FORMAT_KEY='format';	// название агрумента шаблонизатора, задающего формат вывода.
	
	public
		$value;
	
	public function get_value() { return $this->value; }
	
	public function __call($method, $args)
	{
		if (method_exists($this->value, $method)) return $this->value->$method(...$args); // благодаря проверке исключает круговое обращение.
		xdebug_print_function_stack();
		die('UNKNOWN VALUETYPE METHOD: '.$method.' of '.get_class($this));
	}
	
	public function __get($name)
	{
		if (property_exists($this->value, $name)) return $this->value->$name;
	}
	
	public function content_changed($source) { }
	
	public static function for_value($value, $keyword)
	{
		$type=static::from_shorthand($keyword);
		$type->value=$value;
		return $type;
	}
	
	public function dispose() { }
	
	// эта функция строго конвертирует данные в свойственный формат. из этого метода не может вернуться значение, не соответствующее типу! либо соответствующее, либо \Report_impossible. соответствие типу означает, что значение может быть обработано типом: например, показано в соответствии с его шаблонами, проверено его валидаторами...
	// эта функция может учитывать модель (например, минимум и максимцм), но не проводит сложные операции по проверке действительности. в особенности он не проводит операции, требующие получения посторонних данных.
	public function to_good_content($content)
	{	
		$content=static::type_conversion($content);
		if ($content instanceof \Report_impossible) return $content;
		$content=$this->settings_based_conversion($content);
		
		return $content;
	}
	
	public static function type_conversion($content) { return $content; } // это приведение, не зависящее от модели и настроек объекта.
	// хотя этот метод статичный, не следует использовать его в отрыве от объекта. некоторые типы (ValueTypes) сформированы на основе настроек родительских типов, выраженных в виде параметров объекта (на случай, если перенастройка понадобится в ходе выполнения кода).
	public function settings_based_conversion($content) { return $content; } // это приведение, учитывающее модель и настройки объекта.
	
	public function list_validators() { }
	
	public function template_pre_filled($name, $line=[]) { }
	
	public function template($name, $line=[]) { }
	
	public function default_template($line=[])
	{
		if (static::DEFAULT_TEMPLATE_CLASS!==null)
		{
			$class=static::DEFAULT_TEMPLATE_CLASS;
			$task=$class::for_value($this->value, $line);
			return $task;
		}
		
		if (array_key_exists(static::DEFAULT_TEMPLATE_FORMAT_KEY, $line)) $format=$line[static::DEFAULT_TEMPLATE_FORMAT_KEY];
		elseif ($this->in_value_model(static::DEFAULT_TEMPLATE_FORMAT_KEY)) $format=$this->value_model_now(static::DEFAULT_TEMPLATE_FORMAT_KEY);
		else $format=null;
		$result=$this->for_display($format, $line);
		return $result;
	}
	
	public function for_display($format=null, $line=[])
	{
		return $this->value->content;
	}
	
	public function template_for_failed($name, $line=[]) { }
}

?>