<?
namespace Pokeliga\Entlink
{
	/**
	* Обозначает объект, не могущий быть содержимым Value и результатом сам по себе.
	* Обозначает объект, не могущий быть содержимым Value и результатом сам по себе: это механизм внутреннего сообщения движка, а не результат. Это доставщики результата, инструменты сообщения... Примеры: классы \Report, \Pokeliga\Task\Need и \Pokeliga\Task\GenMediator. По смыслу \Generator также является медиатором, однако это стандартный класс. Всякий метод, принимающий в качестве ввода произвольные медиаторы, должен проводить ввод через \process_mediator для преобразования \Generator в ...\GenMediator.
	*/
	interface Mediator { }
	// Возможно, в конечном итоге можно будет добавить медиаторам какую-нибудь функцию самостоятельнгого разрешения, но пока не ясно.

	/**
	* Исключение для случаев, когда обработка результата заканчивается, а медиатор ещё не обработан и метод не знает, что с ним делать.
	*/
	class UnknownMediatorException extends \Exception
	{
		protected $message='Unknown mediator';
	}
}

namespace
{
	/**
	* Приводит \Generator к объектам и значениям, совместимым с движком.
	*/
	function process_mediator(&$obj)
	{
		if ($obj instanceof \Generator) $obj=\Pokeliga\Task\GenMediator::wrap($obj); // может превратить объект в готовый результат.
	}
	
	/**
	* Проверяет, является ли объект медиатором. Если нет, то он является значением.
	*/
	function is_mediator($obj)
	{
		return $obj instanceof \Pokeliga\Entlink\Mediator or $obj instanceof \Generator;
	}
}

?>