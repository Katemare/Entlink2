<?
load_debug_concern('Entity', 'Entity');

class Dependancy_call_aspect_shift extends Dependancy_call
{
	public
		$aspect_code;
		
	public function process_args()
	{
		if ($this->aspect_code!==null)
		{
			array_unshift($this->aspect_code, $this->post_args);
			$this->aspect_code=null;
		}
	}
	
	public function register_at_master()
	{
		if (!array_key_exists($this->aspect_code, $this->master->aspect_dependancies))
			$this->master->aspect_dependancies[$this->aspect_code]=[];
		$this->master->aspect_dependancies[$this->aspect_code][$this->object_id]=$this;
	}
	
	public function unregister_at_master()
	{
		unset($this->master->aspect_dependancies[$this->aspect_code][$this->object_id]);
	}
}

abstract class EntityType implements Templater, Pathway // на самом деле к типизатору в этом качестве обращается только сущность, но интерфейсам он всё равно соответствует.
{
	use Logger_Entity;
	
	const
		VALUE_NAME=1,
		TASK_NAME=2,
		TEMPLATE_NAME=3,
		RIGHT_NAME=4,
		
		// ответы проверки прав:
		RIGHT_FINAL_ALLOW=1,	// окончательное "да", несмотря на любые другие проверки.
		RIGHT_FINAL_DENY=2,		// окончательное "нет", несмотря на любые другие проверки.
		RIGHT_WEAK_ALLOW=3,		// слабое "да": если есть хотя бы одно, то в отсутствие слабых "нет" итог будет "да".
		RIGHT_WEAK_DENY=4,		// слабое "нет": хотя бы одно слабое "нет" означает отказ, но допускает возможность итогового "да" в цепочке.
		RIGHT_NO_CHANGE=0;	// если все результаты NO_CHANGE, то итог будет "нет".
		
	use Prototyper_bare, Report_spawner;
	
	static
//		$init=false,
		$data_model=[],		 // этот массив содержит общую модель данных, которая пока что не должна меняться при замене аспектов. а именно, не могут меняться типы данных, их хранение и зависимости. Способ заполнения данных и параметры валидации могут меняться.
		
		$map=[],			 // это список значений, задач и шаблонов в виде соответствий "код => [код аспекта, тип обращения]" (тип обращения - значение, задача или шаблон)
		
		$rights=[],
		
		$pathway_tracks=[], // карта для работы интерфейса Template_context
		
		$base_aspects=[],	 // базовые аспекты, из которых берётся модель данных.
		
		$variant_aspects=[]; // соответствия "код аспекта => класс задачи-детерминатора" для тех базовых аспектов, которые могут быть реализованы разными классами. Например, у покемонов есть код аспекта disposition, которому соответствует абстрактный базовый аспект Pokemon_disposition. В зависимости от того, принадлежит ли покемон тренеру, находится ли он в яйце и каково его расположение, у сущностей-покемонов этот аспект заполняется объектом класса Pokemon_owned, Pokemon_lab_egg, Pokemon_sheltered и так далее.
	
	public
		$entity,
		$stored_responses=[];
	
	public static function init()
	{
		if (static::$init) return;
		
		foreach (static::$base_aspects as $aspect_code=>$aspect_class)
		{
			$aspect_class::init();
			static::$data_model=array_merge(static::$data_model, $aspect_class::$common_model); // из моделей аспектов требуется только список значений, их типы и сведения для работы Кипера, но отделять нужное от ненужного - лишние ресурсы. Лишний расход памяти невелик благодаря принципу copy-on-write, а не нужное просто не используется.
			static::$map=array_merge
			(
				static::$map,
				array_fill_keys( array_keys($aspect_class::$common_model), [$aspect_code, static::VALUE_NAME] ),
				array_fill_keys( array_keys($aspect_class::$templates), [$aspect_code, static::TEMPLATE_NAME] ),
				array_fill_keys( array_keys($aspect_class::$tasks), [$aspect_code, static::TASK_NAME] )
			);
			if (!empty($aspect_class::$rights))
			{
				foreach ($aspect_class::$rights as $right=>$data)
				{
					if (!array_key_exists($right, static::$rights)) static::$rights[$right]=[$aspect_code];
					else static::$rights[$right][]=$aspect_code;
				}
			}
		}
		
		foreach (static::$data_model as $code=>$model)
		{
			if (!array_key_exists('pre_request', $model)) continue;
			if (!array_key_exists('dependancies', $model)) $model['dependancies']=[];
			static::$data_model[$code]['dependancies']=array_unique(array_merge($model['dependancies'], $model['pre_request']), SORT_REGULAR);
		}
		
		$dependants=array_fill_keys(array_keys(static::$data_model), []);
		foreach (static::$data_model as $code=>$model)
		{
			if (array_key_exists('dependancies', $model))
			{
				foreach ($model['dependancies'] as $key=>$dependancy)
				{
					if (is_array($dependancy)) unset($model['dependancies'][$key]); // попали сюда из pre_request.
					else $dependants[$dependancy][]=$code;
				}
			}
			if (array_key_exists('pathway_track', $model))
			{
				if ($model['pathway_track']===true) $context_code=$code;
				else $context_code=$model['pathway_track'];
				static::$pathway_tracks[$context_code]=$code;
			}
		}
		foreach ($dependants as $code=>$deps)
		{
			if (empty($deps)) continue;
			static::$data_model[$code]['dependants']=$deps;
		}
		
		static::$init=true;
	}
	
	public static function for_entity($type_code, $entity)
	{
		$type=static::from_prototype($type_code);
		$type::init();
		$type->entity=$entity;
		return $type;
	}
	
	public function setup()
	{
		if ( (!is_null($this->entity->type)) && ($this->entity->type!==$this) ) $this->retype_entity();
		// к этому моменту тип уже инициирован.
		$this->entity->dataset->model=static::$data_model; // если модель не меняется, то благодаря системе "copy on write" лишняя память не расходуется.
		$this->prepare_aspects();
	}
	
	// при текущем коде подготовка не требуется.
	public function prepare_aspects()
	{
	}
	
	// вызывается до того, как сущность запомнила созданный новый тип, если у неё уже есть другой тип.
	public function retype_entity()
	{
		die ('RETYPE UNIMPLEMENTED');
		$old_type=$this->entity->type;
		foreach ($this->entity->aspects as $aspect_code=>$aspect_data)
		{
			if (!array_key_exists($aspect_code, static::$aspects))
			{
				$this->entity->remove_aspect($aspect_code);
				continue;
			}
			$aspect_class=$this->resolve_aspect_class($aspect_code);
			if ( (is_string($aspect_data)) && ($aspect_data===$aspect_class) ) continue;
			if ( (is_object($aspect_data)) && (get_class($aspect_data)===$aspect_class) )
			{
				// тут можно скопировать закэшированные ответы, но пусть лучше наполняются по мере необходимости - аспекты и так должны их лихо отдавать.
				continue;
			}
			$this->entity->remove_aspect($aspect_code);
		}
	}
	
	// сюда скрипт попадает только в случае, если сущность сама не может разобраться. Это случается только с вариантными аспектами или когда запрошенный код аспекта вообще отсутствует в предварительном списке.
	public function get_aspect($code, $now=true)
	{
		if (!array_key_exists($code, static::$base_aspects)) { vdump($code); xdebug_print_function_stack(); debug_dump(); die ('UNKNOWN ASPECT: '.$code); }
		
		$class=static::$base_aspects[$code];
		if (array_key_exists($code, static::$variant_aspects))
		{
			if (array_key_exists($code, $this->entity->aspect_determinators)) $result=$this->entity->aspect_determinators[$code];
			else $result=$this->determine_aspect($code);
			if ($result instanceof Task)
			{
				if ($now)
				{
					$result->complete();
					$result=$result->report();
				}
				else return $this->sign_report(new Report_task($result));
			}
			if ($result instanceof Report_resolution) return $result->resolution; // в результате работы задачи аспект заполняется сам и устанавливаются нужные зависимости.
			elseif ($result instanceof Report_impossible) { debug_dump(); die ('CANT GET ASPECT: '.$code); }
			else die('BAD ASPECT REPORT: '.get_class($result));
		}
		$aspect=Aspect::for_entity($class, $this->entity);
		$this->entity->aspects[$code]=$aspect;
		return $aspect;
	}
	// FIX: Ошибкоопасное место. Допустим, скрипт вызвал этот метод с $now=false и получил в ответ отчёт с задачей. Эта задача уже наполовину выполнена, часть данных уже получена и обработана (справились без запроса в БД). Затем задача добавляется в процесс, который включает изменение этих параметров. Зависимость ещё не проведена, так что получившийся аспект может не соответствовать новым данным. Если же зависимость проведена, то у задачи всё равно пока нет возможности перейти на более раннюю стадию.
	
	// должна вернуть отчёт: Report_impossible - нельзя определить аспект; Report_resolution - содеращий аспект; или задача, результатом работы которой станет аспект. В двух последних случаев получаемый аспект должен быть уже зарегистрирован и связан зависимостями с приведённой сущностью. Обычно используется задача-наследник Task_determine_aspect.
	public function determine_aspect($aspect_code)
	{
		if (!array_key_exists($aspect_code, static::$variant_aspects)) die ('NO VARIANT ASPECT TASK');
		$class=static::$variant_aspects[$aspect_code];
		$task=$class::aspect_for_entity($aspect_code, $this->entity);
		return $task;
	}
	
	// этот метод вызывается в рамках задачи, устанавливающей вариантный аспект. Во время установки задача обращалась к некоторым значениям, которые могли измениться, так что нужно установить зависимость и сбросить аспект, если значения изменились.
	public $aspect_dependancies=[], $aspect_dependancy_call=null;
	public function record_aspect_dependancy($aspect_code, $value_codes)
	{
		if ($this->entity->pool->read_only()) $this->aspect_dependancies[$aspect_code]=$value_codes; // чтобы провести связи в случае клонирования в другой пул, который не только для чтения.
		else $this->apply_aspect_dependancies($aspect_code, $value_codes);
	}
	
	public function apply_aspect_dependancies($aspect_code, $value_codes)
	{
		if ($this->aspect_dependancy_call===null)
		{
			$this->aspect_dependancy_call=new Dependancy_call_aspect_shift
			(
				function($aspect_code)
				{
					$this->entity->remove_aspect($aspect_code);
					foreach ($this->aspect_dependancies[$aspect_code] as $call)
					{
						$call->unregister(); // OPTIM: здесь можно оптимизировать, если приказать вызовам только удалить себя из вызывающих объектов, а потом разом опустошить массив $this->aspect_dependancies[$aspect_code].
					}
				}
			);
			$this->aspect_dependancy_call->master=$this;
		}
		
		foreach ($value_codes as $value_code)
		{
			$value_object=$this->entity->value_object($value_code);
			$cloned_call=clone $this->aspect_dependancy_call;
			$cloned_call->aspect_code=$aspect_code;
			$cloned_call->register($value_object, 'set');
		}
	}
	// эта и многие последующие методы записаны в тип по двум причинам. Во-первых, статические параметры типизатора содержат многие сведения о структуре аспектов, которую необходимо анализировать. Во-вторых, в объекте сущности должны храниться только те данные, которые касаются взаимоотношений с типизатором и аспектами, чтобы в случае возникновения дубля сущности (когда после поиска оказывается, что у двух или более сущностей одинаковый айди) можно было просто скопировать наполнение сущности как можно меньшим числом операций.
	
	const
		SAVE_TASK='Task_save_entity',
		SAVE_NEW_TASK='Task_save_new_entity';
		
	public function save()
	{
		if (!$this->entity->is_saveable()) die('NON SAVEABLE ENTITY');
		if ($this->entity->state===Entity::STATE_NEW) $class=static::SAVE_NEW_TASK;
		else $class=static::SAVE_TASK;
		
		$task=$class::for_entity($this->entity);
		return $this->sign_report(new Report_task($task));
	}
	
	/*
	
	Есть следующие типы запросов к аспектам:
	1. Простые (не процедурные) данные, например, из БД или простая формула.
	2. Процедурные данные, такие как "это яйцо?", "активированный пользователь?" или "опыта до уровня?"
	2.1. Процедурные данные, имеющие аргументы, такие как права и шаблоны (см. ниже), проверки, может ли покемон занимать тот или иной слот.
	3. Операции, такие как "вылупить", "потренировать", "поставить на обмен".
	4. Права - имеет ли пользователь (или "я") право вылупить, потренировать, поставить на обмен...
	5. Создание шаблона для визуализации.
	
	Некоторые значения, будучи получены, меняться уже не будут. Например, при показе страницы большая часть данных будет "только для чтения", и их, как и зависимые от них данные, можно считать итоговыми, как только они получены. Вероятно, это должно определяться параметром основного пула. Это не исключает существования копий сущности в отдельном пуле, например, для проверки возможности отдать покемона в приют. При создании копии для таких экспериментов данные должны разблокироваться.
	
	Вызовы, разрешаемые этим методом:
	- $entity->meow() : в зависимости от того, является ли 'meow' значением или задачей, либо $aspect->value('meow'), либо $aspect->task('meow'), либо $aspect->template('meow'). После кода также могут передаваться дополнительные аргументы.
	(Далее вызов метода просто переадресовывается аспекту с тем же названием и аргументами.)
	- $entity->value('meow') - возвращает содержимое заполненного значения, даже если для этого придётся завершать процесс.
	- $entity->request('meow') - возвращает содержимое заполненого значения в форме отчёта или же задачу, если для заполнения содержимого требуется выполнить задачу.
	- $entity->task('meow') - выполняет задачу, которая не должна запоминать результата. Например, процедуру вылупления покемона.
	- $entity->task_request('meow') - возвращает готовую к выполнению задачу.
	- $entity->template('meow', $context=[]) - возвращает объект шаблона.
	- $entity->value_object('meow') - возвращает объект значения.
	- $entity->value_object_request('meow') - то же, но может вернуть задачу, по выполнении которой возникнет объект значения.
	
	- $entity->valid_content('meow') - проверенное содержимое поля DataSet или же Report_impossible, если содержимое отсутвует или плохое.
	- $entity->valid_content_request('meow') - как предыдущее, но может вернуть Report_impossible, а вместо непосредственного решения - Report_resolution.
	
	- $entity->right($user, 'meow') - возвращает право данного пользователя к указанной операции. Позволяет дополнительные аргуметы. Возвращает специальные константы или Report_impossible (например, если такого права не предусмотрено).
	- $entity->right_request($user, 'meow') - то же, что предыдущий пункт, но может вернуть Report_rasks, а вместо непосредственного решения возвращает Report_resolution.
	- $entity->my_right('meow') - то же, что right, но для текущего пользователя.
	- $entity->my_right_request('meow') - то же, что right_request, но для текущего пользователя.
	
	- $entity->model('meow') - возвращает модель значения у аспекта даже не утверждённой сущности (может требоваться для подтверждения сущности, ожидаемых не по айди). WIP: Ещё не ясно, как будет проходить подтверждение таких сущностей.
	
	Каждый код существует в единственном экземпляре в рамках всех аспектов. Например, если у аспекта 'basic' есть значение 'meow', то у него не должно быть задачи 'meow', и ни у какого другого аспекта не должно быть ни значений, ни задач с кодом 'meow'. Это включает также импортированные поля - такие как название вида у покемона, импортированное из сущности вида.
	
	Все операции выше должны выполняться после подтверждения сущности (то есть проверки существования её настоящего айди, если сущность не новая или виртуальная). Это необходимо для того, чтобы сущность окончательно определилась со своим типом и перестановок аспектов уже не происходило. Поэтому вызовы с request в названии могут в результате вернуть задачу, сначала проверяющую сущность, а потом уже разрешающую запрос, а остальные, если получают такую задачу, сразу выполняют её и уже возвращают результат.

	*/
	
	public static function locate_name($name, &$mode=null)
	// находит аспект из уже заданных, которому принадлежит данное имя, а также является ли оно значением или задачей.
	{
		if ($name==='id_group')
		{
			$mode=static::TEMPLATE_NAME;
			return 'basic';
		}
		if (array_key_exists($name, static::$rights))
		{
			$mode=static::RIGHT_NAME;
			return false; // означает обращение к типу.
		}
		if (array_key_exists($name, static::$map))
		{
			$mode=static::$map[$name][1];
			return static::$map[$name][0];
		}
	}
	
	// У методов ниже есть большой запас по оптимизации, если сделать их прямыми вызовами: value() у Entity обращается к value() у EntityType, та выполняет сокращённый кусок кода... Это создаст копипастный код, но как только будет ясно, что эта часть движка уже активно меняться не будет, молжно будет так поступить и получить прибавку к быстродействию.
	
	static
		// нельзя называть значения или задачи этими словами, котому что они ключевые (см. выше)
		$special_calls=
		[
			'value'=>EntityType::VALUE_NAME,
			'request'=>EntityType::VALUE_NAME,
			'task'=>EntityType::TASK_NAME,
			'task_request'=>EntityType::TASK_NAME,
			'template'=>EntityType::TEMPLATE_NAME,
			'value_object'=>EntityType::VALUE_NAME,
			'value_object_request'=>EntityType::VALUE_NAME,
			'valid_content'=>EntityType::VALUE_NAME,
			'valid_content_request'=>EntityType::VALUE_NAME,
			
			'right'=>EntityType::RIGHT_NAME,
			'right_request'=>EntityType::RIGHT_NAME,
			'my_right'=>EntityType::RIGHT_NAME,
			'my_right_request'=>EntityType::RIGHT_NAME,
			
			'model'=>EntityType::VALUE_NAME
		],
		
		$complete_process=['value'=>true, 'task'=>true, 'value_object'=>true, 'valid_content'=>true, 'right'=>true, 'my_right'=>true, 'model'=>true /* FIX: если запрашивается модель из вариантного аспекта, это вызовет дополнительные операции! */],
		// если в процессе разрешения этих запросов образуется задача, то её нужно сразу завершить. В противном случае нужно вернуть задачу.
		
		$storable_responses=['value'=>true, 'value_object'=>true, 'model'=>true, 'my_right'=>true],
		// возврат от этих вызовов можно запоминать при условии, что пул находится в режиме только чтения.
		
		$no_verify=['model'=>true, 'value_object'=>true, 'value_object_request'=>true];
		// эти вызовы не требуют предварительного подтверждения сущности. WIP: предположительно этот вызов используется для нахождения айди сущности, запрошенный по параметрам, но эта часть ещё не устаканена.
	
	public function receive_storable_response($name, $arg, $response)
	{
		if (!$this->entity->pool->read_only()) return;
		$storable_response_key=$name.'|'.$arg;
		$this->stored_responses[$storable_response_key]=$response;
	}
	
	public function resolve_call($name, $args)
	{
		$this->log('resolving_call', ['name'=>$name, 'args'=>$args]);
		$analysis=$this->analyze_call($name, $args);
		if ($analysis instanceof Report_impossible)
		{
			if ($name===static::TEMPLATE_NAME) return;
			return $analysis;
		}
		extract($analysis);
		$storable_response_key=null;
		if ( (array_key_exists($name, static::$storable_responses)) && ($this->entity->pool->read_only()) )
		{
			if (count($args)==1) $storable_response_key=$name.'|'.$args[0];
			// else ... // WIP Здесь можно придумать генерацию ключей и для параметрических значений, но пока не ясно, как это сделать.
			
			if ( ($storable_response_key!==null) && (array_key_exists($storable_response_key, $this->stored_responses)) ) return $this->stored_responses[$storable_response_key];
		}
		
		// для краткости; FIX! для краткости тут можно многое оптимизировать, например, строковые сравнения.
		if
		(
			($analysis['mode']===static::VALUE_NAME) &&
			(in_array($name, ['value', 'request', 'value_object', 'valid_content', 'valid_content_request'])) &&
			(!$this->entity->is_to_verify()) &&
			(array_key_exists($code=$analysis['code'], $this->entity->dataset->values))
		)
		{
			$value=$this->entity->dataset->produce_value($code);
			if ($name==='value_object') return $value;
			$result=$value->$name();
			return $result;
		}
		$task=Task_resolve_entity_call::for_entity($this->entity);
		$task->record_analysis($analysis);
		$task->storable_response_key=$storable_response_key;
		$result=$task->resolve();
		if (array_key_exists($name, static::$complete_process))
		{
			if ($result instanceof Report_tasks)
			{
				$task->complete();
				$result=$task->report();
			}
			if ($result instanceof Report_resolution) $result=$result->resolution;
		}
		elseif ( ($analysis['mode']===EntityType::TEMPLATE_NAME) && ($result instanceof Report_task) ) $result=$result->task;

		// STUB - пока не проверяет существование сущности.
		if ( ($storable_response_key!==null) && (! ($result instanceof Report_tasks)) ) $this->stored_responses[$storable_response_key]=$result;
		
		return $result;
	}
	
	public function analyze_call($name, $args)
	{
		if (array_key_exists($name, static::$special_calls))
		{
			if (!array_key_exists(0, $args)) return $this->sign_report(new Report_impossible('no_code'));
			$mode=static::$special_calls[$name];
			$code=$args[0];
			$aspect_code=static::locate_name($code, $detected_mode);
			if ($aspect_code===null) return $this->sign_report(new Report_impossible('code_not_found: '.$name));
			$default_mode=$detected_mode;
		}
		else
		{
			$code=$name;
			$aspect_code=static::locate_name($code, $mode);
			array_unshift($args, $code);
			if ($aspect_code===false) return $this->sign_report(new Report_impossible('code_not_found: '.$name));
			if ($mode===static::TASK_NAME) $name='task';
			elseif ($mode===static::TEMPLATE_NAME) $name='template';
			else $name='value';
			$default_mode=$mode;
		}
		
		$result=
		[
			'name'=>$name,
			'args'=>$args,
			'code'=>$code,
			'aspect_code'=>$aspect_code,
			'mode'=>$mode,
			'default_mode'=>$default_mode
		];
		return $result;
	}
	
	public function right_request(...$args)
	{
		$task=Task_calc_user_right::for_user_from_args($this->entity, $args);
		return $this->sign_report(new Report_task($task));
	}
	
	public function my_right_request(...$args)
	{
		$task=Task_calc_user_right::for_current_user_from_args($this->entity, $args);
		return $this->sign_report(new Report_task($task));
	}
	
	// в 5.4 нет способов сделать синоним функции. может быть, после оптимизации вызовов вызов будет сразу направляться в верную функцию.
	public function right(...$args)
	{
		return $this->right_request(...$args);
	}
	
	public function my_right(...$args)
	{
		return $this->my_right_request(...$args);
	}
	
	// для соответствия интерфейсу Templater, хотя обращение почти всегда должно идти через Entity
	public function template($name, $line=[])
	{
		if ($name===null) $name=Entity::LINK_TEMPLATE;
		array_unshift($line, $name);
		return $this->resolve_call('template', $line);
	}
	
	public function follow_track($track)
	{
		if ($track==='right') return $this->RightHost();
		if ($track==='my_right') return $this->MyRightHost();
		if ($track==='task') return $this->TaskHost();
		if (array_key_exists($track, static::$pathway_tracks))
		{
			$result=$this->resolve_call('request', [$code=static::$pathway_tracks[$track]]);
			if ($result instanceof Report_resolution) return $this->entity->dataset->produce_value($code); // STUB! здесь должно быть приспособление не только для получения данных, но и, к примеру, для получения задач.
			return $result;
		}
		
		return $this->sign_report(new Report_impossible('no_track 3'));
	}
	
	public $RightHost=null;
	public function RightHost()
	{
		if ($this->RightHost===null) $this->RightHost=RightHost::for_entity($this->entity);
		return $this->RightHost;
	}
	
	public $MyRightHost=null;
	public function MyRightHost()
	{
		if ($this->MyRightHost===null) $this->MyRightHost=MyRightHost::for_entity($this->entity);
		return $this->MyRightHost;
	}
	
	public $TaskHost=null;
	public function TaskHost()
	{
		if ($this->TaskHost===null) $this->TaskHost=TaskHost::for_entity($this->entity);
		return $this->TaskHost;
	}
	
	// STUB: в будущем это должно выполняться с учётом аспектов.
	public static function select_by_search($search, $range_query=null) // возвращает чистый селектор, не заполненный значениями.
	{
		if ($range_query===null) $range_query=static::default_search_range();
		$ticket=static::create_search_ticket($search, $range_query);
		if ($ticket instanceof Report_impossible) return $ticket;
		
		$select=Select_by_ticket::from_model(['id_group'=>get_called_class(), 'ticket'=>$ticket]);
		return $select;
	}
	
	public static function transform_search_ticket($search, $ticket)
	{
		$range_query=$ticket->make_query();
		return static::create_search_ticket($search, $range_query);
	}
	
	// возвращает билет с запросом, результатом работы которого являются строчки в базовой таблице, соответствующие найденным результатам.
	public static function create_search_ticket($search, $range_query)
	{
		static::init();
		$aspect_code=static::locate_name('title');
		if ($aspect_code===null) return new Report_impossible('no_search_params');
		
		$base_aspect_class=static::$base_aspects[$aspect_code];
		$base_aspect_class::init();
		
		$model=static::$data_model['title'];
		$table=$model['table']; // при инициализации должно быть уже заполнено.
		if (array_key_exists('field', $model)) $field=$model['field']; else $field='title';
	
		return static::search_text_from_range_ticket($range_query, $table, $field, $search);
	}
	
	public static function search_from_range_ticket($range_query, $table, $field, $search, $ticket_class='Request_search')
	{
		static::init();
		if ($range_query instanceof Query) $range_query=clone $range_query;
		else $range_query=Query::from_array($range_query);
		$primary_table=$range_query->primary_table();
		if ($primary_table!==static::$default_table) die('UNIMPLEMENTED YET: searching in non-primary table range');
		
		$backlink_field='id';
		$link_field='id';
		if (is_array($table))
		{
			if (array_key_exists('backlink_field', $table)) $backlink_field=$table['backlink_field'];
			if (array_key_exists('link_field', $table)) $link_field=$table['link_field'];
			$table=$table[0];
		}
		
		if ($primary_table!==$table)
		{
			$conditions=[];
			$conditions[]=['field'=>$link_field, 'value_field'=>['search_table', $backlink_field]];
			if (Retriever()->is_common_table($table)) $conditions[]=['field'=>['search_table', 'id_group'], 'value'=>get_called_class()];
			
			$range_query->add_table($table, 'search_table', ...$conditions);
			$field=['search_table', $field];
		}
		$range_ticket=new RequestTicket('Request_single', [$range_query]);
		return new RequestTicket($ticket_class, [$range_ticket, $field, $search]);
	}
	
	public static function search_text_from_range_ticket($range_query, $table, $field, $search)
	{
		return static::search_from_range_ticket($range_query, $table, $field, $search, 'Request_search_text');
	}
	
	public static function default_search_range()
	{
		return
		[
			'action'=>'select',
			'table'=>static::$default_table // по идее совпадает с таблицей у базового аспекта.
		];
	}
	
	public static function common_task($task_code) // STUB! потом будет браться из параметров.
	{
	}
	
	public function cloned_from_pool($pool)
	{
		if (!$this->entity->pool->read_only())
		{
			$this->stored_responses=array_fill_keys(array_keys($this->stored_responses), []); 
			if ($pool->read_only())
			{
				foreach ($this->$aspect_dependancies as $aspect_code=>$value_codes)
				{
					unset($this->$aspect_dependancies[$aspect_code]);
					$this->apply_aspect_dependancies($aspect_code, $value_codes);
				}
			}
		}
		else
		{
			foreach ($this->stored_responses as $domain=>$responses)
			{
				foreach ($responses as $key=>$response)
				{
					if (is_object($response)) unset($this->stored_responses[$domain][$key]);
					// защищает от того, чтобы в запасах типизатора остались ссылки на значения, задачи и шаблоны из другого пула.
					// также это может быть отчёт, а в нём - такие неактуальные ссылки, так что лучше стереть и при необходимости вычислить заново.
				}
			}
		}
	}
	
	public function destroy()
	{
	}
}

class Request_entity_search extends Request_reuser
{
	use Request_reuser_capture_result;
	
	public
		$entity_type,
		$search;
		
	public function __construct($ticket, $entity_type, $search)
	{
		parent::__construct($ticket);
		$this->entity_type=$entity_type;
		$this->search=$search;
	}
	
	public function create_subrequest($ticket=null)
	{
		$entity_type=$this->entity_type;
		if ($ticket===null) $ticket=$this->ticket;
		$search_ticket=$entity_type::transform_search_ticket($this->search, $ticket);
		return parent::create_subrequest($search_ticket);
	}
	
	public function modify_query($query) { return $query; } // модификаций не требуется, мы уже сделали их на этапе создания запроса.
}

class EntityType_uptyped extends EntityType
{
	static
		$aspects=
		[
			'basic'=>'Entity_untyped_basic'
		];
}

// объект-посредник для получения данных о правах по форме {{<entity>.my_right.<right>|аргументы, если нужны}}. Следует использовать только в {|коде|}, поскольку отдаёт задачи, разрешающиеся в true или false (не показывамые).

abstract class SubHost implements Templater
{
	public
		$entity;

	public static function for_entity($entity)
	{
		$host=new static();
		$host->entity=$entity;
		return $host;
	}
	
	public abstract function template($code, $line=[]);
}

class RightHost extends SubHost
{
	public function source()
	{
		$type=$this->entity->type;
		return $type::$rights;
	}
	
	public function template($code, $line=[])
	{
		if (!array_key_exists($code, $this->source())) return;
		
		$task=$this->host_task($code, $line);
		if ($task instanceof Report_task) $task=$task->task;
		return $task;
	}
	
	public function host_task($code, $line=[])
	{
		if (!array_key_exists('user', $line)) return;
		$user=$this->entity->pool->entity_from_provider(['user_by_login', $line['user']], 'User');
		$more_args=$line;
		unset($more_args['user']);
		// FIX: следующий метод принимает аргументы по порядку, а задаются они по ключу! нужно принимать аргументы по ключу.
		return $this->entity->type->right_request($user, $code, ...$more_args);
	}
}

class MyRightHost extends RightHost
{
	public function host_task($code, $line=[])
	{
		$more_args=$line;
		return $this->entity->type->my_right_request($code, ...$more_args);
	}
}

class TaskHost extends SubHost
{
	public function template($code, $line=[])
	{
		$more_args=array_values($line);
		return $this->entity->task_request($code, ...$more_args);
	}
}
?>