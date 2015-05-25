<?
// В этой новой модели объекты Value отвечают за тип содержимого (приведение с стандарту, валидаторы) и хранение сведений о нём (заполнено, не заполнено, с чем связано), в то время как ValueSet отвечает за способ получения содержимого.

load_debug_concern('Data', 'Value');

abstract class Value implements Templater, ValueHost, ValueContent
{
	use Prototyper, Caller_backreference, Report_spawner, Logger_Value, ValueModel_owner, ValueHost_standard;
	
	const
		STATE_UNFILLED=1,
		STATE_FILLING=2,
		STATE_FILLED=3,
		STATE_FAILED=4,
		STATE_IRRELEVANT=5, // когда значение задано, а затем изменено, то если у значения есть зависимые значения - то они теряют актуальность, и далее по цепочке. неактуальные значения вычисляются заново при обращении к ним.
		
		// константы для указания того, что является источником изменения содержимого. FIX: сейчас эти константы уже предполагают знание о системе сущностей.
		BY_OPERATION=0,		// это внесение новой информации по сравнению с хранилищем
		NEUTRAL_CHANGE=1,	// это изменение само по себе не меняет данных по сравнению с хранилищем, если не было предварительных не-нейтральных изменений.
		BY_KEEPER=2, 		// как предыдущее, но требует обработки from_db.
		BY_INPUT=3,			// тоже как предыдущее, но требует обработки from_input?
		
		MODE_SET=1,
		MODE_AUTO=2,
		MODE_CONST=3,
		
		DEFAULT_TEMPLATE_CODE='default',
		UNFILLED_TEMPLATE_CLASS='Template_value_delay',
		DEFAULT_TEMPLATE_CLASS=null,
		DEFAULT_TEMPLATE_FORMAT_KEY='format';
	
	static
		$prototype_class_base='Value_';
		
	public
		$model,
		$mode=Value::MODE_SET,
		$master,
		$code,
		$content=null,
		$filler_task=null,
		
		$state=Value::STATE_UNFILLED,
		$last_source=null,
		
		$valid=null; // сведение о действительности. возможные значения: null - не проверялась. false - плохое. true - хорошее. объект класса Validator - идёт проверка.
	
	public static function from_model($model)
	{
		$type=$model['type'];
		$value=static::from_prototype($type);
		return $value;
	}
	
	public static function standalone($model=null)
	{
		if (is_string($model)) $model=['type'=>$model];
		elseif ( ($model===null) && (($class=get_called_class())!=='Value') ) $model=['type'=>substr($class, 6)];
		elseif ($model===null) die('NO VALUE TYPE');
		$value=static::from_model($model);
		$value->setup_standalone($model);
		return $value;
	}
	
	public static function for_valueset($dataset, $code)
	{
		$model=$dataset->model($code);
		$value=static::from_model($model);
		$value->setup_for_valueset($dataset, $code, $model);
		return $value;
	}
	
	public static function from_content($content, $source=Value::BY_OPERATION)
	{
		if (($class=get_called_class())!=='Value')
		{
			$model=['type'=>substr($class, 6)];
			if ($content===null) $model['null']=true;
		}
		elseif ($content===null) $model=['type'=>'number', 'null'=>true];
		elseif (is_int($content)) $model=['type'=>'int'];
		elseif (is_numeric($content)) $model=['type'=>'number'];
		elseif (is_string($content)) $model=['type'=>'string'];
		elseif (is_bool($content)) $model=['type'=>'bool'];
		elseif (is_array($content)) $model=['type'=>'array'];
		elseif ($content instanceof Entity) $model=['type'=>'entity'];
		else die ('BAD VALUE CONTENT');
		
		$value=static::standalone($model);
		$value->set($content, $source);
		return $value;
	}
	
	public function setup_standalone($model)
	{
		$this->model=$model;
		$this->auto_mode();
	}
	
	public function setup_for_valueset($master, $code, $model=null)
	{
		if ($model===null) $model=$master->model($code);
		$this->model=$model;
		$this->code=$code;
		$this->master=$master;
		$this->auto_mode();
	}
	
	public function auto_mode()
	{
		$this->mode=static::detect_mode($this->model);
	}
	
	// необходимо для определения того, отличается ли подход к значению при изменении типа или аспекта.
	public static function detect_mode($model)
	{
		if (array_key_exists('auto', $model)) return static::MODE_AUTO;
		elseif (array_key_exists('const', $model)) return static::MODE_CONST;
		return static::MODE_SET;	
	}
	
	// STUB: этот, как и многие другие методы, пока не рассчитан на случай, когда значение существует в отрыве от набора. но когда такие случаи будут применяться, пока не ясно.
	public function pool()
	{
		if (!empty($this->master)) return $this->master->pool();
		return EntityPool::default_pool();
	}
	
	public function __construct()
	{
		// предотващает вызов метода value в качестве конструктора.
	}
	
	// это должен быть единственный способ, как кто-либо меняет содержимое значения! включая само значение.
	// FIX: однако этот метод не проверяет свойства пула, в котором находится, если является частью сущности. Хотя в сущности и датасете поставлены такие проверки, однако некоторые задачи могут обращаться напрямую сюда. Нужно либо чтобы они обращались через датасет; или создавались только в случае пула, допускающего редактирование.
	// FIX: кроме того, если значение находится в режиме auto, его содержимое может быть заменено отнюдь не только задачей, призванной его заполнять. это потому что источник изменения не представляется точнее, чем флагом $source, чтобы не переусложять вызов. нужно следить, чтобы посторонние операции не присваивали абсурдные значения.
	public function set($content, $source=Value::BY_OPERATION)
	{
		if ($content instanceof Report) { vdump($content); die ('CANT SET TO REPORT'); }
	
		if ($source===static::BY_KEEPER) $content=$this->from_db($content);
		elseif ($source===static::BY_INPUT) $content=$this->from_input($content);
		
		// важно, что это записывается до выхода из метода. даже если значение ни изменилось или стало плохим, нужно отметить, что пришла инструкция от такого-то источника, ведь это и есть последний источник.
		$this->last_source=$source;
		
		$validity=null;
		$content=$this->normalize($content, $validity); // возвращает обработанное значение или Report_impossible
		if ($content instanceof Report_impossible)
		{
			$this->set_state(static::STATE_FAILED);
			$this->valid=false; // можно установить и null, при запросе всё равно будет переправлено на false.
			return;
		}

		if ( ($this->has_state(static::STATE_FILLED)) && ($this->content===$content) ) return; // ничего не поменялось, ничего менять не надо.
		if ( (is_object($this->valid)) && (! ($this->valid instanceof Validator_delay) ) ) { vdump($content); vdump($this); debug_dump(); die ('SETTING WHILE VALIDATING'); } // отложенная валидация заменяет значение $this->valid на актуальный валидатор, когда значение получено.
		$this->valid=$validity;
		
		// важно, что этот вызов происходит после того, как мы убедились, что значения отличаются. значит, в случае ввода значения BY_INPUT, если оно не отличается от введённого BY_KEEPER, параметр $save_changes не будет изменён на true, ведь мы до этого шага даже не дошли.
		$this->make_calls('before_set', $content, $source); // STUB: в будущем может обрабатывать ответ от подписанных вызовов.
		
		if ( (empty($this->master)) || (!$this->master->changed_from_db) /* ||  ???  ($source===static::BY_KEEPER) */ ) $this->valid=true;
		// данные свободных значений (уж точно не являющихся частью сущности) или набора, не имеющего изменений по сравнению с БД (потому что данные не менялись или же потому что они вообще не из БД и не для БД), заведомо правильные при условии, что они нормализованы. значение changed_from_db меняется в вызовах before_set заинтересованными объектами. причём эта переменная у всего пула и его сущностей общая.
		else // эта ветка отвечает за случай, когда данные изменились по сравнению с БД. потому что если нет, то зависимые значения не требуют пересчёта. может, мы просто получили и присвоили значение из БД или же получили авто-значение на основе данных из БД.
		{
			$this->dependants_are_irrelevant();
		}
		// в противном случае содержимое считается изменившимся. константа в изменённой среде всё равно делает зависимые значения недействительными, даже если она не нуждается в проверке. может, мы только что заменили модель значения и теперь к нему применима другая константа.
		
		$this->content=$content;
		$this->set_state(static::STATE_FILLED);
		$this->content_changed($source);
	}
	
	public function set_state($state)
	{
		$this->state=$state;
	}
	
	public function has_state($state)
	{
		return $this->state===$state;
	}
	
	public function state()
	{
		return $this->state;
	}
	
	public function set_filler($filler)
	{
		if ($this->has_state(Value::STATE_FILLED)) return;
		if ( ($this->has_state(Value::STATE_FILLING)) && ($this->filler_task===$filler) ) return;
		if (!$this->has_state(Value::STATE_UNFILLED)) { vdump($this); die('BAD SETTING FILLER'); }
		$this->filler_task=$filler;
		$this->set_state(Value::STATE_FILLING);
	}
	
	public function content_changed($source=Value::BY_OPERATION)
	{
		$this->valid_content_task=null;
		$this->make_calls('set', $this->content, $source);
	}
	
	public function from_db($content)
	{
		return $content;
	}
	
	public function from_input($content)
	{
		return $this->from_db($content); // по умолчанию конверсия между вводом из БД и пользовательским не отличается.
	}
	
	public function for_db()
	{
		return $this->content();
	}
	
	public function for_input() // для заполнения полей форм
	{
		return $this->for_db();
	}
	
	public function default_correction_mode()
	{
		if (!empty($this->master)) $this->master->correction_mode;
		return ValueSet::VALUES_SOFT;
	}
	
	// только базовые проверки. данная функция сначала проверяет дополнтельные условия модели помимо типа.
	// FIX: в принципе этот метод может использоваться и для проверки потенциальных значений, но также редактирует параметр $valid. Это может быть выполнено лучше.
	public function normalize($content, &$validity=null, $correction_mode=null)
	{
		if ($content instanceof Report_impossible) return $content;
		if ($correction_mode===null) $correction_mode=$this->default_correction_mode();
		
		if ($correction_mode===ValueSet::VALUES_ANY) return $content; // если у набора данных не включена строгость, то в значения может попасть любое содержимое!
		
		if ( ($this->in_value_model('replace')) && ((is_int($content))||(is_string($content))) && (array_key_exists($content, $replacements=$this->value_model_now('replace'))) ) $content=$replacements[$content];
		if ( ($content===null) && ($this->in_value_model('null')) && ($this->value_model_now('null')) ) return $content;
		if ( ($this->in_value_model('normal_content')) && (in_array($content, $this->value_model_now('normal_content'), true)) ) return $content;
		// этим следует пользоваться с осторожностью, поскольку методы from_db и for_db рассчитаны только на свойственный классу тип.
		
		if ($this->mode===static::MODE_CONST) return $this->value_model_now('const');
		
		if ( ($this instanceof Value_number) && (!($this instanceof Value_int)) ) $content=str_replace(',', '.', $content);
		$result=$this->legal_value($content);
		if ($result instanceof Report_impossible) return $result;
		
		// $result - исправленное значение, всегда возвращает верное значение для данного типа, даже если приходится выдумывать его из головы.
		// $content - значение, которое предлагается присвоить.
		
		if ( ($correction_mode===ValueSet::VALUES_STRICT) && ($result!==$content) ) return $this->sign_report(new Report_impossible('strict_content'));
		// значение, которое пытаются присвоить, потребовало исправления - в этом режиме это недопустимо.
		
		elseif ( ($correction_mode===ValueSet::VALUES_MID) && ($result!=$content) ) return $this->sign_report(new Report_impossible('mid_strict_content'));
		// если значение, которое пытаются присвоить, потребовало существенных изменений (например, 'random' стало 'default'), то так нельзя. а если '5' превратилось в 5, то можно. записывается исправленное значение.
		
		elseif ( ($correction_mode===ValueSet::VALUES_NORMALIZE) && ($result!=$content) ) return $content;
		// в этом режиме если были внесены существенные исправления ('random' в 'default'), то сохраняется исходное, не исправленное значение, но '5' при необходимости в 5.
		
		elseif ($correction_mode===ValueSet::VALUES_ANY_STRICT_VALIDITY)
		// сохраняет исходное значение, если оно не совпадает строго с исправленным, но записывает, что значение не действительно.
		{
			if ($result!==$content) $validity=false;
			return $content;
		}
		elseif ($correction_mode===ValueSet::VALUES_ANY_MID_VALIDITY)
		{
			// сохраняет исходное значение, если для него нужны существенные исправления (сохраняет 'random'), но записывает, что значение не действительно.
			if ($result!=$content) $validity=false;
			return $content;
		}
		
		// в противном сохраняется исправленное значение.
		return $result;
	}
	
	// эта функция строго конвертирует данные в свойственный классу тип. из этого метода не может вернуться значение, не соответствующее типу!
	abstract public function legal_value($content);
	
	// FIX: поскольку состояние становится UNFILLED, а не IRRELEVANT, то значение постарается получить данные из БД, если может. это относится прежде всего к авто-значениям, которые также кэшируются в БД. они не будут знать, что их окружающая среда уже изменилась и данные в БД могут больше не подходить. Сейчас, однако, эта функция используется безопасно: 1) в формах, которые должны забыть, что они уже отрабатывали, в частности, для сохранения в сессию - формы не имеют механизма зависимостей, а получают данные разово; 2) при клонировании сущности для значений, имеющих в качестве содержимого сущность, а значит, не хранимых (хранится обычно айди);
	public function reset()
	{
		$this->set_state(static::STATE_UNFILLED);
		$this->filler_task=null;
		$this->content=null;
		$this->last_source=null;
		$this->content_erased();
	}
	
	public function content_erased()
	{
		$this->content_changed(Value::NEUTRAL_CHANGE);
	}
	
	// к содержимому напрямую могут обращаться только методы, производящие техническое обслуживание значения. методы, которым требуется знать содержимое, чтобы использовать и анализировать его, должны обращаться к этому методу.
	public function content()
	{
		if ($this->has_state(Value::STATE_FILLED)) return $this->content;
		return $this->sign_report(new Report_impossible('unfilled_value'));
	}
	
	public function strict_content()
	{
		return $this->normalize($this->content(), ValueSet::VALUES_STRICT);
	}
	
	// STUB: вообще для этого уровня коррекции требуется лучшее название, чем "MID". Что-то другое должно быть между STRICT и SOFT, чтобы по названиям было сразу ясно, о чём речь.
	public function mid_content()
	{
		return $this->normalize($this->content(), ValueSet::VALUES_MID);
	}
	
	public $valid_content_task;
	public function valid_content($now=true)
	{
		$valid=$this->is_valid($now);
		if ($valid===true) return $this->content();
		if ($valid instanceof Report_task)
		{
			if ($this->valid_content_task===null) $this->valid_content_task=Task_delayed_call::with_call([$this, 'valid_content'], $valid->task); // чтобы не повторяться. важно сбрасывать это при изменении содержимого!
			if ($now)
			{
				$this->valid_content_task->complete();
				if ($this->valid_content_task->successful()) return $this->content();
				// если задача не справилась, то она дойдёт до конца.
			}
			else return $this->sign_report(new Report_task($this->valid_content_task));
		}
		return $this->sign_report(new Report_impossible('invalid_content'));
	}
	public function valid_content_request()
	{
		return $this->valid_content(false);
	}
	
	public function is_content_nondefault()
	{
		if (!$this->in_value_model('default')) return false;
		if ($this->content()!==$this->value_model_now('default')) return false;
		return true;
	}
	
	public function dependants_are_irrelevant()
	{
		if ( (!$this->in_value_model('dependants')) || ( count($dependants=$this->value_model_now('dependants'))==0) ) return;
		
		foreach ($dependants as $value_code)
		{
			$value=$this->master->produce_value($value_code);
			// в этом случае создаётся значение, которое, может, и не пригодится. но иначе никак не запомнить, что если когда таковое создастся, то данные в нём уже не следует получать кипером, а вычислять заново.
			$value->irrelevant_by($this);
		}	
	}
	
	public function irrelevant_by($by)
	{
		if ($this->has_state(static::STATE_IRRELEVANT)) return; // если значение устарело, то эта операция уже проводилась.
		
		if (!$this->has_state(static::STATE_FILLING))
		{
			if ($this->mode!==Value::MODE_SET) $this->set_state(static::STATE_IRRELEVANT);
			// OPTIM: сейчас если задаваемое значение несколько раз получает сигнал "irrelevant", то она не помнит, что уже получила его, и повторяет всё это каждый раз.
			
			if (!is_object($this->valid)) $this->valid=null;
			// даже если значение константно, нужно перепроверить: а что если изменилась модель?
			
			$this->save_changes=true;
			// FIX: вообще значение пока не должно знать об этом параметре.
		}
		$this->dependants_are_irrelevant();
	}
	
	// задача этого запроса - немедленно возвратить значение. Если для этого требуется завершить процесс (задачу), то она немедленно завершается.	
	// возможность аргумента $code нужна для соответствия интерфейсу ValueHost, собственно для обращений типа @current_player.owned_pokemon.count.
	public function value($code=null)
	{
		if ($code!==null) return $this->ValueHost_value($code);
		$this->log('value');
		if ($this->has_state(static::STATE_FILLED)) return $this->content();
		elseif ( ($this->has_state(static::STATE_UNFILLED)) || ($this->has_state(static::STATE_IRRELEVANT)) )
		{
			$this->fill();
			return $this->value(); // после операции выше статус должен измениться на FILLED , FILLING или FAILED.
		}
		elseif ($this->has_state(static::STATE_FILLING))
		{
			$this->filler_task->complete();
			return $this->value();  // после операции выше статус должен измениться на FILLED или FAILED.
		}		
		elseif ($this->has_state(static::STATE_FAILED))
		{
			return $this->sign_report(new Report_impossible('value_failed'));
		}
		die ('UNKNOWN STATE 2');
	}
	
	public function request($code=null)
	{
		if ($code!==null) return $this->ValueHost_request($code);
		if ($this->has_state(static::STATE_FILLED)) return $this->sign_report(new Report_resolution($this->content()));
		elseif ( ($this->has_state(static::STATE_UNFILLED)) || ($this->has_state(static::STATE_IRRELEVANT)) )
		{
			$this->fill();
			return $this->request(); // после операции выше статус должен измениться на FILLED , FILLING или FAILED.
		}
		elseif ($this->has_state(static::STATE_FILLING))
		{
			return $this->sign_report(new Report_task($this->filler_task));
		}		
		elseif ($this->has_state(static::STATE_FAILED))
		{
			return $this->sign_report(new Report_impossible('value_failed_requested'));
		}
		die ('UNKNOWN STATE 3');
	}
	
	// этот метод осуществляет действия, необходимые для того, чтобы перевести значение из UNFILLED или IRRELEVANT в FILLING (или FILLED, если удаётся сразу, или IMPOSSIBLE, если сразу ясна невозможность).
	public function fill()
	{
		if ($this->mode===static::MODE_CONST) $this->set($this->value_model_now('const'), static::NEUTRAL_CHANGE);
		// заполнение константы не нарушает картины: либо константа та же, что была в БД; либо модель изменился в результате того, что картина уже нарушена.
		else
		{
			$this->master->fill_value($this);
		}
	}
	
	public function determine_generator()
	{
		if ($this->in_value_model('auto'))
		{
			$filler_class=$this->value_model_now('auto');
			if (string_instanceof($filler_class, 'Filler')) $filler=$filler_class::for_value($this);
			else $filler=$filler_class::filler_for_value($this);
			return $filler;
		}
	}
	
	public function keeper_code()
	{
		if ($this instanceof Value_unkept) return false;
		if ($this->in_value_model('keeper')) return $this->value_model_now('keeper');
		return $this->master->default_keeper; // STUB
	}
	
	public function determine_keeper()
	{
		$keeper_code=$this->keeper_code();
		if (empty($keeper_code)) return false;
		$keeper=Keeper::for_value($this, $keeper_code);
		return $keeper;
	}
	
	public function produce_validators()
	{
		$validator_keywords=$this->list_validators();
		if (empty($validator_keywords)) return;
		$validators=[];
		foreach ($validator_keywords as $data)
		{
			if (is_string($data))
			{
				$validator_code=$data;
				$model=null;
			}
			elseif (is_array($data))
			{
				$validator_code=array_shift($data);
				$model=array_merge($this->value_model(), $data);
			}
			$validators[]=Validator::for_value($validator_code, $this, $model);
		}
		return $validators;
	}
	
	public function list_validators()
	{
		if (!$this->in_value_model('validators')) return;
		return $this->value_model_now('validators');
	}
	
	// возвращает true, false или Report_tasks
	public function is_valid($now=true)
	{
		if ($this->has_state(static::STATE_FAILED)) $result=false;
		elseif (is_object($this->valid)) $result=$this->valid;
		elseif ($this->has_state(static::STATE_FILLED))
		{
			if (is_bool($this->valid)) return $this->valid;
			elseif ( ($this->mode===static::MODE_CONST) && ($this->content===$this->value_model_now('const')) ) $result=true;
			elseif ( ($this->in_value_model('auto_valid')) && (in_array($this->content, $this->value_model_now('auto_valid'), true)) ) $result=true;
			elseif ( ($this->in_value_model('auto_invalid')) && (in_array($this->content, $this->value_model_now('auto_invalid'), true)) ) $result=false;
			elseif ($this->valid===null)
			{
				$validators=$this->produce_validators();
				if (empty($validators)) $result=true;
				else $result=ValidationPlan_solo::from_validators($validators);
			}
			else die ('BAD VALIDATION STATE 1');
		}
		else	// FILLING, UNFILLED, IRRELEVANT
		{
			$result=Validator::for_value('delay', $this);
		}
		
		if ( ($result instanceof Task) && ($now) )
		{
			$result->complete();
			$report=$result->report();
			if ($report instanceof Report_impossible) $result=false;
			elseif ($report instanceof Report_success) $result=true;
			else { vdump($result); die ('BAD VALIDATION RESOLUTION'); }
		}
		
		$this->valid=$result;
		if ($result instanceof Task) return $this->sign_report(new Report_task($result));
		return $result;
	}
	
	public function change_model($new_model)
	{
		if ($this->model==$new_model) return;
		$this->model=$new_model;
		$this->valid=null;
		$this->auto_mode();
		$this->irrelevant_by($new_model);
	}
	
	// FIX? поведение отличается в зависимости от того, сразу ли значение заполнено (или провалено) или нет. В первом случае, если ответа на такой код шаблона не предусмотрено, вернётся null, что в общем-то позволяет продолжать поиск. Во втором случае вернётся шаблон Template_value_delay, который в итоге может тоже закончиться ответом null (приводящему к провалу задачи), но он уже будет стоять включён в старший шаблон. добавить заблаговременное распознавание кодов шаблонов не получится, потому что такие значения как Value_id не знают, какие шаблоны они смогут обеспечить, когда будут заполнены.
	public function template($name, $line=[])
	{
		if ($this->has_state(static::STATE_FILLED)) return $this->template_for_filled($name, $line);
		elseif ($this->has_state(static::STATE_FAILED)) return $this->template_for_failed($name, $line);
		else // UNFILLED, FILLING, IRRELEVANT
		{
			$class=static::UNFILLED_TEMPLATE_CLASS;
			$task=$class::for_value($this, $name, $line);
			return $task;
		}
	}
	
	public function default_template($line=[])
	{
		if (static::DEFAULT_TEMPLATE_CLASS!==null)
		{
			$class=static::DEFAULT_TEMPLATE_CLASS;
			$task=$class::for_value($this, $line);
			return $task;
		}
		
		if (array_key_exists(static::DEFAULT_TEMPLATE_FORMAT_KEY, $line)) $format=$line[static::DEFAULT_TEMPLATE_FORMAT_KEY];
		elseif ($this->in_value_model(static::DEFAULT_TEMPLATE_FORMAT_KEY)) $format=$this->value_model_now(static::DEFAULT_TEMPLATE_FORMAT_KEY);
		else $format=null;
		$result=$this->for_display($format, $line);
		return $result;
	}
	
	// создаёт шаблон по умолчанию для значения, которое уже заполнено.
	public function template_for_filled($name, $line=[])
	{
		if ( ($name===null) || ($name===static::DEFAULT_TEMPLATE_CODE) ) return $this->default_template($line);
	}
	
	// пусть по умолчанию пишет "missing template".
	public function template_for_failed($name, $line=[])
	{
	}
	
	public function for_display($format=null, $line=[])
	{
		return $this->content();
	}
}

?>