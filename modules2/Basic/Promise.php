<?
namespace Pokeliga\Entlink;

// Promise означает обещание что-нибудь выполнить или получить рано или поздно. как интерфейс, оно даёт возможность узнать состояние процесса, получить отчёт, результат или потребовать результат сейчас же. в целом, однако, обещание ничего не говорит о возможности объекта самому выполнить своё обещание.

// в отличие от стандартного соглашения Promise в других движках, данный не гарантирует, что обещание не может быть "сброшено" до нуля и повторено. это с осторожностью применяется для задач, например, запросов к БД (Request), которые не выигрывают от соблюдения такого соглашения. иначе их пришлось бы разделять на два объекта - контейнер и задачи.
interface Promise
{
	public function completed();	// да или нет - завершено ли обещание?
	public function successful();	// возвращает да, если обещание завершено и успешно.
	public function failed();		// возвращает да, если обещание завершено и провалено.
	public function resolution();	// возвращает результат либо отчёт о невозможности отдать таковой. если обещание не завершено, то всегда второе.
	public function now();			// запрашивает результат немдленно и побуждает обещание выполниться, если необходимо и возможно.
	public function register_dependancy_for($task, $identifier=null); // регистрирует зависимость, в результате выполнения которой completed() будет отвечать true. в том числе регистрирует заведомо провальные или выполненные зависимости, потому что задачи прогоняют их через свою логику и могут сделать для себя какие-то выводы.
	// если обещание не выполнено, то оно должно зарегистрировать именно Task, являющийся обещанием или эквивалентный данному обещанию. в противном случае задача, для которой регистрируется зависимость, не будет знать, что ей делать.
	public function to_task(); // это следует спрашивать только у незавершённых обещаний! в целях регистрации зависимости.
}

// этому интерфейсу соответствуют обещания, предназначенные для сообщения итогового результата, а не, к примеру, запрос промежуточных данных.
interface FinalPromise extends Promise { }

interface PromiseLink
{
	public function get_promise();
}

trait Promise_from_link
{
	public function completed()		{ return $this->get_promise()->completed(); }
	public function successful()	{ return $this->get_promise()->successful(); }
	public function failed()		{ return $this->get_promise()->failed(); }
	public function resolution()	{ return $this->get_promise()->resolution(); }
	public function now()			{ return $this->get_promise()->now(); }
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