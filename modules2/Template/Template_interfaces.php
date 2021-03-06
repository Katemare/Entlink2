<?
namespace Pokeliga\Template;

// объекты, соответствующие этому интерфейсу, могут реагировать на ключевые конструкции в текстовых шаблонах. 
interface Templater
{
	// этот метод должен выдавать конечный результат; либо задачу, результатом работы которой становится то, что нужно показать пользователю на месте данного кода и командной строки. в качестве кода принимается строка или набор обращений - стёком (например, ['pokemon', 'species', 'egg_pic', 'name'], что соответствует текстовой записи pokemon.species.egg_pic.name).
	// итого результатом могут быть такие ответы: строка; число; объект класса Task (часто Template); или отчёт \Report_impossible; или null (ничего подходящего не найдено, что может позволить поиску продолжиться).
	public function template($code, $line=[]);
}

interface CodeHost
{
	public function get_codefrag($id); // создаёт экземпляр инструкции.
	
	public function codefrag($type, $id); // предоставляет данные для создания инструкции.
}

?>