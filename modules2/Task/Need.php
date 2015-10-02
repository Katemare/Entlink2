<?

namespace Pokeliga\Task;

/*

это задачи, занимающиеся получением данных и выполнением заданий, нужных в Корутинах (Coroutines), да и просто. Идея в том, чтобы свести запрос данных к созданию и выполнению этого объекта.

WIP!!
*/

abstract class Need extends Task implements \Pokeliga\Entlink\Mediator
{
	public
		$mandatory=true;
		
	public function __construct($mandatory=true)
	{
		$this->mandatory=$mandatory;
		parent::__construct();
	}
	
	public function on_failed_dependancy($task, $identifier=null)
	{
		if ($this->mandatory) $this->impossible($task);
		parent::on_failed_dependancy($task, $identifier);
	}
}

// получает данные либо отказ (Report_impossible) из одного произвольного объекта. если объект требует выполнения, то выполняети получает данные.
// важно, что в данной необходимость нет такого места, чтобы она прерывалась до достижения субъектом разрешения. когда необходимость разрешена, то и субъект разрешён и имеет то же разрешение, что необходимость.
class Need_one extends Need
{
	public function __construct($item, $mandatory=true)
	{
		parent::__construct($mandatory);
		$this->prepare_item($item);
	}
	
	public function prepare_item($item)
	{
		\process_mediator($item);
		if ($item instanceof \Pokeliga\Entlink\Promise)
		{
			if ($item->completed())
			{
				$this->finish_by_promise($item);
				return;
			}
			$this->promise=$item;
			$item->register_dependancy_for($this);
		}
		elseif (!\is_mediator($item)) $this->finish_with_resolution($item);
		else throw new \Pokeliga\Entlink\UnknownMediatorException();
	}
	
	public function progress()
	{
		$this->finish_by_promise($this->promise);
	}
}

class Need_all extends Need implements \ArrayAccess
{
	public
		$required=[];
		
	public function __construct($container, $mandatory=true)
	{
		parent::__construct($mandatory);
		if (empty($container)) $this->success();
		else $this->receive_container($container);
	}
	
	public function receive_container($container)
	{
		$this->resolution=[];
		foreach ($container as $code=>$param)
		{
			$result=$this->prepare_container_item($code, $param);
			if ($result!==null) { $this->finish($result); return; }
			if ($param instanceof \Pokeliga\Entlink\Promise)
			{
				if ($this->mandatory and $param->failed()) { $this->impossible($param); return; }
				if ($param->completed()) $this->resolution[$code]=$param->resolution();
				else
				{
					$this->required[$code]=$param;
					$param->register_dependancy_for($this);
				}
			}
			else $this->resolution[$code]=$param;
		}
		if (empty($this->required)) $this->finish();
	}
	
	// превращает $param либо в результат (включая Report_impossible), либо в обещание, при выполнении которого появится результат.
	// если возвращает не null, то это данные для завершения задачи.
	public function prepare_container_item($code, &$param)
	{
		\process_mediator($param);
		if ($param instanceof \Pokeliga\Entlink\Promise or !is_mediator($param)) return;
		throw new \Exception('bad Need container item');
	}
	
	public function progress()
	{
		foreach ($this->required as $code=>$promise)
		{
			$this->resolution[$code]=$promise->resolution();
		}
		unset($this->required);
		$this->finish();
	}
	
	public function offsetExists($offset)
	{
		if ($this->successful()) return array_key_exists($offset, $this->resolution);
		else $this->cant_access_result();
	}
	public function offsetGet($offset)
	{
		if ($this->successful()) return $this->resolution[$offset];
		else $this->cant_access_result();	
	}
	public function offsetSet($offset, $value)
	{
		$this->result_is_readonly();
	}
	public function offsetUnset($offset)
	{
		$this->result_is_readonly();
	}
	public function cant_access_result()
	{
		throw new \Exception('Bad Need result access');
	}
	public function result_is_readonly()
	{
		throw new \Exception('Need result is read only');
	}
}

// делает вызов и повторяет его, выполняя поставляемые зависимости, пока не получит результат или же обещание, которое также выполняет. возвращает результат.
class Need_call extends Need
{
	use Task_coroutine;
	
	public $call;
	
	public function __construct($call, $mandatory=true)
	{
		$this->call=$call;
		parent::__construct($mandatory);
	}
	
	public function coroutine()
	{
		$call=$this->call;
		while ( ($result=$call() ) instanceof \Report_delay)
		{
			\process_mediator($result);
			yield $result;
			if ($result instanceof \Pokeliga\Entlink\FinalPromise) break;
		}
		if ($result instanceof \Pokeliga\Entlink\Promise) $this->finish_by_promise($result);
		elseif (\is_mediator($result)) throw new \Pokeliga\Entlink\UnknownMediatorException();
		else $this->finish_with_resolution($result);
	}
}

?>