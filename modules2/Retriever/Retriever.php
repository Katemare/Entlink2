<?
namespace Pokeliga\Retriever;

load_debug_concern(__DIR__, 'Retriever');

class Retriever implements \Pokeliga\Entlink\Multiton_host
{
	use \Pokeliga\Entlink\Caller, \Pokeliga\Entlink\Multiton_host_standard, Logger_Retriever;

	const
		DEFAULT_OPERATOR_CODE='mysqli';
	
	// до того, как запрос собирается в строку с помощью compose_query, он представляется в форме массива. если не сказано обратное, функции этого и дочерних классов работают именно с формой массива.
	// массив имеет такой формат.
	// table => название таблицы или массив названий.
	// action => update/replace/insert/select
	
	// для insert/replace/update:
	// fields => массив (поле => значение). для update - что обновить. для insert/replace - какие значения вставить.
	
	//для select:
	// fields => массив (поля) . это поля, которые нужно выбрать. в отсутствие этого массива - *.
	// для INSERT вместо ключа fields можно использовать ключ values с массивом наборов полей. порядок значений должен быть одинаковым во всех наборах!
	
	// where => массив (поле => значение; цифровой ключ => условие). только для update и select.

	// первичным ключом или по крайней мере его синонимом в каждой записи должен быть id. он всегда должен быть целым положительным. уникальность id в пределах всех таблиц сразу не требуется (поэтому не используется название "uniID" - это будет задача дальнейшей разработки.)
	
	public
		$config,
		$db_prefix='', // префикс таблиц на случай, если при установке движка он был указан.
		$operator=null, // объект непосредственного оператора с БД.
		$data=[]; // это массив "название таблицы => массив записей в таблице". ключи в массиве записей равны первичному ключу.
	
	// это массив для совместимости с движками, которые не соблюдают соглашение о названии первичного поля.
	public
		$common_tables=[]; // список таблиц, где хранятся данные для сущностей из разных групп айди, так что нельзя просто делать запросы с условием id=1 - таких записей там может быть несколько, относящихся к разным сущностям. в формате 'название_таблицы'=>true, поскольку работа с ключами быстрее, чем с содердимым массивов.
	
	public function __construct($config)
	{
		$this->config=$config;
		$this->setup();
	}
	
	public function setup()
	{
		$this->setup_operator();
	}
	
	public function setup_operator()
	{
		$this->operator=$this->create_operator();
		$this->operator->connect();
	}
	
	public function create_operator()
	{
		if (array_key_exists('db_operator', $this->config)) $operator_code=$this->config['db_operator'];
		else $operator_code=static::DEFAULT_OPERATOR_CODE;
		$operator=RetrieverOperator::from_shorthand($operator_code);
		$operator->setup($this);
		return $operator;
	}
	
	// эта функция возвращает одну запись из одной таблицы.
	public function data_by_id($table, $id, $field=null)
	{
		if (is_array($table))
		{
			$result=[];
			foreach ($table as $table_name)
			{
				$result[$table_name]=$this->data_by_id($table_name, $id, $field);
			}
		}
		elseif (is_array($id))
		{
			if (!array_key_exists($table, $this->data))
			{
				$report=new \Report_impossible('no_data', $this);
				return array_fill_keys($id, $report); // для совместимости формата.
			}
			$result=array();
			foreach ($id as $i)
			{
				$result[$i]=$this->data_by_id($table, $i, $field);
			}
		}
		else
		{
			if ( (!array_key_exists($table, $this->data)) || (!array_key_exists($id, $this->data[$table])) )
				return new \Report_impossible('no_data', $this);
				
			$result=$this->data[$table][$id];
			if (!is_null($field))
			{
				if (!array_key_exists($field, $result)) return new \Report_impossible('no_field', $this);
				$result=$result[$field];
			}
		}
		return $result;
	}

	// используются ли следующие два метода?
	
	public function data_by_table($table)
	{
		if (is_array($table))
		{
			$result=[];
			foreach ($table as $table_name)
			{
				$result[$table_name]=$this->data_by_table($table_name);
			}
			return $result;
		}
		else
		{
			if (!array_key_exists($table, $this->data)) return new \Report_impossible('no_data', $this);
			return $this->data[$table];
		}
	}
	
	public function ids_by_table($table)
	{
		if (is_array($table))
		{
			$result=[];
			foreach ($table as $table_name)
			{
				$result[$table_name]=$this->ids_by_table($table_name);
			}
			return $result;
		}
		else
		{
			if (!array_key_exists($table, $this->data)) return new \Report_impossible('no_data', $this);
			return array_keys($this->data[$table]);
		}
	}
	
	// эта функция непосредственно прогоняет запрос в форме массива
	public function run_query($query)
	{
		if (is_array($query)) $query=Query::from_array($query);
		$composed_query=$query->compose();
		$result=$this->operator->query($composed_query);
		if ($result===false) return new \Report_impossible('request_rejected', $this);
		if ($query['action']==='select')
		{
			$rows=$this->fetch_all($result);
			if ($query->storeable()) $this->store($rows, $query->store_to());
			return $rows;
		}
		elseif ($query['action']==='insert')
		{
			if ( (!empty($query['insert_ignore'])) && ($this->affected_rows()==0) ) return false; // не \Report_impossible, потому что это не обязательно ошибка.
			return $this->get_insert_id();
		}
		else /* update, replace, delete */ return $result;
		
		// итого этот метод возвращает одно из следующих значений:
		// \Report_impossible - запрос отвергнут.
		// массив - запрошенные записи.
		// false - результат запроса insert_ignore в случае, если ни одна строчка не была вставлена.
		// число - insert_id() после запроса insert.
		// true - результат запроса update, replace или delete.
	}
	
	public function run_queries($queries)
	{
		$result=[];
		foreach ($queries as $key=>$query)
		{
			$result[$key]=$this->run_query($query);
		}
		return $result;
	}
	
	// сохраняет сведения из БД в кэш, упорядочивая по таблицам и айди. Это нужно для того, чтобы результаты, найденные любым образом (любыми запросами), были потом доступны безотносительно запроса: по айди.
	public function store(&$rows, $table)
	{
		if (!array_key_exists($table, $this->data)) $this->data[$table]=[];
		foreach ($rows as &$row)
		{
			if ($this->is_common_table($table)) $id=$row['id_group'].$row['id'];
			else $id=$row['id'];
			if (array_key_exists($id, $this->data[$table])) $this->log('data_rewrite');
			$this->data[$table][$id]=$row;	
		}
		
		$hook='stored_'.$table;
		$this->make_calls($hook);
	}
	
	public function register_common_table($table)
	{
		$this->common_table[$table]=true;
	}
	
	public function is_common_table($table)
	{
		return !empty($this->common_table[$table]);
	}
	
	// эта функция принимает строковый запрос SQL.
	public function query($query)
	{
		return $this->operator->query($query);
	}
	
	// эта функция принимает ресурс, возвращённый функцией query.
	public function fetch($res)
	{
		return $this->operator->fetch($res);
	}
	
	public function fetch_all($res)
	{
		return $this->operator->fetch_all($res);
	}
	
	public function get_insert_id()
	{
		return $this->operator->get_insert_id();
	}
	
	public function affected_rows()
	{
		return $this->operator->affected_rows();
	}
	
	public function safe_text($text)
	{
		return $this->operator->safe_text($text);
	}
	
	public function safe_text_like($text)
	{
		return $this->operator->safe_text(str_replace(['%', '_'], ['\%', '\_'], $text));
	}
	
	public function start_transaction()
	{
		return $this->operator->start_transaction();
	}
	
	public function commit()
	{
		return $this->operator->commit();
	}
	
	public function rollback()
	{
		return $this->operator->rollback();
	}
	
	// позволяет Ретриверу получать данные от других объектов.
	// FIX: позже это будет делать объект-запрос.
	public function receive($table, $row)
	{
		$this->data[$table][$row['id']]=$row;
	}
	
	// KILL - следует избавиться.
	public function basic_connect()
	{
		mysql_connect ($this->config['db_host'], $this->config['db_login'], $this->config['db_password']);
		mysql_select_db ($this->config['db_database']);
		mysql_set_charset('utf8');	
	}
}

abstract class RetrieverOperator
{
	use \Pokeliga\Entlink\Shorthand;
	
	public
		$composer_shorthand='replace_me';
	
	public function query_composer_for($query)
	{
		$class=QueryComposer::get_shorthand_class($this->composer_shorthand);
		return $class::with_query($query, $this);
	}
	
	public abstract function connect();

	public abstract function get_insert_id();

	public abstract function affected_rows();
	
	public abstract function query($query);
	
	public abstract function fetch($result);
	
	public abstract function fetch_all($result);
	
	public abstract function safe_text($text);
	
	public abstract function start_transaction();
	
	public abstract function commit();
	
	public abstract function rollback();
}
?>