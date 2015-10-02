<?

namespace Pokeliga\Task;

trait Task_coroutine
{
	private
		$coroutine_need,
		$coroutine_generator,
		$coroutine_init=false;
	
	private function coroutine_generator()
	{
		if ($this->coroutine_generator===null) $this->coroutine_generator=$this->get_coroutine_generator();
		return $this->coroutine_generator;
	}
	
	protected function get_coroutine_generator()
	{
		$generator=$this->coroutine();
		if (!($generator instanceof \Generator)) throw new \Exception('bad generator');
		return $generator;
	}
	
	protected abstract function coroutine();
	
	public function progress()
	{
		if ($this->coroutine_need!==null)
		{
			if ($this->coroutine_need->to_abort())
			{
				$this->impossible($this->coroutine_need->abort_reason());
				return;
			}
			else $this->coroutine_need=null;
		}
		$generator=$this->coroutine_generator();
		if ($this->coroutine_init)
		{
			if ($generator->valid()) $generator->next();
			else
			{
				$this->impossible('generator_abruptly_closed');
				return;
			}
		}
		else $this->coroutine_init=true;
		$this->process_generator_state();
	}
	
	protected function process_generator_state($effort=false)
	{
		$state=$this->coroutine_generator->current();
		if ($state instanceof \Generator) $state=new Need_subroutine($state);
		\process_mediator($state);
		if ($state instanceof Need) $this->register_need($state, $effort);
		elseif ($state instanceof \Report_delay) $this->register_dependancies($state);
		elseif ($state instanceof \Report_final) $this->finish($state);
		elseif (\is_mediator($state)) throw new \Pokeliga\Entlink\UnknownMediatorException();
		else $this->finish_with_resolution($state);
	}
	
	protected function register_need($need, $effort=false)
	{
		if ($effort) $need->max_requestless_progress();
		else $need->max_standalone_progress();
		
		if ($need->mandatory and $need->failed())
		{
			$this->impossible($need);
			return;
		}
		$this->register_dependancy($need); // "зарегистрировать" зависимость нужно даже при завершённой необходимости потому, что у задачи может быть какое-то поведение в ответ на завершённые зависимости.
		
		if (!$need->completed()) $this->coroutine_need=$need;
	}
}

class Coroutine extends Task
{
	use Task_coroutine;
		
	// этот метод предназначен для оборачивания вызова, который может быть генератором, а может и не быть. поэтому только в случае, если аргумент - генаратор, он возвращает что-то своё. это своё - это либо результат работы генератора (если тому ничего не мешает или если не требуется запросов к БД), невозможность завершить генератор или же отчёт-обещание с собой.
	public static function wrap($generator)
	{
		if (!($generator instanceof \Generator)) return static::wrap_not_generator($generator);
		$routine=static::from_generator($generator);
		return $routine->init();
	}
	
	protected static function wrap_not_generator($val) { return $val; }
	
	protected static function from_generator($generator)
	{
		return new static($generator);
	}
	
	public function __construct($generator)
	{
		parent::__construct();
		$this->coroutine_generator=$generator;
	}
	
	private function init()
	{
		$this->coroutine_init=true;
		if (!$this->coroutine_generator()->valid()) { xdebug_print_function_stack(); return $this->report_impossible('empty_generator'); }
		$this->process_generator_state(true);
		
		return $this->requestless_resolution_or_promise();
	}
	
	protected function coroutine() { die('COROUTINE ALREADY PROVIDED'); }
}

// Генератор представляет собой не завершённый вызов метода или функции. такие методы и функции предполагают возможность паузы в выполнении (содержат ключевое слово yield). возможно, вызов можно завершить сразу, даже не встретив yield! или встретить yield, возвращающую Need, но эта нужда всего лишь номинальна и тут же выполняется без дополнительных усилий. но иногда для преодоления yield'а нужны дополнительные действия и даже запросы.
// так или иначе, Генератор сам по себе не может быть результатом и считается медиатором. но он не может реализовывать интерфейс Mediator, поскольку это стандартный класс, так что на помощь приходит слелующий класс. Coroutine не является Медиатором потому, что может использоваться в случае, когда сама является искомой задачей-результатом.
class GenMediator extends Coroutine implements \Pokeliga\Entlink\Mediator, \Pokeliga\Entlink\FinalPromise
{
	protected static function wrap_not_generator($val)
	{
		throw new \Exception('not generator');
	}
}

class Need_subroutine extends Need_one
{
	public function __construct($gen, $mandatory=true)
	{
		parent::__construct(Coroutine::wrap($gen), $mandatory);
	}
}
?>