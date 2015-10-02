<?
namespace Pokeliga\Retriever;

class Cache
{
	use \Pokeliga\Entlink\Multiton;
	
	public
		$cache_code,
		$cache_num,
		$expiry;
	
	public function __construct($cache_key, $cache_num=null, $expiry=null)
	{
		if (is_array($cache_key))
		{
			$this->cache_code=$cache_key['code'];
			if (array_key_exists('num', $cache_key) $this->cache_num=$cache_key['num'];
			if (array_key_exists('expiry', $cache_key) $this->expiry=$cache_key['expiry'];
		}
		else
		{
			$this->cache_code=$cache_key;
			$this->cache_num=$cache_num;
			$this->expiry=$expiry;
		}
	}
	
	public function is_numbered() { return $this->cache_num!==null; }
	
	public function retrieve()
	{
		return $this->create_retrieve_ticket()->get_data_set();
	}
	
	public function save($content)
	{
		return $this->create_save_ticket($content)->get_data_set();
	}
	
	public function reset()
	{
		return $this->create_reset_ticket()->get_data_set();
	}
	
	public function create_retrieve_ticket()
	{
		if ($this->is_numbered()) return new RequestTicket('Request_cache_numbered', [$this->cache_code], [$this->cache_num]);
		else return new RequestTicket('Request_cache_numbered', [], [$this->cache_num]);
	}
	
	public function create_save_ticket($content)
	{
		$query=
		[
			'action'=>'replace',
			'table'=>'info_cache',
			'value'=>
			[
				'code'=>$this->cache_code,
				'num'=>$this->cache_num===null ? 1 : $this->cache_num,
				'expires'=>$this->expiry===null ? (time()+$this->cache_key['expiry']) : null,
				'content'=>$content
			]
		];
		return new RequestTicket('Request_insert', [$query], []);
	}
	
	public function create_reset_ticket()
	{
		$query=
		[
			'action'=>'delete',
			'table'=>'info_cache',
			'where'=>['code'=>$this->cache_code, 'num'=>$this->cache_num===null ? 1 : $this->cache_num]
		];
		return new RequestTicket('Request_delete', [$query], []);
	}
}

class Task_retrieve_cache extends Task
{
	use Task_processes_request;
	
	const
		NUMBERED_REQUEST_CLASS='Request_cache_numbered',
		UNNUMBERED_REQUEST_CLASS='Request_cache_unnumbered';
	
	public
		$cache_key;
	
	public static function with_cache_key($cache_key)
	{
		$task=new static();
		$task->cache_key=$cache_key;
		return $task;
	}
	
	public function create_request_ticket()
	{
		if (array_key_exists('num', $this->cache_key)) return new RequestTicket(static::NUMBERED_REQUEST_CLASS, [$this->cache_key['code']], [$this->cache_key['num']]);
		else return new RequestTicket(static::UNNUMBERED_REQUEST_CLASS, [], [$this->cache_key['code']]);
	}
	
	public function apply_data($data)
	{
		if ( (empty($data)) || ($data instanceof \Report_impossible) ) $this->impossible('no_cache');
		else $this->finish_with_resolution($data['content']);
	}
	
	public function save_cache($content)
	{
		$task=Task_save_cache::with_cache_key($this->cache_key, $content);
		return $this->sign_report(new \Report_task($task));
	}
}

class Task_save_cache extends Task_retrieve_cache
{
	public
		$content;
		
	public static function with_cache_key($cache_key, $content=null /* COMP */)
	{
		$task=parent::with_cache_key($cache_key);
		$task->content=$content;
		return $task;
	}

	public function create_request_ticket()
	{
		$query=
		[
			'action'=>'replace',
			'table'=>'info_cache',
			'value'=>
			[
				'code'=>$this->cache_key['code'],
				'num'=>((array_key_exists('num', $this->cache_key))?($this->cache_key['num']):(1)),
				'expires'=>((array_key_exists('expiry', $this->cache_key))?(time()+$this->cache_key['expiry']):(null)),
				'content'=>$this->content
			]
		];
		return new RequestTicket('Request_insert', [$query], []);
	}
	
	public function apply_data($data)
	{
		if ( (empty($data)) || ($data instanceof \Report_impossible) ) $this->impossible('cache_not_saved');
		else $this->finish();
	}
	
	public function save_cache($content)
	{
		if ($content!==$this->content) die('BAD CACHE CONTENT');
		if (!$this->completed()) return $this->sign_report(new \Report_task($this));
		return $this->report();
	}
}

class Task_reset_cache extends Task_retrieve_cache
{
	const
		NUMBERED_REQUEST_CLASS='Request_cache_numbered_reset',
		UNNUMBERED_REQUEST_CLASS='Request_cache_unnumbered_reset';
		
	public function apply_data($data)
	{
		$this->finish();
	}
	
	public function save_cache($content)
	{
		die('NO SAVE FOR RESET');
	}
}

class Request_cache_numbered extends Request_by_unique_field
{
	public
		$code;
		
	public function __construct($code)
	{
		$this->code=$code;
		parent::__construct('info_cache', 'num');
	}
	
	public function create_query()
	{
		$query=parent::create_query();
		$query['where']['code']=$this->code;
		$query['where'][]=['expression'=>'({{expires}} IS NULL OR {{expires}}>'.time().')'];
		return $query;
	}
}

class Request_cache_unnumbered extends Request_by_unique_field
{
	use Singleton;
	
	public function __construct()
	{
		parent::__construct('info_cache', 'code');
	}
	
	public function create_query()
	{
		$query=parent::create_query();
		$query['where']['num']=1;
		$query['where'][]=['expression'=>'({{expires}} IS NULL OR {{expires}}>'.time().')'];
		return $query;
	}
}

class Request_cache_numbered_reset extends Request
{
	use Multiton, Request_get_data_one_arg;
	
	public
		$code,
		$nums=[];
		
	public function __construct($code)
	{
		$this->code=$code;
	}
	
	public function set_data($nums=[])
	{
		if (!is_array($nums)) $nums=[$nums];
		$new_nums=array_diff($nums, $this->nums);
		if (count($new_nums)==0) return false;
		$this->nums=array_merge($this->nums, $new_nums);
		return true;
	}
	
	public function create_query()
	{
		if (empty($this->nums)) return null;
		$query=
		[
			'action'=>'delete',
			'table'=>'info_cache',
			'where'=>['code'=>$this->code, 'num'=>$this->nums]
		];
		return $query;
	}
	
	// возврата данных не требуется.
	public function compose_data($nums=[]) { }
	
	/*
	public function data_processed()
	{
		$this->nums=[];
	}
	*/
}

class Request_cache_unnumbered_reset extends Request
{
	use Multiton, Request_get_data_one_arg;
	
	public
		$codes=[];
	
	public function set_data($codes=[])
	{
		if (!is_array($codes)) $codes=[$codes];
		$new_codes=array_diff($this->codes, $codes);
		if (!$new_codes) return false;
		$this->codes=array_merge($this->codes, $new_codes);
		return true;
	}
	
	public function create_query()
	{
		if (empty($this->codes)) return null;
		$query=
		[
			'action'=>'delete',
			'table'=>'info_cache',
			'where'=>['code'=>$this->codes, 'num'=>1]
		];
	}
	
	// возврата данных не требуется.
	public function compose_data($codes=[]) { }
	
	/*
	public function data_processed()
	{
		$this->codes=[];
	}
	*/
}
?>