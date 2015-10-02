<?
namespace Pokeliga\Retriever;

/**
* Задачи-запросы занимаются обращениями к БД и получением данных.
* Эти запросы по-особенному выполняются процессами (Process): процесс ждёт, пока все остальные задачи замрут в ожидании данных из БД и только потом отправляет все запросы к БД, которые к тому моменту, как ожидается, накопили достаточно ключей, чтобы совершить всё в несколько обращений. Например, если на странице показывается 20 пользователей, то вместо 20 запросов "получить данные пользователя А" процесс накопит запросы и отправит "получить данные пользователей А, Б В...".
* Накопление реализуется с помощью механизма мультитонов и синглтонов. Каждый класс запроса соответствует одному способу построения query. Параметры конструктора соответствуют особенностям построения query - при совпадении параметров конструктора используется один и тот же объект запроса, а не создаётся новый. Сами данные получаются с помощью метода get_data, который получает айди или другие ключи данных. Он либо сразу выдаёт уже имеющиеся данные, либо записывает их в ключи, которые следует получить.
* В отличие от большинства задач, запросы могут сбрасываться. Они делают это, когда получают новые ключи.
*/
abstract class Request extends \Pokeliga\Task\Task
{
	use \Pokeliga\Entlink\Noton, \Pokeliga\Task\Task_resetable;

	/**
	* @var GET_DATA_NOW Инструкция получить нехватающие данные немедленно.
	* @var GET_DATA_SET Инструкция получить нехватающие данные при первой возможности.
	* @var GET_DATA_SOFT Инструкция считать нехватающие данные отсутствующими.
	* @var KEYS_NUMBER Какое количество ключей принимает запрос.
	*/
	const
		// KEYS_NUMBER=... , // не определено для того, чтобы запросы с забытым числом ключей вызывали ошибку.
		GET_DATA_NOW=1,
		GET_DATA_SET=2,
		GET_DATA_SOFT=3;
	
	/**
	* @var $result Переменная для накапливания данных.
	*/
	protected
		$data;
		
	/**
	* Возвращает объект Query или соответствующего формата массив для получения нужных строчек БД.
	* @return \Pokeliga\Retriever\Query|array
	*/
	public abstract function create_query();
	
	/**
	* Сообщает, готов ли запрос к тому, чтобы сформировать query.
	* @return bool
	*/
	protected function is_ready() { return true; }
	
	public function progress()
	{
		$result=$this->is_ready();
		if ($result!==true)
		{
			$this->impossible($result);
			return;
		}
		
		$query=$this->create_query();
		$result=Retriever()->run_query($query);
		$success=$this->process_result($result);
		$this->data_processed();
		if ($success) $this->finish();
		else $this->impossible('request_failed');
	}
	
	/**
	* Обрабатывает результат, приходящий после прогона query.
	* Разбирает и записывает результат в хранилище в нужном формате.
	* @param mixed Результат работы Ретривера при передаче ему query для выполнения.
	* @return bool Возвращает истину, если результат обработан успешно, или ложь, если запрос следует считать провальным.
	*/
	protected function process_result($result)
	{
		return !($result instanceof \Report_impossible);
	}
	
	/**
	* Завершает получение данных.
	* Главная цель этого метода - записать, что текущие ключи были выполнены (и, возможно, зафиксировать, что они не были найдены), чтобы в следующий раз set_data() точно знал, повторять ли запрос.
	*/
	protected function data_processed() { }
	
	/**
	* Сообщает, ведётся ли поиск по уникальному полю и, соответственно, будет ли соответствовать каждому ключу строго одна запись.
	* @return bool
	* @throws \Exception В случае неизвестности.
	*/
	public function by_unique_field() { throw new \Exception('unknown request uniqueness'); }
	
	/**
	* Немедленно получает данные по ключам, совершая все обращения к БД, какие необходимо.
	* @param array $keys Ключи в порядке следования. Например, если принимается один ключ (айди), то первым аргументом должен быть собственно айди или массив айди. Если два - скажем, айди и отношение - то первым аргументом должен быть айди или их массив, а вторым - отношение или их массив.
	* @return mixed|\Report_impossible
	*/
	public function get_data(...$keys)      		{ return $this->process_get_data($keys, static::GET_DATA_NOW); }
	
	/**
	* Получает данные по ключам, а если не все данные готовы - записывает их для получения и обещает сделать это при первой возможности.
	* @param array $keys Ключи в порядке следования.
	* @see \Pokeliga\Retriever\Request::get_data();
	* @return mixed|\Report_impossible|\Report_dependant
	*/
	public function get_data_or_dep(...$keys)		{ return $this->process_get_data($keys, static::GET_DATA_SET); }
	
	/**
	* Получает данные по ключам, а если не все данные готовы - записывает их для получения и возвращает итоговое обещание.
	* @param array $keys Ключи в порядке следования.
	* @see \Pokeliga\Retriever\Request::get_data();
	* @return mixed|\Report_impossible|\Report_promise
	*/
	public function get_data_or_promise(...$keys)
	{
		$result=$this->get_data_or_dep(...$keys);
		if ($result instanceof \Report_delay and !($result instanceof \Pokeliga\Entlink\FinalPromise)) return new \Report_promise(Task_request_get_data::with_request($this, ...$keys));
		return $result;
	}
	
	/**
	* Возвращает имеющиеся данне по ключам, а если данных не хватает - то отчёт о неудаче.
	* @param array $keys Ключи в порядке следования.
	* @see \Pokeliga\Retriever\Request::get_data();
	* @return mixed|\Report_impossible
	*/
	public function get_data_or_fail(...$keys)		{ return $this->process_get_data($keys, static::GET_DATA_SOFT); }
	
	/**
	* Обрабатывает вызовы get_data...().
	* @param array $keys Ключи в порядке следования.
	* @see \Pokeliga\Retriever\Request::get_data();
	* @param int $mode Одна из констант GET_DATA...
	* @return mixed|\Report_impossible|\Report_delay
	*/
	private function process_get_data($keys, $mode)
	{
		$uncompleted=$this->set_data(...$keys);
		if ($uncompleted instanceof \Report_impossible) return $uncompleted;
		if ($uncompleted)
		{
			if ($mode===static::GET_DATA_SET) return $this->report_dependancy();
			if ($mode===static::GET_DATA_SOFT) return $this->report_impossible('uncompleted');
			$this->complete();
		}
		if ($this->failed()) return $this->report_impossible('bad_request');
		
		return $this->compose_data(...$keys);
	}
	
	/**
	* Добавляет требуемые ключи.
	* @param array $keys Ключи в порядке следования.
	* @see \Pokeliga\Retriever\Request::get_data();
	* @return bool Возвращает true, если добавлены новые ключи, false если нет.
	*/
	public abstract function set_data(...$keys);
	
	/**
	* Возвращает готовые данные по ключам. Не добавляет ключи и не запускает запрос.
	* @param array $keys Ключи в порядке следования.
	* @see \Pokeliga\Retriever\Request::get_data();
	* @return mixed|\Report_impossible
	*/
	public abstract function compose_data(...$keys);
	
	/**
	* Возвращает число ключей.
	* В виде функции потому, чтобы черты могли изменять это число.
	* @return int
	*/
	public function keys_number() { return static::KEYS_NUMBER; }
}

/**
* Черта для запросов, не работающих с ключами и выполняющихся один раз.
*/
trait Request_no_keys
{
	public function keys_number() { return 0; }

	public function set_data(...$keys) { return empty($this->processed); }
	
	public function process_result($result)
	{
		if ($result instanceof \Report_impossible) return false;
		$this->data=$result;
		return true;
	}
	
	public function data_processed()
	{
		$this->processed=true;
	}
	
	public function compose_data(...$keys)
	{
		return $this->data;
	}
}

/**
* Черта для задач, являющихся обработкой единственного запроса.
*/
trait Task_processes_request
{	
	/**
	* @var \Pokeliga\Retriever\RequestTicket $request_ticket Собственно RequestTicket для получения данных.
	*/
	private $request_ticket=null;
	
	public function progress()
	{
		$result=$this->get_data_or_dep();
		if ($result instanceof \Report_dependant) $result->register_dependancies_for($this);
		elseif ($result instanceof \Report_impossible) $this->finish($result);
		else
		{
			$this->apply_data($result);
			$this->finalize();
		}
	}
	
	/**
	* Получает искомый RequestTicket.
	* @return \Pokeliga\Retriever\RequestTicket
	*/
	public function get_request_ticket()
	{
		if (empty($this->request_ticket)) $this->request_ticket=$this->create_request_ticket();
		return $this->request_ticket;
	}
	
	/**
	* Спрашивает у RequestTicket'а данные или отчёт.
	* @return mixed|\Report_delay
	*/
	private function get_data_or_dep()
	{
		return $this->get_request_ticket()->get_data_or_dep();
	}
	
	/**
	* Создаёт RequestTicket при его нехватке.
	* @return \Pokeliga\Retriever\RequestTicket
	* @throws \Exception в случае невозможности 
	*/
	protected function create_request_ticket()
	{
		throw new \Exception('no request ticket');
	}
	
	/**
	* Применяет полученные из RequestTicket'а данные.
	* @param mixed $data Собственно данные.
	*/
	abstract protected function apply_data($data);
}

?>