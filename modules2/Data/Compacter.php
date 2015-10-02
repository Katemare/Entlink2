<?
namespace Pokeliga\Data;

// хранит компактные данные для получения элемента кода, содержимого и прочих необходимостей. используется в инструкциях шаблонов и моделях данных. это лёгкий класс, наподобие \Report, который призван быть скорее эдаким типом переменных. пока к нему не обращаются, он занимает очень мало места, ничего не делает, не загружает процесс. когда он нужен, тогда он разворачивает ту конструкцию, которая требуется.
// здесь собраны компактеры, необходимые разным стандартным модулям - шаблонам, данным, сущностям...

abstract class Compacter
{
	public abstract function extract_for($host);
	
	// для преобразования специального массива в компактер. позволяет "инициализировать" компактерами поля в моделях значений.
	public static function by_mark($array)
	{
		$keyword=$array[0];
		$class='Compacter_'.$keyword;
		$args=$array[1];
		if (!is_array($args)) $args=[$args];
		$instance=new $class(...$args);
		return $instance;
	}
	
	public static function by_mark_and_extract($host, $array)
	{
		$compacter=static::by_mark($array);
		return $compacter->extract_for($host);
	}
	
	public static function recognize_mark($array)
	{
		return (is_array($array)) && (reset($array)===true) && (key($array)===Engine::COMPACTER_KEY);
	}
}

class Need_commandline extends \Pokeliga\Task\Need_all
{
	public
		$compacter_host;
		
	public function __construct($container, $compacter_host, $mandatory=true)
	{
		$this->compacter_host=$compacter_host;
		parent::__construct($container, $mandatory);
	}
	
	public function prepare_container_item($code, &$item)
	{
		if (Compacter::recognize_mark($item)) $item=Compacter::by_mark_and_extract($this->compacter_host, $item);
		return parent::prepare_container_item($code, $item);
	}
}

// возвращает CodeFragment с заданным айди у данного CodeHost'а
class Compacter_codefrag_reference extends Compacter
{
	public
		$frag_id;
	
	public function __construct($id)
	{
		$this->frag_id=$id;
	}
	
	public function extract_for($host)
	{
		if (!($host instanceof \Pokeliga\Template\CodeHost)) die ('BAD COMPACTER HOST');
		return $host->get_codefrag($this->frag_id);
	}
}

// возвращает задачу, получающую значение для шаблона (например, @pokemon.id) или готовое значение.
class Compacter_template_value extends Compacter
{
	public
		$track;
	
	public function __construct($track)
	{
		$this->track=$track;
	}
	
	public function extract_for($host)
	{
		if (!($host instanceof \Pokeliga\Template\Template_from_text)) die ('BAD COMPACTER HOST');
		// STUB: в будущем, может, будет работать с большим числом заказчиков, но пока что метод, к которому идёт обращение, есть только у текстовых шаблонов.
		return $host->value_task($this->track);
	}
}

// возвращает задачу, получающую подшаблон данного (например, {{pokemon.species.title}}) или сразу подшаблон.
class Compacter_template_keyword extends Compacter_template_value
{
	public
		$line;
	
	public function __construct($track, $line=[])
	{
		parent::__construct($track);
		$this->line=$line;
	}
	
	public function extract_for($host)
	{
		if (!($host instanceof \Pokeliga\Template\Template_from_text)) die ('BAD COMPACTER HOST');
		// STUB: в будущем, может, будет работать с большим числом заказчиков, но пока что метод, к которому идёт обращение, есть только у текстовых шаблонов.
		return $host->keyword_task($this->track, $this->line);
	}
}

// возвращает результат вызова к приведённому хосту (пока не используется). отличается от Call тем, что работает проще и может быть создан с помощью COMPACTER_KEY. Важно! поскольку этот компактер не раскрывается в задачу, результат вызова сохраняется вместо компактера, то есть фактически кэшируется!
class Compacter_method extends Compacter
{
	public
		$method,
		$args;
	
	public function __construct($method, $args=[])
	{
		$this->method=$method;
		$this->args=$args;
	}
	
	public function extract_for($host)
	{
		$callback=[$this->method_host($host), $this->method];
		return $callback(...$this->args);
	}
	
	public function method_host($host) { return $host; }
}

class Compacter_master_method extends Compacter_method
{
	public function method_host($host) { return $host->master; }
}

class Compacter_form_method extends Compacter_method
{
	public function method_host($host)
	{
		if ($host instanceof \Pokeliga\Data\Value) return $host->master->form;
		if ($host instanceof \Pokeliga\Form\Form) return $host;
		if ($host instanceof \Pokeliga\Form\FieldSet) return $host->form;
		die('BAD COMPACTER');
	}
}

// возвращает значение из того же набора; или задачу, в результате которой значение будет заполнено. работает даже для значений сущности. FIX: поскольку этот компактер не всегда раскрывается в задачу, результат вызова сохраняется вместо компактера, то есть фактически кэшируется! что в данном случае неприемлемо.
class Compacter_sibling_content extends Compacter
{
	public
		$sibling_code;
	
	public function __construct($code)
	{
		$this->sibling_code=$code;
	}
	
	public function extract_for($host)
	{
		if ($host instanceof ValueLink) $host=$host->get_value();
		if (!($host instanceof Value)) die ('BAD COMPACTER HOST');
		$master=$host->master;
		if (empty($master)) die ('NO VALUE MASTER');
		
		if (is_array($this->sibling_code)) $result=$this->track_from_master($master);
		else $result=$this->ask_master($master);
		if ($result instanceof \Report_resolution) return $result->resolution();
		elseif ($result instanceof \Report_promise) return $result->get_promise();
		return $result;
	}
	
	public function ask_master($master)
	{
		return $master->request($this->sibling_code);
	}
	
	// FIX! не гарантирует valid_content.
	public function track_from_master($master)
	{
		$task=new Task_resolve_value_track($this->sibling_code, $master);
		return new \Report_promise($task, $this);
	}
}

class Compacter_sibling_valid_content extends Compacter_sibling_content
{
	public function ask_master($master)
	{
		return $master->valid_content_request($this->sibling_code);
	}
}

?>