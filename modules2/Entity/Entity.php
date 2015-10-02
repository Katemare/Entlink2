<?
namespace Pokeliga\Entity;

/*

Логика новой модели такая. Часто бывает, что до доставания данных о сущности мы не можем наверняка сказать, что это за сущность - покемон, игрок, вид, комментарий, метка... - и, соответственно, какое у неё должно быть поведение, а зачастую даже в какой таблице искать данные. Мы также не можем заменить класс объекта или ссылки на этот объект всюду, где они имеются. В лучшем случае мы можем вложить во все сущности функционал посредника, который будет передавать запросы уточнённой, "настоящей" сущности, а это, как мне кажется, чревато.

Поэтому сущность - это объект-композиция, у которого есть вложенный объект "тип", который и меняется в зависимости от полученных данных. Сначала тип - объект класса "EntityType_untyped". Потом, допустим, мы выясняем, что сущность - покемон, и тип меняется на объект класса "Pokemon" (наследник EntityType). Позже тип может ещё уточняться. Кто знает, может, нам понадобится принципиальное различие в механике пикачу и мьюту.

У сущности может быть очень много поведения. Тех же покемонов можно тренировать, эволюционировать, меняться ими, брать их в приключения, сражаться... Это очень много функционала и много потенциально нужных данных. Нет смысла держать все эти данные и весь функционал, когда чаще всего нужна только часть. Отсюда - аспекты. Покемон делится на аспекты "тренировка", "эволюция", "обмен", каждый из которых знает о нужных ему данных, умеет их получать, а также имеет соответствующие операции. Объект соответствующего аспекта создаётся только тогда, когда требуются его данные или операции. До тех пор сущность только знает, что в принципе такой аспект есть и что некоторые ключевые слова следует адресовать к нему. Точнее, знает об этом не Entity, а объект класса EntityType. Это главная функция типа: знать об аспектах и превращать вызов $pokemon->is_egg() или $pokemon->hatch() в вызов соответствующего аспекта.

*/

load_debug_concern(__DIR__, 'Entity');

class Entity
	implements \Pokeliga\Template\Templater, \Pokeliga\Data\ValueHost, \Pokeliga\Data\Pathway, \Pokeliga\Template\Template_context, \Pokeliga\Entlink\Multiton_argument
{
	use \Pokeliga\Entlink\Object_id, \Pokeliga\Entlink\Caller_backreference, Logger_Entity, \Pokeliga\Template\Context_self;
	
	const
		LINK_TEMPLATE='link',
		JS_EXPORT_TEMPLATE='js_export',
		
		STATE_NEW=1, // новая сущность, ещё не сохранена.
		STATE_PROVIDED_ID=2, // сущность, как ожидается, существует в БД, и мы знаем её айди, но мы ещё это не проверили.
		STATE_EXPECTED_ID=3, // сущность, как ожидается, существует в БД, но мы ещё не знаем её айди. Например, мы ищем пользователя по его уникальному логину.
		STATE_VERIFIED_ID=4, // сущность существет в БД, нам известен айди и мы подтвердили её существование.
		STATE_FAILED=5, // после поиска сущности в БД (по айди или уникальным параметрам) её там не оказалось.
		STATE_VIRTUAL=6; // сущность не имеет отдельной записи в БД и не будет, а существует исключительно во время прогона скрипта. Например, есть сущность "вид покемонов", есть сущность "набор спрайтов вида покемонов", позволяющая разные окраски, но набор спрайтов по умолчанию, хотя представляется сущностью, в БД хранится в записи вида покемонов и не имеет своего айди из той же группы.
		
	public
		// возможно, какие-то или даже все из этих параметров стоит хранить в типе, чтобы легче было разбираться с ситуациями, когда мы создали сущность-пользователя по номеру и другую сущность-пользователя по нику, и оказалось, что это одна и даже сущность. Или когда мы скопировали сущность в отдельный пул и всякий раз, когда она обращается к другим сущностям, те тоже копируются в этот пул. Тогда объект класса Entity скорее служит посредником или указующей на данные о сущности.
		
		$db_id=null,
		$id_group=null,	// для категорий сущностей, которые хранятся в отдельных таблицах, а не в общем entities. сейчас это относится ко всем сущностям.
		$pool,
		$state,			// константа статуса, см. выше
		$provider=null, // данные для сущности в состоянии EXPECTED_ID, чтобы получить искомый айди.
		$type=null,		// типизатор - объект подкласса к EntityType
		$dataset=null,	// данные, которые должны быть общими для всех аспетов.
		$changed_from_db,		// связывается с соответствующим параметром пула.
		$aspects=[], 	// массив фрагментов сущности. Например, у сущности "покемон" есть типизатор "покемон", определяющий возможный состав фрагментов; и фрагменты "тренировка", "обмен", "эволюция" и прочие. Каждый представляет собой набор данных и задач.
		$aspect_determinators=[]; // текущие задачи, выясняющие аспекты, чтобы не создавать заново при повторном обращении.
	
	public function __construct()
	{
		$this->generate_object_id();
	}
	
	/*
	Стандартный порядок создания или получения сущности.
	
	От пула, с известным айди:
	1. Метод entity_from_db_id($id, $id_group=null) пула. Если сущности уже есть, она возвращается. Если нет, см. далее.
	2. Он вызывает статический метод from_db_id сущности.
	3. Тот для создания объекта сущности вызывает статический метод for_pool.
	4. После того, как объект сущности возвращён и его начальные параметры наполнены, вызывается метод setup() объекта сущности.
	5. В рамках setup() вызывается метод register_entity($entity) пула.
	
	От пула, с неизвестным айди:
	1. Метод entity_from_provider($id_group, $provider_data) пула. Наличие сущности проверить нельзя, так что преждевременное завершение не вариант.
	2. Он вызывает статический метод create_expected() сущности. Далее как шаги 3-5 предыдущего алгонитма.
	
	От сущности:
	1. Метод entity_from_db_id, entity_from_provider или другой. В аргументы поставляется ссылка на пул. Далее как шаги 3-5 первого алгоритма.
	
	*/
	
	// все сущности начинаются зедсь.
	public static function for_pool($id_group=null, $pool=null)
	{
		$entity=new Entity();
		$entity->id_group=$id_group;
		if ($pool===false) $pool=EntityPool::default_pool();
		$entity->pool=$pool;
		return $entity;
	}
	
	// сущность, у которой известен айди в БД, хотя его существование ещё следует проверить (и, возможно, уточнить тип).
	public static function from_db_id($db_id, $id_group=null, $pool=null)
	{
		if ($db_id==0) return; // FIX: должно возращать \Report_impossible, но нужно настроить всюду, чтобы понимало это.
		
		$entity=static::for_pool($id_group, $pool);
		$entity->state=static::STATE_PROVIDED_ID;
		$entity->db_id=(int)$db_id;
		$entity->changed_from_db=false;
		$entity->setup();
		return $entity;
	}
	
	// сущность, которая предположительно есть в БД, но айди которой неизвестен и будет заполнен автоматически другим процессом. Эта сущность не может подтвердить сама себя! FIX: когда будет понятно, в каких случаях нужны такие сущности, этот вариант будет уточнён.
	public static function create_expected($id_group=null, $pool=null)
	{
		$entity=static::for_pool($id_group, $pool);
		$entity->state=static::STATE_EXPECTED_ID;
		$entity->changed_from_db=false;
		$entity->setup();
		return $entity;
	}
	
	// сущность, которая предположительно есть в БД, айди которой неизвестен, но известны другие данные, по которым айди можно получить. Её существование ещё следует проверить (и, возможно, уточнить тип). Указание группы (предварительного типа) в таком случае обязательно, поскольку требуются данные из базового аспекта.
	public static function from_provider($provider_data, $id_group, $pool=null)
	{
		$entity=static::for_pool($id_group, $pool);
		$entity->state=static::STATE_EXPECTED_ID;
		$entity->provider=$provider_data;
		$entity->changed_from_from_db=false;
		$entity->setup();
		return $entity;
	}
	
	// сущность, созданная в рамках программы и потенциально пригодная для сохранения в БД (чтобы она была непригодна, статус нужно изменить на STATE_VIRTUAL). Тип ещё может быть уточнён вручную с помощью set_type() или данными. WIP: Если сущность будет сохранена, её данные должны соответствовать её типу.
	public static function create_new($id_group=null, $pool=null)
	{
		$entity=static::for_pool($id_group, $pool);
		$entity->state=static::STATE_NEW;
		$entity->changed_from_db=true;
		$entity->setup();
		return $entity;	
	}
	
	public static function create_virtual($id_group=null, $pool=null)
	{
		$entity=static::for_pool($id_group, $pool);
		$entity->state=static::STATE_VIRTUAL;
		$entity->setup();
		return $entity;	
	}
	
	public function setup()
	{
		$this->dataset=DataSet::for_entity($this);
		$this->auto_type();
		$this->pool->register_entity($this);
		$this->dataset->changed_from_db=&$this->changed_from_db;
		// связать эти переменные нужно позже регистрации сущности в пуле, потому что пул заменяет переменную changed_from_db сущности на свою и связываться нужно именно с ней, новой.
	}
	
	// если у сущности известен $id_group, то он совпадает с предположением о типе (с названием класса типа). Если нет, то сущность получает общий тип-заглушку. Последнее может быть у сущностей, которые хранятся не в отдельных, а в общих таблицах, например `entities`.
	public function auto_type($force=false /* если истина, то выполняет автотипирование, даже если тип уже проставлен. */ )
	{
		if ( (!$force) && ($this->type!==null) ) return;
		if ($this->id_group===null) { die('NO ENTITY TYPE'); } // $type='EntityType_untyped';
		else $type=$this->id_group;
		$this->set_type($type);
	}
	
	// устанавливает сущности тип, соответствующий коду. Код совпадает с названием класса типа.
	public function set_type($type_code)
	{
		$new_type=EntityType::for_entity($type_code, $this);
		while (($final_type=$new_type->resolve_type())!==$new_type) // этот метод также выполняет перетипирование сущности, если у той уже был какой-нибудь тип (например, мы не знали тип сущности, а потом узнали, что это покемон.
		{
			$new_type=$final_type;
		}
		$final_type->setup();
	}
	
	// возвращает объект аспекта по его коду - то есть ключу в массиве $aspects.
	public function get_aspect($aspect_code, $now=true)
	{
		if (array_key_exists($aspect_code, $this->aspects))
		{
			if (is_object($this->aspects[$aspect_code])) return $this->aspects[$aspect_code];
			if (is_string($aspect_class=$this->aspects[$aspect_code]))
			{
				$this->aspects[$aspect_code]=Aspect::for_entity($aspect_class, $this);
				return $this->aspects[$aspect_code];
			}
		}
		return $this->type->get_aspect($aspect_code, $now);
	}
	
	// удаляет аспект из соответствующего массива по его коду (ключу).
	public function remove_aspect($aspect_code)
	{
		if (!array_key_exists($aspect_code, $this->aspects)) return;
		if (is_object($this->aspects[$aspect_code])) $this->aspects[$aspect_code]->destroy();
		unset($this->aspects[$aspect_code]);
	}
	
	// возвращает отчёт: \Report_success - подтверждено и существует, либо не нуждается в подтверждении; \Report_task - нужно выполнить эту задачу, чтобы подтвердить; \Report_impossible - нельзя подтвердить или подтверждение провалено.
	public function verify($now=true)
	{
		if ($this->state===static::STATE_FAILED) return new \Report_impossible('failed_entity', $this);
		elseif (!$this->is_to_verify()) return new \Report_success($this); // NEW, VIRTUAL
		elseif ($this->state===static::STATE_PROVIDED_ID) $result=$this->verify_provided_id();
		elseif ($this->state===static::STATE_EXPECTED_ID) $result=$this->verify_expected_id();
		else die ('UNEXPECTED ENTITY STATE II: '.$this->state);
		if ( ($now) && ($result instanceof \Report_tasks) )
		{
			$result->complete();
			$result=$result->report();
		}
		return $result;
	}
	
	// возвращает true, false или \Report_tasks (если не $now). следует заметить, что данный метод назван как вопрос ("существуешь?"), а предыдущий - как команда ("подтвердись!").
	public function exists($now=true)
	{
		if ($this->state===static::STATE_VERIFIED_ID) return true;
		if ($this->state===static::STATE_FAILED) return false;
		if ($this->not_loaded()) return false;
		if ($this->is_to_verify())
		{
			$result=$this->verify($now);
			if ($result instanceof \Report_final) return $this->exists();
			return $result; // \Report_tasks
		}
		die ('UNEXPECTED ENTITY STATE I: '.$this->state);
	}
	
	public function is_editable()
	{
		return !$this->pool->read_only() && $this->state!==Entity::STATE_FAILED; // FIX: в будущем будет также учитывать, есть ли у сущности известный тип. сейчас тип всегда известный.
	}
	public function is_saveable()
	{
		return $this->is_editable() && $this->state!==Entity::STATE_FAILED && $this->state!==Entity::STATE_VIRTUAL && $this->pool->saveable();
	}
	
	public function verify_provided_id()
	{
		$basic_aspect=$this->get_aspect('basic');
		$table=$basic_aspect->default_table();
		$data=\Pokeliga\Retriever\Request_by_id::instance($table)->get_data_set($this->db_id);
		if ($data instanceof \Report_task)
		{
			$task=Task_for_entity_verify_id::for_entity($this);
			return new \Report_task($task, $this);
		}
		elseif ($data instanceof \Report_impossible)
		{
			$this->state=static::STATE_FAILED;
			return $data;
		}
		else
		{
			$this->verified();
			return new \Report_success($this);
		}
	}
	
	public function verified()
	{
		$this->state=Entity::STATE_VERIFIED_ID;
		$this->make_final_calls('verified');
	}
	
	public function verify_expected_id()
	{
		if (is_null($this->provider)) die ('PROVIDER-LESS ID RECOVERY UNIMPLEMENTED');
		if ($this->provider instanceof \Pokeliga\Task\Task) return new \Report_task($this->provider, $this);
		
		if (is_string($this->provider)) $provider_code=$this->provider;
		elseif (is_array($this->provider)) $provider_code=$this->provider[0];
		else die ('BAD PROVIDER DATA');
		
		$provider=Provider::from_prototype($provider_code);
		$provider->setup($this);
		$this->provider=$provider;
		$this->log('provider');
		return new \Report_task($provider, $this);
	}
	
	public function receive_db_id($id)
	{
		// FIX: здесь требуются действия в случае, если сущность с таким айди уже есть. скорее всего, замена содержимого.
		if (!$this->expects_db_id()) die ('DOUBLE RECEIVE ID');
		$id=(int)$id;
		$this->db_id=$id;
		$this->state=static::STATE_VERIFIED_ID;
		$this->dataset->set_value('id', $id, Value::NEUTRAL_CHANGE); // обращение напрямую, поскольку это обычно выполняется внутри сложных операций, а о существовании 'id' мы и так наверняка знаем.
		// айди либо найден в БД, и тогда это изменение само по себе не нарушает картины их хранилища; либо получил в результате сохранения, и тогда картина уже нарушена.
		$this->make_calls('received_id');
	}
	
	// применяется к сущностям, которые, как выясняется, совпадают с другими сущностями. только для вызова объектом EntityPool! потому что должно быть вызвано сразу после подтверждения сущности.
	public function equalize($source)
	{
		if (!$this->equals($source)) { vdump($this); vdump($source); die('BAD EQUALIZE 1'); }
		if ($this->state===static::STATE_EXPECTED_ID) { vdump($this); vdump($source); die('BAD EQUALIZE 2'); }
		if ($source->state===static::STATE_EXPECTED_ID) { vdump($this); vdump($source); die('BAD EQUALIZE 3'); }
		
		$this->type=$source->type;
		$this->dataset=$source->dataset;
		$this->changed_from_db=&$source->changed_from_db;
		$this->aspects=$source->aspects;
		$this->aspect_determinators=$source->aspect_determinators;
	}
	
	public function failed_db_id()
	{
		$this->state=static::STATE_FAILED;
		$this->make_calls('failed_id');
	}
	
	public function has_db_id()
	{
		return $this->db_id!==null; // может сработать в трёх состояниях: PROVIDED_ID, VERIFIED_ID и FAILED.
	}
	
	public function expects_db_id()
	{
		return
			$this->state===static::STATE_EXPECTED_ID ||
			$this->state===static::STATE_NEW;
	}
	
	public function is_to_verify()
	{
		return
			$this->state===static::STATE_PROVIDED_ID ||
			$this->state===static::STATE_EXPECTED_ID;
	}
	
	public function not_loaded()
	{
		return
			$this->state===static::STATE_NEW ||
			$this->state===static::STATE_VIRTUAL;
	}
	
	public function is_failed()
	{
		return $this->state===static::STATE_FAILED;
	}
	
	// FIX! похоже, эти методы не используются.
	public function validate_request()
	{
		return $this->type->validate_request();
	}
	
	public function validate()
	{
		$result=$this->validate_request();
		if ($result instanceof \Report_tasks)
		{
			$process=$result->create_process();
			$process->complete();
			$result=$process->report();
		}
		return $result;
	}
	
	public function set($value_code, $content, $source_code=\Pokeliga\Data\Value::BY_OPERATION)
	{
		$this->dataset->set_value($value_code, $content, $source_code);
	}
	
	public function set_by_array($data, $source_code=\Pokeliga\Data\Value::BY_OPERATION)
	{
		$this->dataset->set_by_array($data, $source_code);
	}
	
	public function save()
	{
		return $this->type->save();
	}
	
	public function human_readable()
	{
		return 'entity[pool'.$this->pool->object_id.':'.$this->object_id.'] - state '.$this->state.' of type '.get_class($this->type);
	}
	
	// для соответствия интерфейсу Templater
	public function template($name, $line=[])
	{
		if ($name===static::JS_EXPORT_TEMPLATE) return $this->js_export_template($line);
		if ($name===null) $name=static::LINK_TEMPLATE;
		$args=[$name, $line];
		$template=$this->type->resolve_call('template', $args);
		if ($template instanceof \Report_impossible) return;
		return $template;
	}
	
	public function js_export_template($line=[])
	{
		$task=Template_entity_js_export::for_entity($this, $line);
		return $task;
	}
	
	// для соответствия интерфейсу ValueHost
	public function request($code)
	{
		return $this->type->resolve_call('request', [$code]);
	}
	
	public function value($code)
	{
		return $this->type->resolve_call('value', [$code]);
	}
	
	// для соответствия интерфейсу Pathway
	public function follow_track($track, $line=[])
	{
		return $this->type->follow_track($track);
	}
	
	public function spawn_page($type_slug, $parts=[])
	{
	}
	
	public function __call($name, $args) // теперь используется только для прямых вызовов типа $pokemon->owned(). FIX! и вообще стоит избавиться.
	{
		return $this->type->resolve_call($name, $args);
	}
	
	public function cloned_from_pool($pool)
	{
		$this->calls=[];
		
		if (!empty($this->type))
		{
			$this->type=clone $this->type;
			$this->type->entity=$this;
			$this->type->cloned_from_pool($pool);			
		}
		if (!empty($this->aspects))
		{
			$cloned_aspects=[];
			foreach ($this->aspects as $key=>$aspect)
			{
				if (is_object($aspect))
				{
					$aspect=clone $aspect;
					$aspect->entity=$this;
					$aspect->cloned_from_pool($pool);
					$cloned_aspects[$key]=$aspect;
				}
				else $cloned_aspects[$key]=$aspect;
			}
			$this->aspects=$cloned_aspects;
		}
		
		$this->dataset=clone $this->dataset;
		$this->dataset->changed_from_db=&$this->changed_from_db; // эта переменная клонированной сущности уже была связана с новым пулом в процессе клонирования, теперь черёд датасета.
		$this->dataset->cloned_from_pool($pool);
	}
	
	public function __clone()
	{
		$this->generate_object_id();
	}
	
	public function equals($entity)
	{
		if (!($entity instanceof Entity)) return false;
		if ($this->pool!==$entity->pool) return false;
		if (!$this->in_id_group($id_group)) return false;
		if ($this->db_id!==$entity->db_id) return false;
		return true;
	}
	
	public function in_id_group($id_group)
	{
		return $this->id_group===$id_group;
	}
	
	public function deleted()
	{
		$this->state=static::STATE_NEW;
		$this->db_id=null;
		foreach ($this->dataset->values as $value)
		{
			$value->save_changes=true;
		}
	}
	
	public function Multiton_argument()
	{
		return 'Entity'.$this->object_id;
	}
}

class Template_entity_link extends \Pokeliga\Template\Template_from_db
{
	public
		$db_key='standard.entity_link';
}
?>