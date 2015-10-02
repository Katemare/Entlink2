<?

namespace Pokeliga\Nav;

class RootHub extends RouterHub
{
	const
		ROUTE_GET_KEY='_route';
		
	public
		$engine,
		$module,
		$keywords=[],
		$patterns=[];
		
	public static function for_module($module)
	{
		$router=new static();
		$router->module=$module;
		$router->setup();
		return $router;
	}
	
	public function setup()
	{
		$this->engine=$this->module->engine;
	}
	
	public function index($route=null)
	{
		if ($route===null) $route=$this->input_route();
		$route=$this->prepare_route($route);
		$page=$this->route($route);
		if (empty($page) or $page instanceof \Report_impossible) $page=$this->bad_page($route);
		$page->process();
	}
	
	// можно переделать на использование InputSet'а, если потребуется сколько-нибудь более сложная логика.
	public function input_route()
	{
		global $_GET;
		if (array_key_exists(static::ROUTE_GET_KEY, $_GET)) return $_GET[static::ROUTE_GET_KEY];
	}
	
	// можно переделать на использование InputSet'а, тогда значение будет само разбирать валидность пути.
	public function prepare_route($route)
	{
		if ($route===null) return [];
		if (is_string($route)) return explode('/', $route);
		return (array)$route;
	}
	
	public function bad_page($route)
	{
		$bad_page_class=$this->module->get_config('bad_page_class');
		$page=$bad_page_class::with_route($route);
		return $page;
	}
	
	public function fallback_page($route)
	{
		return $this->bad_page($route);
	}
	
	public function route($route)
	{
		if (!empty($result=$this->simple_route($route))) return $result;
		if (!empty($result=$this->extended_route($route))) return $result;
		if (!empty($result=$this->complex_route($route))) return $result;
		return $this->fallback_page($route);
	}
	
	public function simple_route($route)
	{
		$keyword=reset($route);
		if ($keyword===false) return;	
		if (!empty($result=$this->route_by_keyword($keyword))) return $result;
		if (!empty($result=$this->route_by_pattern($keyword))) return $result;
	}
	
	public function extended_route($route)
	{
		$keyword=reset($route);
		if ($keyword===false) return;
		
		// FIX: должно быть сделано иначе, через какой-нибудь модуль кэша.
		$query=
		[
			'action'=>'select',
			'table'=>'info_cache',
			'where'=>['code'=>'Nav:RootHub']
		];
		$result=Retriever()->run_query($query);
		if (empty($result) or $result instanceof \Report_impossible) return;
		$result=unserialize(reset($result));
		
		$new_keywords=array_key_diff($result['keywords'], $this->keywords);
		$new_patterns=array_key_diff($result['patterns'], $this->patterns);
		
		if (!empty($new_keywords))
		{
			$this->keywords=array_merge($this->keywords, $new_keywords);
			if (!empty($result=$this->route_by_keyword($keyword, $new_keywords))) return $result;
		}
		
		if (!empty($new_patterns))
		{
			$this->patterns=array_merge($this->patterns, $new_patterns);
			if (!empty($result=$this->route_by_pattern($keyword, $new_patterns))) return $result;
		}
	}
	
	public function complex_route($route) { }
	
	public function route_by_keyword($keyword, $keywords=null)
	{
		if ($keywords===null) $keywords=$this->keywords;
		if (array_key_exists($keyword, $keywords)) return $this->route_to($route, $keywords[$keyword]);
	}
	
	public function route_by_pattern($keyword, $patterns=null)
	{
		if ($patterns===null) $patterns=$this->patterns;
		foreach ($patterns as $pattern=>$router)
		{
			if (preg_match($pattern, $keyword)) return $this->route_to($route, $router); 
		}
	}
	
	public function route_to($route, $target)
	{
		if ($target instanceof Router) return $target->route($route);
		return $this->spawn_page_by_data($target);
	}
	
	public function gather_keywords()
	{
		// WIP
	}
	
	public function gather_patterns()
	{
		// WIP
	}
	
########################################
### Для переходов и создания адресов ###
########################################

	// возвращает базовый адрес плюс строковую надстройку.
	public function url($add='')
	{
		$result=$this->engine->base_url.$add;
		return $result;
	}
	
	public function module_url($module, $add='')
	{
		return $this->engine->base_url . $this->engine->config['modules_dir'] . '/'.$module.'/'.$add;
	}
	
	public function module_address($module, $add='')
	{
		return $this->engine->modules_path.'/'.$module.'/'.$add;
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
		$result=$this->engine->host . $this->engine->current_url;
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