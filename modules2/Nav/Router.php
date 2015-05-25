<?

class Router
{
	use Report_spawner, Page_spawner;
	
	const
		URL_UNKNOWN				=0,
		URL_HOMEPAGE			=1,
		URL_MODULE				=2,
		URL_MODULE_ACTION		=3,
		URL_MODULE_TYPE			=4,
		URL_MODULE_TYPE_ACTION	=5,
		URL_MODULE_TYPE_ID		=6,
		URL_MODULE_TYPE_ID_ACTION=7,
		URL_MODULE_TYPE_ID_HINT	=8,
		URL_MODULE_TYPE_ID_HINT_ACTION=9;
		
	static
		$url_formations=
		[
			Router::URL_HOMEPAGE				=>
			[
				'formation'=>'',
				'parts'=>[]
			],
			Router::URL_MODULE				=>
			[
				'formation'=>'%module_slug%',
				'parts'=>['module_slug']
			],
			Router::URL_MODULE_ACTION		=>
			[
				'formation'=>'%module_slug%/%module_action%',
				'parts'=>['module_slug', 'module_action']
			],
			Router::URL_MODULE_TYPE			=>
			[
				'formation'=>'%module_slug%/%type_slug%',
				'parts'=>['module_slug', 'type_slug']
			],
			Router::URL_MODULE_TYPE_ACTION	=>
			[
				'formation'=>'%module_slug%/%type_slug%/%type_action%',
				'parts'=>['module_slug', 'type_slug', 'type_action']
			],
			Router::URL_MODULE_TYPE_ID		=>
			[
				'formation'=>'%module_slug%/%type_slug%/%entity_id%',
				'parts'=>['module_slug', 'type_slug', 'entity_id']
			],
			Router::URL_MODULE_TYPE_ID_ACTION=>
			[
				'formation'=>'%module_slug%/%type_slug%/%entity_id%/%enity_action%',
				'parts'=>['module_slug', 'type_slug', 'entity_id', 'entity_action']
			],
			Router::URL_MODULE_TYPE_ID_HINT	=>
			[
				'formation'=>'%module_slug%/%type_slug%/%entity_id%_%entity_hint%',
				'parts'=>['module_slug', 'type_slug', 'entity_id', 'entity_hint']
			],
			Router::URL_MODULE_TYPE_ID_HINT_ACTION=>
			[
				'formation'=>'%module_slug%/%type_slug%/%entity_id%_%entity_hint%/%entity_action%',
				'parts'=>['module_slug', 'type_slug', 'entity_id', 'entity_hint', 'entity_action']
			],
		];
	
	public function route($query=null)
	{
		if ($query===null) $query=InputSet::instant_fill('route', 'string', InputSet::SOURCE_GET);
		$query=$this->normalize_query($query);
		if ( (empty($query)) || ($query instanceof Report) ) return $this->spawn_homepage();
		return $this->spawn_page_by_query($query);
	}
	
	public function normalize_query($query)
	{
		if (is_string($query)) $query=explode('/', $query);
		return $query;
	}
	
	public function spawn_page_by_query($query)
	{
		$module_slug=array_shift($query);
		$module=Engine()->module_by_slug($module_slug);
		if (empty($module)) return $this->spawn_badpage();
		
		$page=$module->spawn_page($module_slug, $query);
		if (empty($page)) return $this->spawn_badpage($query);
		return $page;
	}
	
	public function spawn_homepage()
	{
		if (empty($data=Engine()->config['homepage'])) die('NO HOMEPAGE');
		$page=$this->spawn_page_by_data($data, null, static::URL_HOMEPAGE);
		return $page;
	}
	
	public function spawn_badpage($query=[])
	{
		if (empty($data=Engine()->config['badpage'])) die('BAD PAGE');
		$page=$this->spawn_page_by_data($data, $query, static::URL_UNKNOWN);
		return $page;
	}
	
// адаптировано из движка

	// возвращает базовый адрес плюс строковую надстройку.
	public function url($add='')
	{
		$result=Engine()->base_url.$add;
		return $result;
	}
	
	public function module_url($module, $add='')
	{
		return Engine()->base_url . Engine()->config['modules_dir'] . '/'.$module.'/'.$add;
	}
	
	public function module_address($module, $add='')
	{
		return Engine()->modules_path.'/'.$module.'/'.$add;
	}
	
	// возвращает текущий адрес плюс аргументы
	public function url_args_only($args=null)
	{
		if ($args===null) return $this->url_self();
		$args=$this->compose_url_args($args);
		$result=$this->url_self().$args;
		return $result;
	}
	
	// возвращает строго текущий адрес
	public function url_self()
	{
		$result=Engine()->host . Engine()->current_url;
		return $result;
	}	
	
	// превращает массив аргументов для URL в их строковую запись для адресной строки.
	public function compose_url_args($args=null)
	{
		if ($args===null) $result='';
		elseif (is_string($args)) $result=$args;
		elseif (is_array($args))
		{
			$result=array();
			foreach ($args as $arg=>$value)
			{
				$result[]=$arg.'='.urlencode($value);
			}
			if (count($result)<1) $result='';
			else $result='?'.implode('&', $result);
		}
		return $result;
	}
	
	public function compose_url($url, $args)
	{
		if ($url===null) $url=$this->url_self();
		$parsed_url=parse_url($url);
		if (array_key_exists('query', $parsed_url)) parse_str($parsed_url['query'], $query);
		else $query=[];
		foreach ($args as $key=>$arg)
		{
			if ($arg!==null) continue;
			unset($query[$key]);
			unset($args[$key]);
		}
		$args=array_merge($query, $args);
		
		$parsed_url['query']=http_build_query($args);
		return $this->unparse_url($parsed_url);
	}
	
	public function compose_router_url($formation_code, $route, $query)
	{
		$url_formation=static::$url_formations[$formation_code];
		$insufficient=false;
		$base=preg_replace_callback
		(
			'/%(?<code>[a-z_]+)%/',
			function($m) use($route, &$insufficient)
			{
				if (!array_key_exists($m['code'], $route)) $insufficient=true;
				if ($insufficient) return '';
				return $route[$m['code']];
			},
			$url_formation['formation']
		);
		if ($insufficient) return $this->sign_report(new Report_impossible('insufficient_data'));
		if (!empty($query)) $base.='/'.implode('/', array_map(function($value) { return urlencode($value); }, $query));
		return $this->url($base);
	}
	
	public function unparse_url($parsed_url)
	{
		$scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
		$pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
		$pass     = ($user || $pass) ? "$pass@" : '';
		$path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
		$query    = !empty($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
		$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
		return "$scheme$user$pass$host$port$path$query$fragment";
	} 
	
	public function redirect($address, $args=null)
	{
		if (is_array($args)) $args=$this->compose_url_args($args);
		if (is_string($args)) $address.=$args;
		Header('Location:'.$address);
		exit;
	}
	
	public function get_back($add=null)
	{
		global $_SERVER;
		$back=$_SERVER['HTTP_REFERER'];
		$this->redirect($back, $add);
	}
}

?>