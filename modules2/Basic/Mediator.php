<?
namespace Pokeliga\Entlink
{
	interface Mediator { } // объекты классов, соответствующих этому интерфейсу, никогда не являются сами по себе искомым результатом. они доставщики результата, инструменты сообщения...
	// возможно, в конечном итоге можно будет добавить медиаторам какую-нибудь функцию разрешения в рамках объекта, а не принимающей его стороны, но не ясно, что тогда делать с генераторами. возможно, оборачивать их в какой-нибудь GeneratorMediator с помощью глобальной функции process_mediator/wrap_mediator - в общем, там посмотрим.
	
	class UnknownMediatorException extends \Exception
	{
		protected $message='Unknown mediator';
	}
}

namespace
{
	// приводит Генераторы к объектам и значениям, совместимым с движком.
	function process_mediator(&$obj)
	{
		// к стандартному классу Generator нельзя приклеить дополнительный интерфейс, так что следует пользоваться этой функцией.
		if ($obj instanceof \Generator) $obj=\Pokeliga\Task\GenMediator::wrap($obj); // может превратить объект в готовый результат.
	}
	
	function is_mediator($obj)
	{
		return $obj instanceof \Pokeliga\Entlink\Mediator or $obj instanceof \Generator;
	}
}

?>