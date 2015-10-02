<?
namespace Pokeliga\Entlink;

/**
* Для объектов, содержащих список ошибок.
*/
interface ErrorsContainer
{
	/**
	* Возвращает список содержащихся ошибок.
	* @return array Массив ошибок (пока текстовых).
	*/
	public function get_errors();
}

/**
* Обозначает обещание что-нибудь выполнить или получить рано или поздно.
* Как интерфейс, обещание даёт возможность узнать состояние процесса, получить отчёт, результат или потребовать результат сейчас же. в целом, однако, обещание ничего не говорит о возможности объекта самому выполнить своё обещание.
* В отличие от стандартного соглашения Promise в других движках, данный не гарантирует, что обещание не может быть "сброшено" до нуля и повторено. Это с осторожностью применяется для задач, например, запросов к БД (Request), которые не выигрывают от соблюдения такого соглашения. Иначе их пришлось бы разделять на два объекта - контейнер и задачи.
*/
interface Promise extends ErrorsContainer
{
	/**
	* Да или нет - завершено ли обещание?
	* @return bool
	*/
	public function completed();
	
	/**
	* Возвращает да, если обещание завершено и успешно.
	* @return bool
	*/
	public function successful();
	
	/**
	* Возвращает да, если обещание завершено и провалено.
	* @return bool
	*/
	public function failed();
	
	/**
	* Возвращает результат либо отчёт о невозможности отдать таковой. Если обещание не завершено, то всегда второе.
	* @return mixed|Report_impossible Либо не-медиатор, либо Report_impossible.
	*/
	public function resolution();
	
	/**
	* Запрашивает результат немдленно и побуждает обещание выполниться, если необходимо и возможно.
	* @return mixed|Report_impossible Либо не-медиатор, либо Report_impossible.
	*/
	public function now();
	
	/**
	* Регистрирует зависимость, в результате выполнения которой completed() будет отвечать true. в том числе регистрирует заведомо провальные или выполненные зависимости, потому что задачи прогоняют их через свою логику и могут сделать для себя какие-то выводы.
	* @param \Pokeliga\Task\Task $task Задача, у которой следует определить зависимости.
	* @param null|string|int|array $identifier Идентификаторы, по которым при желании можно отличить одну зависимость от другой.
	* @see \Pokeliga\Task\Task::register_dependancy() О применении идентификаторов зависимостей.
	*/
	public function register_dependancy_for($task, $identifier=null);
	
	/**
	* Конвертирует обещание в задачу, если возможно.
	* Если обещание не выполнено, то оно должно зарегистрировать именно \Pokeliga\Task\Task, являющийся собственно обещанием или эквивалентный данному обещанию. В противном случае задача, для которой регистрируется зависимость, не будет знать, что ей делать.
	* @return \Pokeliga\Task\Task Задача, к которой сводится обещание.
	* @throws \Exception Если обещание уже завершено.
	*/
	public function to_task();
}

/**
* Этому интерфейсу соответствуют обещания, предназначенные для получения итогового результата, а не, к примеру, запрос промежуточных данных.
* Может быть некоторая путаница с названиями, поскольку Promise'ом является всё, у чего есть статус завершённости и способность заполнять зависимости, а у FinalPromise есть смысл: доставка искомого значения или выполнение искомой задачи. Во многих случаях, когда говорится об обещаниях, подразумеваются именно FinalPromise - тут надо смотреть на возвращаемые значения метода. Можно было бы переименовать FinalPromise в Promise, а оригинальнй Promise, скажем, в Intent, но это не является распространённым программным термином и потребует куда больше объяснений в докумнетации и по всему коду.
*/
interface FinalPromise extends Promise { }

/**
* Объекты, могущие указать пальцем на то или иное обещание.
*/
interface PromiseLink
{
	/**
	* Получение связанного обещания.
	* @return Promise
	*/
	public function get_promise();
}

/**
* Позволяет удовлетворить интерфейс обещания через ссылку на обещание.
*/
trait Promise_from_link
{
	public function completed()		{ return $this->get_promise()->completed(); }
	public function successful()	{ return $this->get_promise()->successful(); }
	public function failed()		{ return $this->get_promise()->failed(); }
	public function resolution()	{ return $this->get_promise()->resolution(); }
	public function now()			{ return $this->get_promise()->now(); }
	public function get_errors()	{ return $this->get_promise()->get_errors(); }
	public function register_dependancy_for($task, $identifier=null)
	{
		return $this->get_promise()->register_dependancy_for($task, $identifier);
	}
	public function to_task()
	{
		if ($this->completed()) throw new \Exception('completed Promise to task');
		$linked=$this->get_promise();
		if ($linked instanceof \Pokeliga\Task\Task) return $linked;
		else return $linked->to_task();
	}
}

?>