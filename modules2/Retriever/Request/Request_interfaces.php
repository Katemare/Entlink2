<?
namespace Pokeliga\Retriever;

// смысл этого интерфейса состоит в сообщении информации модификатору запроса (Request_reuser), который хочет получить значения групповых функций. Логика следующая: если запрос не имеет интерфейса Request_groupable, то даже если исходный запрос действует как мультитон, то групповой запрос выполняется индивидуально. Если же интерфейс есть и отличает положительно на is_groupable, то групповые запросы могут быть скомпанованы в один вида SELECT функции, ключ `group_key` FROM... GROUP BY группировка. Каждая отдельная группа (это обязательно!) отвечает за набор, который бы исходный запрос выдал по отдельному ключу для get_data.
interface Request_groupable
{
	public function group_fields(); // то, что в Query нужно вставить в элемент 'group'
	
	public function group_key(); // то, что нужно ставить в поля помимо групповых функций, чтобы идентифицировать группы в формате поля для 'fields' в SELECT.
	
	public function group_key_value_from_get_data_args($get_data_args); // какому значению группового ключа соответствуют аргументы get_data.
	
	public static function is_groupable($get_data_args);
}

?>