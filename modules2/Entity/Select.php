<?
namespace Pokeliga\Entity;

interface Select_provides_ticket
{
	public function create_request_ticket();
}

// наполняет значение класса Value_linkset
abstract class Select extends Filler_for_entity implements \Pokeliga\Template\Templater
{
	use \Pokeliga\Entlink\Shorthand;
	
	public
		$query_convertable=false, // означает, что выборщик отрабатывает на основе одного явного запроса без дополнительной логики и может быть сведён к этому запросу.
		$original_select; // для селекторов, образованных череез derieved()
	
	public static function for_value($value)
	{
		if (get_called_class()===__CLASS__)
		{
			if (!$value->in_value_model('select')) { vdump($value); die ('NO LINKSET SELECTOR'); }
			$keyword=$value->value_model_now('select');
			$selector=static::from_shorthand($keyword);
		}
		else $selector=new static();
		$selector->set_value($value);
		
		if ( ($value->in_value_model('per_page')) && (!($selector instanceof \Pokeliga\Template\Paged)) )
		{
			$value->reset();
			if ($value->in_value_model('page_var')) $page_var=$value->value_model_now('page_var'); else $page_var='p';
			if ($value->in_value_model('order')) $order=$value->value_model_now('order'); else $order='id';
			$per_page=$value->value_model_now('per_page');
			$selector=$selector->select_page($order, $page_var, $per_page);
			$selector->set_value($value);
		}
		elseif ( ($value->in_value_model('order'))&&(!($selector instanceof Select_ordered_request)) )
		{
			$value->reset();
			$selector=$selector->select_ordered($value->value_model_now('order')); // FIXME: рекурсия должна обеспечиваться как-то иначе, потому что select_ordered() может выдавать селекторы разного рода.
			$selector->set_value($value);
		}

		return $selector;
	}
	
	public function set_value($value)
	{
		$this->value=$value;
		$this->value->set_filler($this);
		$this->setup();
	}
	
	public static function from_model($model, $master=null)
	{
		if (!is_array($model)) $model=['id_group'=>$model];
		$model['type']='linkset';
		$value=\Pokeliga\Data\Value::standalone($model);
		$value->master=$master;
		$select=static::for_value($value);
		return $select;
	}
	
	public static function standalone($id_group=null)
	{
		if (($class=get_called_class())===__CLASS__) die('BAD STANDALONE SELECTOR');
		$code=substr($class, 7);
		$model=['select'=>$code, 'id_group'=>$id_group];
		return static::from_model($model);
	}
	
	public static function blank($keyword=null)
	{
		if ( ($keyword===null) && (get_called_class()===__CLASS__) ) die ('NO SELECTOR KEYWORD');
		if ($keyword===null) return new static();
		return $selector=static::from_shorthand($keyword);
	}
	
	public static function derieved($original_select, ...$ignore)
	{
		$model=$original_select->value_model();
		foreach ($ignore as $model_code)
		{
			unset($model[$model_code]);
		}
		$select=static::from_model($model);
		$select->original_select=$original_select;
		$select->value->set_filler($select); // новое значение уже настроено на новый селектор.
		return $select;
	}
	
	public function template($code, $line=[])
	{
		return $this->value->template($code, $line);
	}
	
	public function linkset_from_data($data, $key='id')
	{
		$ids=[];
		foreach ($data as $row)
		{
			$ids[]=$row[$key];
		}
		return $this->linkset_from_ids($ids);
	}

	public function id_group()
	{
		if ($this->in_value_model('id_group')) return $this->value_model_now('id_group'); 
	}
	
	public function empty_linkset()
	{
		$linkset=$this->value->content;
		$linkset->clear();
		return $linkset;
	}
	
	public function linkset_from_ids($ids)
	{	
		$linkset=$this->empty_linkset();
		$id_group=$this->id_group();
		foreach ($ids as $id)
		{
			$entity=$this->pool()->entity_from_db_id($id, $id_group);
			$linkset->add($entity);
		}
		return $linkset;
	}
	
	public function linkset_from_entities($entities)
	{
		$linkset=$this->empty_linkset();
		foreach ($entities as $entity)
		{
			$linkset->add($entity);
		}
		return $linkset;
	}
	
	public function resolve_from_data($data, $key='id')
	{
		$this->resolve($this->linkset_from_data($data, $key));
	}
	
	public function resolve_from_ids($ids)
	{
		$this->resolve($this->linkset_from_ids($ids));
	}
	
	public function resolve($linkset)
	{
		$this->resolution=$linkset;
		$this->finish();
	}
	
	public function setup()
	{
		parent::setup();
		if ($this->value->content===null) $this->value->content=$this->create_linkset();
	}
	
	public function create_linkset()
	{
		if (!empty($this->entity)) $linkset=LinkSet::for_entity($this->entity);
		else $linkset=LinkSet::for_value($this->value);
		return $linkset;
	}
	
	public function entities()
	{
		if (!$this->successful()) return $this->report();
		
		return $this->resolution->values;
	}
	
	// следующие методы отражают роль Селектора не как утилитарного объекта для формирования набора сущностей, а как представителя набора сущностей для самых разных операций.
	
	// возвращает валидаторы, выполнение которых необходимо для проверки присутствия сущности в наборе, помимо соответствия id_group. позволяет проверить принадлежность сущности к набору без того, чтобы получать весь набор. это также значит, что условия включения в набор должны быть прописаны и здесь, и в создании запроса - что не очень хорошо, но связать их воедино пока не получается.
	public function list_validators() { }
	// FIXME! не используется, зато есть дублирующийся функционал: интерфейс Select_has_range_validator
	
	// возвращает Селектор, добавляющий в выборку дополнительные условия. каждый вызов поочерёдно принимает RequestTicket и актуальный выборщик, возвращая новый (или тот же) RequestTicket.
	public abstract function select_modified_by_calls(...$calls);
	
	// возвращает Селектор класса Select_filter, который берёт содержимое базового селектора и проверяет каждый элемент определённой логикой, недоступной БД (и, следовательно, вызовам в select_modified_by_calls, которые модифицируют запрос к БД).
	public abstract function select_filtered($filter_call);
	
	// возвращает Селектор, изымающий страницу из набора. номер страницы берётся из $_GET[$page_var].
	public abstract function select_page($order='id', $page_var='p', $perpage=50);
	
	// возвращает Селектор, возвращающий указанное число элементов из начала списка. нужен, например, для поиска.
	public abstract function select_limited($limit=20, $order='id');
	
	// возвращает Селектор, выбирающий элементы по поиску - дополнительным условиям. принимает либо строку (как распорядиться строкой поиска - решает типизатор, если он указан в id_group), либо массив условий.
	public abstract function select_search($search);
	
	// возвращает Селектор, выбирающий случайные элементы из набора в заданном количестве.
	public abstract function select_random($limit=20);
	
	// возвращает Селектор, упорядочивающий элементы заданным способом.
	public abstract function select_ordered($order='id');
	
	// возвращает тикет, результатом работы которого становится число - подсчёт элементов в наборе. работает без того, чтобы заполнить набор. если набор уже составлен, то просто подсчитывает его и возвращает число. это может выполнить лишнюю операцию в случае, если набор уже поставлен для заполнения или вот-вот будет поставлен, но что поделать.
	public abstract function extract_count();
	
	// возвращает тикет, подсчитывающий нужные статы из набора, например, сумму заданных полей, средние значения... WIP!
	public abstract function extract_stats($stats);
	
	// этот метод должен возвращать запрос (Request), который получает поля из основной таблицы того типа сущностей, к которому относится выборщик. это необходимо для последующего преобразования запроса. когда выборщик работает самостоятельно, этого не требуется и можно, например, получить и заполнить список тренеров по полю "owner" у набора покемонов.
	public function create_standard_request()
	{
		return $this->sign_report(new \Report_impossible('not_query_convertable'));
	}
	
	public function produce_range_query()
	{
		return $this->sign_report(new \Report_impossible('not_query_convertable'));
	}
}

abstract class Select_by_single_request extends Select implements Select_provides_ticket
{
	use \Pokeliga\Retriever\Task_processes_request;
	
	public
		$id_key='id',
		$query_convertable=true;
	
	public function id_key()
	{
		if ($this->in_value_model('select_id_field')) return $this->value_model_now('select_id_field');
		return $this->make_id_key();
	}
	
	public function make_id_key()
	{
		if ($this->id_key!==null) return $this->id_key;
	}
	
	public function apply_data($data)
	{
		$this->resolve_from_data($data, $this->id_key());
	}
	
	/*
	abstract public function create_request_ticket();
	*/
	
	// для работы уточняющих выборщиков (типа Select_ordered). В результате должен быть возвращён тикет запроса, выбирающего строки из основной базовой таблицы сущности и поле с айдишниками в которых называется "id". FIXME: это невозможно в случае, если выбираются сущности разного типа - но пока такого случая нет.
	public function create_standard_request()
	{
		return $this->create_request_ticket();
	}
	public function produce_range_query()
	{
		return $this->get_request()->create_query();
	}
	
	public function select_modified_by_calls(...$calls)
	{
		$modified=Select_modified_request::derieved($this);
		$modified->setup_calls(...$calls);
		return $modified;
	}
	
	public function select_filtered($filter_call)
	{
		$filtered=Select_filter::derieved($this);
		$filtered->setup_filter($filter_call);
		return $filtered;
	}
	
	public function select_page($order='id', $page_var='p', $perpage=50)
	{
		$extract=Select_page_from_request::derieved($this, 'order');
		$extract->setup_page($order, $page_var, $perpage);
		return $extract;
	}
	
	public function select_limited($limit=20, $order='id') // здесь в другом порядке потому, что некоторые выборщики подразумевают порядок, и тогда можно не указывать.
	{
		$extract=Select_limited_from_request::derieved($this, 'order', 'limit');
		$extract->setup_limit($limit, $order);
		return $extract;
	}
	
	public function select_search($search)
	{
		$extract=Select_search_from_request::derieved($this, 'search');
		$extract->setup_search($search);
		return $extract;
	}
	
	public function select_random($limit=20)
	{
		$extract=Select_random_from_request::derieved($this, 'random');
		$extract->setup_random($limit);
		return $extract;
	}
	
	public function select_ordered($order='id')
	{
		$extract=Select_ordered_request::derieved($this, 'order');
		$extract->setup_order($order);
		return $extract;
	}
	
	public function extract_count()
	{
		return new RequestTicket_count($this->create_request_ticket());
	}
	
	public function extract_stats($stats)
	{
		return new RequestTicket('Request_group_functions', [$this->create_request_ticket()], [$stats]);
	}
}

class Select_by_ticket extends Select_by_single_request
{
	public static function from_ticket($ticket, $model)
	{
		if (!is_array($model)) $model=['id_group'=>$model];
		$model['ticket']=$ticket;
		$select=static::from_model($model);
		return $select;
	}
	
	public static function from_query($query, $model)
	{
		$ticket=new RequestTicket('Request_single', [$query]);
		$select=static::from_ticket($ticket, $model);
		return $select;
	}

	public function create_request_ticket()
	{
		$ticket=$this->value_model_now('ticket');
		if (!($ticket instanceof \Pokeliga\Retriever\RequestTicket)) die('NO TICKET');
		return clone $ticket;
	}
}

class Select_modified_request extends Select_by_single_request
{
	public
		$calls;
		
	public function setup_calls(...$calls)
	{
		$this->calls=$calls;
	}
		
	public function create_request_ticket()
	{
		$ticket=$this->original_select->create_standard_request();
		foreach ($this->calls as $call)
		{
			$ticket=$call($ticket, $this);
		}
		return $ticket;
	}
}

class Select_random_from_request extends Select_by_single_request
{
	public
		$limit;
		
	public function setup_random($limit)
	{
		$this->limit=$limit;
	}
		
	public function create_request_ticket()
	{
		return new RequestTicket('Request_random', [$this->original_select->create_standard_request(), $this->limit]);
	}
}

class Select_ordered_request extends Select_by_single_request
{
	public
		$order;
		
	public function create_request_ticket()
	{
		return new RequestTicket('Request_ordered', [$this->original_select->create_standard_request(), $this->order]);
	}
	
	public function setup_order($order=null)
	{
		if ($order===null) $order=$this->order;
		$this->order=$order;
	}
}

class Select_limited_from_request extends Select_ordered_request
{
	public
		$limit;
		
	public function setup_limit($limit, $order)
	{
		$this->setup_order($order);
		$this->limit=$limit;
	}
		
	public function create_request_ticket()
	{
		return new RequestTicket('Request_limited', [$this->original_select->create_standard_request(), $this->order], [$this->limit]);
	}
}

class Select_page_from_request extends Select_ordered_request implements \Pokeliga\Template\Paged
{
	public
		$page=1,
		$per_page=50,
		$page_var='p';
	
	public function setup_page($order='id', $page_var='p', $per_page=50)
	{
		$this->setup_order($order);
		$this->page=InputSet::instant_fill($page_var, ['type'=>'unsigned_int', 'min'=>1, 'default'=>1]);
		$this->per_page=$per_page;
	}
	
	public function create_request_ticket()
	{
		return new RequestTicket('Request_page', [$this->original_select->create_standard_request(), $this->order, $this->page, $this->per_page], []);
	}
	
	public function get_page() { return $this->page; }
	public function get_per_page() { return $this->per_page; }
	public function get_page_var() { return $this->page_var; }
	public function get_complete_count() { return $this->original_select->extract_count(); }
}

/*
class Select_search_from_request extends Select_by_single_request
{
}
*/

// берёт за основу потомка Select_by_single_request и модифицирует его выборку.
class Select_filter extends Select
{
	use \Pokeliga\Task\Task_steps, Select_complex
	{
		\Pokeliga\Task\Task_steps::dependancies_resolved			as std_dependancies_resolved;
		Select_complex::select_modified_by_calls	as complex_select_modified_by_calls;
		// Select_complex::select_page				as complex_select_page;
		Select_complex::select_limited				as complex_select_limited;
		Select_complex::select_search				as complex_select_search;
		Select_complex::select_random				as complex_select_random;
		Select_complex::select_ordered				as complex_select_ordered;
		// интересуют только методы подсчёта и статистики, а также те, которые временно закомментированы выше (не реализованы)
	}
	
	const
		STEP_GET_REQUEST=0,
		STEP_MODIFY_REQUEST=1,
		STEP_GATHER_CANDIDATES=2,
		STEP_FILTER=3,
		STEP_FINISH=4,
		
		BLOCK_BLEED=0.3;

	public
		$base_select,
		$base_request,
		$id_key='id',
		$filter_call,
		$candidates=[],	// массив сущностей, а не LinkSet - всего этого функционала с шаблонами и значениями не требуется.
		
		$good=[],	// пары порядок => прошедший кандидат.
		$bad=[],	// пары порядок => плохой кандидат.
		$maybe=[],	// пары порядок => кандидат под вопросом.
		$resolved_before,	// минимальный порядок в массиве maybe. до этого порядка все кандидаты ясны.
		$maybe_call=[], 	// пары порядок => вызов зависимости, чтобы очищать их, когда необходимость отпадает.
		$maybe_tasks=[],	// пары порядок => зависимость
		$good_delayed=[],	// пары порядок => прошедший кандидат, перед которым ещё есть неизвестные (то есть с порядком позже $resolved_before). только если указан предел.
		
		// данные для модифицированных запросов.
		$calls=[],
		$randomize=false,
		$order=false,
		$limit=false;
	
	public function setup_filter($filter)
	{
		$this->filter_call=$filter;
	}
	
	public static function derieved($original_select, ...$ignore)
	{
		$select=parent::derieved($original_select, ...$ignore);
		if ($original_select instanceof Select_filter)
		{
			$select->filter_call=$original_select->filter_call;
			$select->base_select=$original_select->base_select;
			$select->base_request=$original_select->base_request;
			$select->id_key=$original_select->id_key; // на случай, если базовый Селектор не был указан.
			$select->order=$original_select->order;
			$select->randomize=$original_select->randomize;
			$select->limit=$original_select->limit;
			$select->calls=$original_select->calls;
		}
		else
		{
			$select->base_select=$original_select;
		}
		return $select;
	}
	
	public function id_key()
	{
		if ($this->in_value_model('select_id_field')) return $this->value_model_now('select_id_field');
		return $this->id_key;
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_GET_REQUEST)
		{
			if ($this->base_request!==null)
			{
				$this->request=clone $this->base_request;
				return $this->advance_step(); // базовый выборщик нужен только как поставщик запроса. иногда запрос уже известен.
			}
			$base=$this->get_base_selector();
			if (!($base instanceof Select_provides_ticket)) return $this->sign_report(new \Report_impossible('bad_base_selector'));
			$this->request=$base->create_request_ticket();
			$this->id_key=$base->id_key();
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_MODIFY_REQUEST)
		{
			if (!empty($this->calls))
			{
				foreach ($this->calls as $call)
				{
					$this->request=$call($this->request, $this);
				}
			}
			if ($this->randomize) $this->request=new RequestTicket('Request_random', [$this->request]);
			elseif ($this->order!==false) $this->request=new RequestTicket('Request_ordered', [$this->request, $this->order]);
			
			$report=$this->request->get_data_set();
			if ($report instanceof \Report) return $report;
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_GATHER_CANDIDATES)
		{
			$data=$this->request->get_data_set();
			if ($data instanceof \Report_impossible) return $data;
			if ($data instanceof \Report_tasks) return $this->sign_report(new \Report_impossible('bad_request_ticket'));
			if (empty($data)) return $this->sign_report(new \Report_resolution($this->empty_linkset()));
			
			$report=$this->gather_candidates_from_data($data);
			if ($report instanceof \Report) return $report;
			if (empty($this->candidates)) return $this->sign_report(new \Report_resolution($this->empty_linkset()));
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_FILTER)
		{
			foreach ($this->candidates as $order=>$candidate)
			{
				$result=$this->filter($candidate);
				if ($result===true)
				{
					if ( ($this->limit!==false) && ($this->resolved_before!==null)) $this->good_delayed[$order]=$candidate;
					else $this->good[$order]=$candidate;
				}
				elseif ($result===false) $this->bad[$order]=$candidate;
				elseif ($result instanceof \Report_task)
				{
					if ($this->resolved_before===null) $this->resolved_before=$order; // кандидаты рассматриваются по очереди.
					$task=$result->task;
					$this->maybe[$order]=$candidate;
					$this->maybe_tasks[$order]=$task;
					$call=new Call([$this, 'candidate_resolved'], $order);
					$this->maybe_call[$order]=$call;
					$task->add_call($call, 'complete');
				}
				elseif ($result instanceof \Report_tasks) die ('BAD FILTER REPORT');
				elseif ($result instanceof \Report_impossible) return $result;
				if ( ($this->limit!==false) && (count($this->good)==$this->limit) && (empty($this->maybe)) ) return $this->advance_step();
			}
			if (empty($this->maybe)) return $this->advance_step();
			if ($this->limit!==false)
			{
				$process=new Process_prioritized($this->maybe_tasks, $this->limit*(1+static::BLOCK_BLEED));
				return $this->sign_report(new \Report_task($process));
			}
			else return $this->sign_report(new \Report_tasks($this->maybe_tasks));
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			if (empty($this->good)) return $this->sign_report(new \Report_resolution($this->empty_linkset()));
			ksort($this->good);
			if ($this->limit!==false) $this->good=array_slice($this->good, 0, $this->limit); // эта операция не сохраняет ключи, но они и не нужны, когда результат получен.
			return $this->sign_report(new \Report_resolution($this->linkset_from_entities($this->good)));
		}
	}
	
	public function get_base_selector()
	{
		return $this->base_select;
	}
	
	public function gather_candidates_from_data($data)
	{
		$key=$this->id_key();
		if (empty($key)) return $this->sign_report(new \Report_impossible('no_id_key'));
		$type=$this->id_group();
		
		foreach ($data as $row)
		{
			$entity=$this->pool()->entity_from_db_id($row[$key], $type);
			$this->candidates[]=$entity;
		}
	}
	
	public function filter($entity)
	{
		$call=$this->filter_call;
		return $call($entity);
	}
	
	public function candidate_resolved($order, $task)
	{
		$candidate=$this->maybe[$order];
		unset($this->maybe[$order]);
		unset($this->maybe_call[$order]);
		unset($this->maybe_tasks[$order]);
		if ($task->failed())
		{
			$this->impossible('filter_error');
			return;
		}
		elseif ($task->resolution===false) $this->bad[$order]=$candidate;
		elseif (($this->limit===false) || ($this->resolved_before===$order)) $this->good[$order]=$candidate;
		else $this->good_delayed[$order]=$candidate;
		
		if ($this->limit===false) return; // следующее - только для ограниченных выборок.
		if ($this->resolved_before===$order)
		{
			// задачи добавлялись по очереди, так что самые низкоочерёдные - сначала.
			reset($this->maybe);
			$this->resolved_before=key($this->maybe);
			ksort($this->good_delayed);
			foreach ($this->good_delayed as $order=>$candidate)
			{
				if ( ($this->resolved_before!==null) && ($order>=$this->resolved_before) ) break;
				$this->good[$order]=$candidate;
				unset($this->good_delayed[$order]);
			}
		}
		if (($this->resolved_before!==null) && ($this->resolved_before<$this->limit)) return; // не могло быть отобрано $limit первых хороших кандидатов, если пока не просмотрено $limit первых кандидатов.
		if (count($this->good)>=$this->limit) $this->dependancies_resolved();
	}
	
	public function dependancies_resolved()
	{
		if ( ($this->step===static::STEP_FILTER) && (!empty($this->maybe)) )
		{
			foreach ($this->maybe_call as $order=>$call)
			{
				$this->maybe_tasks[$order]->remove_call($call, 'complete');
			}
		}
		$this->std_dependancies_resolved();
	}
	
	// возвращает Селектор с изменениями в /базовом выборщике/.
	public function select_modified_by_calls(...$calls)
	{
		$select=$this::derieved($this);
		$select->calls=$calls;
		return $select;
	}
	
	// возвращает Селектор страницы из /итоговой выборки/.
	// public function select_page($order='id', $page_var='p', $perpage=50)
	
	// возвращает Селеектор ограниченного отрезка /итоговой выборки/. фильтр останавливает работу, когда отобрал достаточно элементов, поэтому используется процесс с приоритетом.
	public function select_limited($limit=20, $order='id')
	{
		$select=$this::derieved($this, 'order', 'limit');
		$select->order=$order;
		$select->limit=$limit;
		return $select;
	}
	
	// возвращет Селектор с поиском в /базовой выборке/
	public function select_search($search)
	{
		die('UNIMPLEMENTE YET: filtered search');
	}
	
	// возвращает Селектор со случайными результатами /итоговой выборки/. фильтр останавливает работу, когда отобрал достаточно элементов, поэтому используется процесс с приоритетом (FIXME: важно ли, чтобы перемешанные с помощью RAND() элементы отбирались в порядке с первого по последний? или достаточно $limit любых подходящих элементов, и распределение сохранится? возможно, нет: к некоторым данные могли быть получены заранее и поэтому страницы с той или иной конфигурацией могут показывать не вполне случайные элементы).
	public function select_random($limit=20)
	{
		$select=$this::derieved($this, 'random');
		$select->randomize=true;
		$select->limit=$limit;
		return $select;
	}
	
	// возвращает Селектор с упорядоченным /базовым выборщиком/ (фильтр ведь может только проредить результаты, а не переставить).
	public function select_ordered($order='id')
	{
		$select=$this::derieved($this, 'order');
		$select->order=$order;
		return $select;
	}
	
	// возвращает Тикет на количество элементов /итоговой выборки/
	// public function extract_count();
	
	// возвращает Тикет, подсчитывающий нужные статы из /итоговой выборки/
	// public function extract_stats($stats);
}

class Select_all extends Select_by_single_request
{
	public
		$table=null,
		$id_group=false;

	public function id_group()
	{
		if ($this->id_group===false)
		{
			if ($this->in_value_model('id_group')) $this->id_group=$this->value_model_now('id_group');
			else $this->id_group=null;
		}
		return $this->id_group;
	}
	
	public function table()
	{
		if ($this->table===null)
		{
			if ($this->in_value_model('select_table')) $this->table=$this->value_model_now('select_table');
			elseif (($type=$this->id_group())!==null)
			{
				$class=$type::$base_aspects['basic'];
				$this->table=$class::$default_table;
			}
			else { vdump($this); die ('UNIMPLEMENTED_YET'); }
		}
		return $this->table;
	}
	
	public function create_request_ticket()
	{
		return new RequestTicket('Request_all', [$this->table()]);
	}
}

class Select_by_field extends Select_by_single_request
{
	public
		$table, $field, $content,
		$additional_conditions=false;
	
	public function table()
	{
		if ($this->table===null)
		{
			if ($this->in_value_model('select_table')) $this->table=$this->value_model_now('select_table');
			else
			{
				$id_group=$this->id_group();
				$this->table=$id_group::$default_table;
			}
		}
		return $this->table;
	}
	
	public function field()
	{
		if ($this->field===null)
		{
			if ($this->in_value_model('select_field')) $this->field=$this->value_model_now('select_field');
			else die('NO SELECTOR FIELD');
		}
		return $this->field;
	}
	
	public function content()
	{
		if ($this->content===null)
		{
			if ($this->in_value_model('select_content')) $this->content=$this->value_model_now('select_content');
			else die('NO SELECTOR CONTENT');
		}
		return $this->content;
	}
	
	public function additional_conditions()
	{
		if ($this->additional_conditions===false)
		{
			if ($this->in_value_model('select_conditions')) $this->additional_conditions=$this->value_model_now('select_conditions');
			else $this->additional_conditions=null;
		}
		return $this->additional_conditions;
	}
	
	public function fulfills_conditions($conditions)
	{
		if (empty($conditions)) return true;
		$current_conditions=$this->additional_conditions();
		if (empty($current_conditions)) return false;
		
		foreach ($conditions as $condition)
		{
			if (!in_array($condition, $current_conditions)) return false;
		}
		return true;
	}
	
	public function append_conditions($conditions)
	{
		if ($this->fulfills_conditions($conditions)) return false;
		$old_conditions=(array)$this->additional_conditions();
		$new_conditions=array_unique(array_merge($old_conditions, $conditions));
		$this->additional_conditions=$new_conditions;
		return true;
	}
	
	public function apply_data($data)
	{
		if ( (is_array($this->content())) && (!$this->get_request()->by_unique_field()) && (!($data instanceof \Report)) )
		{
			$merge=[];
			foreach ($data as $value=>$array)
			{
				if (!is_array($array)) continue;
				$merge+=$array; // ключами во внутренних массивах являются айди, которые даже если совпадают, ведут на одну и ту же строчку.
			}
			$data=$merge;
		}
		parent::apply_data($data);
	}
	
	public function create_request_ticket()
	{
		return new RequestTicket('Request_by_field', [$this->table(), $this->field(), $this->additional_conditions()], [$this->content()]);
	}
	
	public function select_search($search)
	{
		if (!is_array($search)) die('IMPLEMENTED YET: string search');
		if ($this->fulfills_conditions($search)) return $this;
		$select=static::derieved();
		$appended=$select->append_conditions($search);
		if ($appended) return $select;
		else return $this;
	}
}

class Select_backlinked extends Select_by_field
{
	public
		$backlink_field=null;

	public function field()
	{
		if ($this->backlink_field===null)
		{
			$this->backlink_field=$this->value_model_now('backlink_field');
		}
		return $this->backlink_field;
	}

	public function table()
	{
		if ($this->table===null)
		{
			if ($this->in_value_model('select_table')) return parent::table();
			
			$id_group=$this->value_model_now('id_group');
			$id_group::init(); // можно было бы включить в метод locate_name, но это пока единственное место, где нет уверенности, что тип уже инициализирован при обращении к нему.
			$aspect_code=$id_group::locate_name($this->field());
			$aspect_class=$id_group::$base_aspects[$aspect_code];
			$this->table=$aspect_class::$default_table; // STUB: не работает с полями, которые находятся в нестандартной для аспекта таблице.
		}
		return $this->table;
	}

	public function content()
	{
		return $this->entity->db_id;
	}
}

class Select_generic_linked extends Select_by_single_request
{
	public $position=null;
	public function position()
	{
		if ($this->position===null) $this->position=$this->value_model_now('position');
		if ($this->position===null) die('NO SELECT POSITION');
		return $this->position;
	}
	
	public $opposite_id_group=false;
	public function opposite_id_group()
	{
		if ($this->opposite_id_group===false) $this->opposite_id_group=$this->value_model_now('opposite_id_group');
		if ($this->opposite_id_group===false) die('NO SELECT OPPOSITE ID GROUP');
		return $this->opposite_id_group;
	}
	
	public function source_id_group()
	{
		return $this->id_group();
	}
	
	public $relation=null;
	public function relation()
	{
		if ($this->relation===null) $this->relation=$this->value_model_now('relation');
		if ($this->relation===null) die('NO SELECT RELATION');
		return $this->relation;
	}

	public function create_request_ticket()
	{
		$ticket=new RequestTicket('Request_generic_links', [$this->position(), $this->opposite_id_group(), $this->source_id_group()], [$this->entity->db_id, $this->relation()]);
		return $ticket;
	}
	
	public function create_standard_request()
	{
		$ticket=$this->create_request_ticket();
		$ticket->standalone();
		$query=$ticket->create_query();
		$query=Query::from_array($query);
		
		$type=$this->id_group();
		$query->add_primary($type::$default_table, 'links');
		$query->add_complex_condition(['field'=>'id', 'value_field'=>['links', $this->id_key()]]);
		
		return new RequestTicket('Request_single', [$query]);
	}
	
	public function make_id_key()
	{
		if ($this->position()===Request_generic_links::FROM_OBJECT) return 'entity2_id';
		else return 'entity1_id';
	}
	
	public function apply_data($data)
	{
		$this->resolve_from_data($data, $this->id_key());
	}
}

class Select_generic_links extends Select_generic_linked
{
	public $source_id_group=false;
	public function source_id_group()
	{
		if ($this->source_id_group===false) $this->source_id_group=$this->value_model_now('source_id_group');
		if ($this->source_id_group===false) die('NO SELECT SOURCE ID GROUP');
		return $this->source_id_group;
	}
	
	public function make_id_key()
	{
		return Select_by_single_request::make_id_key();
	}
}

?>