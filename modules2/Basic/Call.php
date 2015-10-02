<?

namespace Pokeliga\Entlink;

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

?>