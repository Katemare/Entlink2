<?
namespace Pokeliga\Entity;

class Select_from_ids extends Select implements Select_provides_ticket
{
	public
		$ids=null,
		$query_convertable=true;
	
	public static function from_ids($ids, $id_group=null)
	{
		$model=['ids'=>$ids, 'id_group'=>$id_group];
		return static::from_model($model);
	}
	
	public function ids()
	{
		if ($this->ids===null)
		{
			if ($this->in_value_model('ids')) $this->ids=$this->value_model_now('ids');
		}
		if ($this->ids===null) die('NO IDS');
		return $this->ids;
	}

	public function progress()
	{
		$ids=$this->ids();
		$this->resolve_from_ids($ids);
	}
	
	public function create_request() // для совместимости с Select_limited и прочими.
	{
		$id_group=$this->id_group();
		$range_ticket=new RequestTicket('Request_by_id', [$id_group::$default_table], [$this->ids()]);
		return $range_ticket;
	}
	
	public function select_modified_by_calls(...$calls)
	{
		$modified=Select_modified_request::derieved($this);
		$modified->setup_calls($calls);
		return $modified;
	}
	
	
	public function select_filtered($filter_call)
	{
		die('UNIMPLEMENTED YET: select filter from ids');
	}
	
	public function select_page($order='id', $page_var='p', $perpage=50)
	{
		die('UNIMPLEMENTED YET: select page');
	}
	
	public function select_limited($limit=20, $order='id')
	{
		$ids=$this->ids();
		if ($order==='id')
		{
			sort($ids);
			$select=Select_from_ids::derieved($this, 'order', 'limit');
			$select->ids=array_splice($ids, 0, $limit);
		}
		elseif ($order==['id', 'dir'=>'DESC'])
		{
			rsort($ids);
			$select=Select_from_ids::derieved($this, 'order', 'limit');
			$select->ids=array_splice($ids, 0, $limit);
		}
		else
		{
			$select=Select_limited_from_request::derieved($this, 'order', 'limit');
			$this->setup_limit($limit, $order);
			$select->ids=$ids;
		}
		return $select;
	}
	
	public function select_search($search)
	{
		$select=Select_search_from_request::derieved($this, 'search');
		$select->setup_search($search);
		return $select;
	}
	
	public function select_random($limit=20)
	{
		$ids=$this->ids();
		if ($limit>=count($ids)) $picked=$ids;
		else
		{
			$picked=[];
			$keys=array_rand($ids, $limit);
			foreach ($keys as $key) $picked[]=$ids[$key];
		}
		shuffle($picked);
		
		$select=Select_from_ids::derieved($this, 'random');
		$select->ids=$this->ids();
		return $select;
	}
	
	public function select_ordered($order='id')
	{
		$select=Select_ordered_request::derieved($this, 'order');
		$select->setup_order($order);
		return $select;
	}
	
	public function extract_count()
	{
		return count($this->ids());
	}
	
	public function extract_stats($stats)
	{
		return new RequestTicket('Request_group_functions', [$this->create_request()], [$stats]);
	}
	
	public function create_standard_request()
	{
		return $this->create_request();
	}
	public function produce_range_query()
	{
		$id_group=$this->id_group();
		return
		[
			'action'=>'select',
			'table'=>$id_group::$default_table,
			'where'=>['id'=>$this->ids()]
		];
	}
}

// этот выборщик нужен для навешивания сверху на сложные выборщики, не выполнимые в один запрос, когда от них требуется поиск, постраничный выбор и так далее. он сначала выполняет сложный выборщик, потом оперирует найденными айдишниками.
class Select_special_from_complex extends Select_from_ids
{
	public
		$complex;

	public static function from_ids($ids, $id_group=null)
	{
		die('DEFUNCT FACTORY');
	}
	
	public static function from_select($complex_select)
	{
		$id_group=$complex_select->id_group();
		$model=['id_group'=>$id_group];
		$select=static::from_model($model);
		$select->complex=$complex_select;
		return $select;
	}
	
	public function ids()
	{
		if ($this->ids!==null) return parent::ids();
		if (!$this->completed()) die('PREMATURE IDS REQUEST');
		
		$this->ids=[];
		foreach ($this->resolution->values as $entity)
		{
			$this->ids[]=$entity->db_id;
		}
		return $this->ids;
	}
	
	public function progress()
	{
		if ($this->complex->successful()) $this->finish_with_resolution($this->complex->resolution);
		elseif ($this->complex->failed()) $this->impossible($this->complex->errors);
		else $this->register_dependancy($this->complex);
	}
	
	public function delayed_call_report($method, $args=[])
	{
		$call=new Call([$this, $method], ...$args);
		return $this->sign_report(new \Report_task(Task_delayed_call::with_call($call, $this)));
	}
	
	public function select_page($order='id', $page_var='p', $perpage=50)
	{
		if (!$this->completed()) return $this->delayed_call_report('select_page', [$order, $page_var, $perpage]);
		return parent::select_page($order, $page_var, $perpage);
	}
	
	public function select_limited($limit=20, $order='id')
	{
		if (!$this->completed()) return $this->delayed_call_report('select_limited', [$limit, $order]);
		return parent::select_limited($limit, $order);
	}
	
	public function select_search($search)
	{
		if (!$this->completed()) return $this->delayed_call_report('select_search', [$search]);
		return parent::select_search($search);
	}
	
	public function select_random($limit=20)
	{
		if (!$this->completed()) return $this->delayed_call_report('select_random', [$limit]);
		return parent::select_random($limit);
	}
	
	public function select_ordered($order='id')
	{
		if (!$this->completed()) return $this->delayed_call_report('select_ordered', [$order]);
		return parent::select_ordered($order);
	}
	
	public function extract_count()
	{
		if (!$this->completed()) return $this->delayed_call_report('extract_count');
		return parent::extract_count();
	}
	
	public function extract_stats($stats)
	{
		if (!$this->completed()) return $this->delayed_call_report('extract_stats', [$stats]);
		return parent::extract_stats($stats);
	}
	
	public function create_standard_request()
	{
		if (!$this->completed()) die('UNIMPLEMENTED YET: complex standard request');
		return parent::create_standard_request();
	}
	public function produce_range_query()
	{
		if (!$this->completed()) die('UNIMPLEMENTED YET: complex range query');
		return parent::produce_range_query();
	}
}

trait Select_complex
{
	public $proxy_selector;
	public function proxy_selector()
	{
		if ($this->proxy_selector===null) $this->proxy_selector=Select_special_from_complex::from_select($this);
		return $this->proxy_selector;
	}

	public function select_modified_by_calls(...$calls)
	{
		return $this->proxy_selector()->select_modified_by_calls(...$calls);
	}
	
	public function select_filtered($filter_call)
	{
		die('UNIMPLEMENTED YET: multiple filters');
	}
	
	public function select_page($order='id', $page_var='p', $perpage=50)
	{
		return $this->proxy_selector()->select_page($order, $page_var, $perpage);
	}
	
	public function select_limited($limit=20, $order='id')
	{
		return $this->proxy_selector()->select_limited($limit, $order);
	}
	
	public function select_search($search)
	{
		return $this->proxy_selector()->select_search($search);
	}
	
	public function select_random($limit=20)
	{
		return $this->proxy_selector()->select_random($limit);
	}
	
	public function select_ordered($order='id')
	{
		return $this->proxy_selector()->select_ordered($order);
	}
	
	public function extract_count()
	{
		return $this->proxy_selector()->extract_count();
	}
	
	public function extract_stats($stats)
	{
		return $this->proxy_selector()->extract_stats($stats);
	}
}
?>