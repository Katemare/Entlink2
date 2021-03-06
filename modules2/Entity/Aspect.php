<?
namespace Pokeliga\Entity;

// 'basic' - аспект, необходимый для показа базовой информации о сущности на разных страницах. Если сущность просто упоминается, то в идеале basic - единственный требуемый аспект.

class Aspect implements \Pokeliga\Template\Templater
{
	use \Pokeliga\Entlink\StaticOwner;
	
	const
		MODEL_MODIFIED=false,
		MODEL_REDECLARED=false,
		
		STANDARD_PAGE_CLASS='Page_view_from_db';
	
	static
		$static_group_default=
		[
			'init'=>false
		],
		$init=false,
		$common_model=[],
		// $modify_model=[], // этот параметр должен быть у унаследованных аспектов, например, как аспект Pokemon_owned унаследован от Pokemon_disposition. считывается только параметр последнего класса в цепочке наследования, у которого был объявлен этот параметр.
		$templates=[],
		$tasks=[],
		$rights=[], // права похожи на задачи, но отличаются тем, что обязательно обрабатывают пользователя, а также запрашиваются по очереди у всех аспектов, у которых такое право перечислено.
		$pages=[],	// отвечают за страницы (Page) и их адреса (URL).
		$basic=false; // должен быть установлен на истину в базовых аспектах, данные которых содержат айди и так далее. STUB: возможно, в будущем будет интерфейсом или чертой.
	
	public
		$entity;
	
	public function __construct()
	{
		static::init();
		$this->model=static::$common_model; // до тех пор, пока ни в одну из этих переменных не вносится изменений, они занимают единое место в памяти, то есть затраты память равны всего лишь по массиву на класс аспекта.
	}
	
	public static function init()
	{
		$static_data=&static::get_static_group(static::$static_group_default);
		if ($static_data['init']) return; // вызывается таким образом для того, чтобы задать статическую группу.
		
		$parent_class=get_parent_class(get_called_class());
		if (static::$common_model===null) // значит, модель наследуется с изменениями у родительского класса.
		{	
			if (!empty($parent_class)) $parent_class::init(); // нельзя обратиться порсто parent::init(), потому что метод у всех классов, как правило, один, так что у его scope'а нет родительского метода. настоящая запись позволяет запустить тот же метод init() в рамках родительского класса.
			else die ('BAD ASPECT INHERITANCE: '.get_called_class());
			
			static::$common_model=$parent_class::$common_model;
			// при замене аспекта применяются только модифицированные поля, так что полная копия родительской модели не нужна.
			if ( (static::MODEL_MODIFIED===get_called_class()) && (property_exists(get_called_class(), 'modify_model')) )
			{
				foreach (static::$modify_model as $code=>$data)
				{
					static::$common_model[$code]=$data;
					static::init_id($code); // само разбирает, нужно ли добавлять поле сущности.
				}
			}
		}
		elseif ( $parent_class==='Pokeliga\Entity\Aspect' or static::MODEL_REDECLARED===get_called_class() )
		{
			foreach (static::$common_model as $code=>&$data)
			{
				static::init_id($code, true); // само разбирает, нужно ли добавлять поле сущности.
			}
			
			foreach (static::$pages as $code=>&$data)
			{
				if (!array_key_exists('page_action', $data)) $data['page_action']=$code;
			}
			
			if ( static::$basic and !array_key_exists('id', static::$common_model) )
			{
				static::$common_model['id']=
				[
					'type'=>'own_id',
				];
			}
		}
		else { vdump(get_called_class()); die('BAD ASPECT MODEL'); }
		
		$static_data['init']=true;
	}
	
	public static function init_id($code, $do_import=false)
	{
		$id_model=static::$common_model[$code];
		$value_type_class=\Pokeliga\Data\ValueType::get_shorthand_class($id_model['type']);
		
		if ($do_import and is_a($value_type_class, '\Pokeliga\Entity\Value_links_entity', true) and array_key_exists('import', $id_model) )
				static::init_import($code, $id_model['import']);
	}
	
	public static function init_import($source_entity, $import)
	{
		if (!is_array($import)) $import=[$import];
		foreach ($import as $source_code=>$target_code)
		{
			if (is_numeric($source_code)) $source_code=$target_code;
			if (array_key_exists($target_code, static::$common_model)) { vdump(get_called_class()); die ('VALUE CODE OVERLAP'); }
			static::$common_model[$target_code]=
			[
				'type'=>'reference',
				'keeper'=>false,
				'source_entity'=>$source_entity, // код значения, хранящего ссылку на сущность.
				'dependancies'=>[$source_entity],
				'source_code'=>$source_code
			];
		}	
	}
	
	public static function for_entity($class, $entity)
	{
		$aspect=new $class;
		$aspect->entity=$entity;
		return $aspect;
	}
	
	public function pool()
	{
		return $this->entity->pool;
	}
	
	public function default_table()
	{
		if (!empty(static::$default_table)) return static::$default_table;
		return $this->entity->type->default_table();
	}
	
	public function template($name, $line=[])
	{
		if ($name==='id_group') return $this->entity->id_group;
		if (!array_key_exists($name, static::$templates)) return $this->sign_report(new \Report_impossible('no_template'));
		
		$do_setup=true; // если не инициировать эту переменную, то она инициируется при попадании в метод нулём.
		$template=$this->make_complex_template($name, $line, $do_setup);
		if ($template!==null)
		{
			if ( ($do_setup) && ($template instanceof \Pokeliga\Template\Template) ) $this->setup_template($template);
			return $template;
		}
		
		$data=static::$templates[$name];
		$db_key=null;
		$class=null;
		if (is_array($data))
		{
			$class=reset($data);
			$db_key=substr(next($data), 1);
		}
		elseif (is_string($data))
		{
			if ($data{0}==='#')
			{
				$db_key=substr($data, 1);
				$class='Template_from_db';
			}
			else $class=$data;
		}
		elseif ($keyword===true)
		{
			// попытка уже была предпринята выше.
			die('NO COMPLEX TEMPLATE');
		}
		
		if ($class===null) { vdump($data); vdump(get_class($this)); die ('NO TEMPLATE CLASS'); }
		$template=$class::with_line($line);
		if ($db_key!==null) $template->db_key=$db_key;
		$this->setup_template($template);
		return $template;		
	}
	
	public function setup_template($template)
	{
		if (empty($template->context)) $template->context=$this->entity;
	}
	
	public function make_complex_template($name, $line=[], &$do_setup=true /* возвращает вызвавшему инструкцию, следует ли ему инициировать шаблон (если true) или это уже сделано (если false) */)
	{
	}
	
	public function task($code, ...$args)
	{
		$class=static::$tasks[$code];
		if ($class===true) return $this->make_custom_task($code, $args);
		$task=$class::for_entity($this->entity, $args);
		return $task;
	}
	
	public function make_custom_task($code, $args=[])
	{
		die('UNKNOWN TASK: '.var_export($code, true));
	}
	
	public function task_request(...$args)
	{
		return $this->task(...$args); // отличаются тем, как их обрабатывает EntityType
	}
	
	// возвращает либо готовый результат (из констант EntityType - RIGHT_FINAL_ALLOW, RIGHT_FINAL_DENY, RIGHT_WEAK_ALLOW, RIGHT_WEAK_DENY, RIGHT_NO_CHANGE), либо \Report_impossible, либо \Report_task с задачей, разрешением которой станет искомая константа.
	public function supply_right($right, $user, ...$more_args)
	{
		// if (!array_key_exists($right, static::$rights)) return EntityType::RIGHT_NO_CHANGE;
		if (!array_key_exists($right, static::$rights)) die('BAD RIGHT: '.$right);
		
		$right_data=static::$rights[$right];
		if (is_numeric($right_data)) return $right_data; // готовый результат, одна из констант.
		if ($right_data===true) return $this->has_right($right, $user, ...$more_args); // сразу обратиться к has_right.
		if (is_string($right_data))
		{
			$class=$right_data;
			$task=$class::for_right(func_get_args(), $this);
			return $this->sign_report(new \Report_task($task));
		}
		if (!is_array($right_data)) die('BAD RIGHT DATA');
		
		if ( (array_key_exists('anon', $right_data)) && (empty($user)) ) return $right_data['anon'];
		if (array_key_exists('user_right', $right_data))
		{
			if (empty($user)) return EntityType::RIGHT_WEAK_DENY;
			$task=$user->task_request('check_right', ...array_values($right_data));
			return $task; // \Report
		}
		
		if (array_key_exists('task', $right_data)) $class=$right_data['task'];
		else $class='Task_calc_aspect_right'; // запрашивает необходимые поля, а затем вызывает has_right() аспекта с теми же аргументами.
		$task=$class::from_data($right_data, func_get_args(), $this);
		return $this->sign_report(new \Report_task($task));
	}
	
	public function page($action, $parts=[], $route=[])
	{
		if (!empty($action))
		{
			$route['entity_action']=$action;
		}
		$page_code=$this->find_page($action, $route);
		if (empty($page_code)) return;
		if (empty($action))
		{
			$route['url_formation'] = (empty($route['entity_hint']) ? Router::URL_MODULE_TYPE_ID : Router::URL_MODULE_TYPE_ID_HINT);
		}
		else
		{
			$route['url_formation'] = (empty($route['entity_hint']) ? Router::URL_MODULE_TYPE_ID_ACTION : Router::URL_MODULE_TYPE_ID_HINT_ACTION);
		}
		$page_data=static::$pages[$page_code];
		$page=static::spawn_page_by_data($page_data, $parts, $route, static::STANDARD_PAGE_CLASS);
		if (empty($page)) return;
		if ($page instanceof Page_entity) $page->set_entity($this->entity);
		return $page;
	}
	
	public function find_page(&$action, $route)
	{
		$found=null;
		foreach (static::$pages as $code=>$data)
		{
			if ($action===null)
			{
				if ($data['default'])
				{
					$found=$code;
					break;
				}
			}
			else
			{
				if ($action===$data['page_action'])
				{
					$found=$code;
					if (!empty($data['default'])) $action=null;
					break;
				}
			}
		}
		
		if ($found===null) return;
		foreach ($route as $route_code=>$slug)
		{
			if (!array_key_exists($route_code, $data)) continue;
			if (is_array($data[$route_code]))
			{
				if (!in_array($slug, $data[$route_code])) return;
			}
			else
			{
				if ($slug!==$data[$route_code]) return;
			}
		}
		return $found;
	}
	
	public function page_request($action, $parts=[])
	{
		return $this->page($action, $parts); // отличаются тем, как их обрабатывает EntityType
	}
	
	public function cloned_from_pool($pool) { }
	
	public function destroy() { }
}

?>