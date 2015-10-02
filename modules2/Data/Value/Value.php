<?
namespace Pokeliga\Data;

/*
Абстрактный класс Value является основой для классов, обозначающих различные типы значений: Value_title, Value_entity, Value_int...

Все Value отвечают за следующий функционал:
- Приводит содержимое к заданному типу. Если только не стоит нестрогой настройки, содержимое Value_X всегда соответствует типу X.
- Хранит модель значения, используемую во время его заполнения, сохранения, проверки...
- Хранит состояние значения: заполнено, не заполнено, заполнение провалилось...
- Хранит действительность значения и предоставляет методы проверки этой действительности. Это касается действительности помимо простого соблюдеия типа: например, чтобы нельзя было установить мужской или женский пол у покемона, биологический вид которого бесполый. Различие приведения и действительности в том, что действительность проверять затратно и следует только при изменениях содержимого. полученные из БД данные считаются действительными, если не зафиксирован измнений в БД.
- Предоставляет методы заполнения (обычно обращается за ними к ValueSet'у, владельцу значения).
- Информирует зависимые значения об их устаревании, если содержимое изменилось.
- Выступает шаблонизатором, посредником и прочим представителем содержимого.
- Предоставляет поле ввода по умолчанию.
*/

load_debug_concern(__DIR__, 'Value');

class Value implements \Pokeliga\Template\Templater, ValueContent, ValueHost_combo, \Pokeliga\Entlink\Interface_proxy
{
	use \Pokeliga\Entlink\Caller_backreference, Logger_Value, ValueModel_owner;
	
	const
		STATE_UNFILLED	=1,
		STATE_FILLING	=2,
		STATE_FILLED	=3,
		STATE_FAILED	=4,
		
		// константы для указания того, что является источником изменения содержимого.
		BY_OPERATION	=1,	// это внесение новой информации по сравнению с хранилищем
		NEUTRAL_CHANGE	=2,	// это изменение само по себе не меняет данных по сравнению с хранилищем, если не было предварительных не-нейтральных изменений.
		BY_INPUT		=3,	// тоже как предыдущее, и запоминает, что данные получены из пользовательского ввода.
		
		// режим значения выводится из модели и определяет, как значение относися к изменениям, заполнению и проверкам.
		MODE_SET	=1, // режим хранимого значения.
		MODE_AUTO	=2, // режим процедурного значения.
		MODE_CONST	=3; // режим константы
		
	public
		$model,
		$mode=Value::MODE_SET,
		$master,
		$code,
		$type,
		$substitute_type,
		$content=null,
		$filler_task=null,
		
		$state=Value::STATE_UNFILLED,
		$last_source=null,
		
		$valid=null; // сведение о действительности. возможные значения: null - не проверялась. false - плохое. true - хорошее. объект класса Validator - идёт проверка.
	
	public static function standalone($model=null)
	{
		$value=new static();
		$value->setup_standalone($model);
		return $value;
	}
	
	public static function for_valueset($dataset, $code)
	{
		$model=$dataset->model_soft($code);
		if ($model instanceof \Report_impossible) return $model;
		$value=new static();
		$value->setup_for_valueset($dataset, $code, $model);
		return $value;
	}
	
	public static function from_content($content, $source=Value::BY_OPERATION)
	{
		if (($class=get_called_class())!=='Value')
		{
			preg_match('/Value_([^\\\\])$/', $class, $m);
			$model=['type'=>$m[1]];
			if ($content===null) $model['null']=true;
		}
		else $model=static::type_by_content($content);
		
		$value=static::standalone($model);
		$value->set($content, $source);
		return $value;
	}
	
	public static function type_by_content($content)
	{
		if ($content===null) return 'null';
		elseif (is_int($content))return 'int';
		elseif (is_numeric($content)) return 'number';
		elseif (is_string($content)) return 'string';
		elseif (is_bool($content)) return 'bool';
		elseif (is_array($content)) return 'array';
		elseif ($content instanceof \Pokeliga\Entity\Entity) return 'entity';
		elseif (is_object($content)) return 'object';
		else die ('UNKNOWN VALUE CONTENT');
	}
	
	public function setup_standalone($model)
	{
		$this->apply_model($model);
	}
	
	public function setup_for_valueset($master, $code, $model=null)
	{
		if ($model===null) $model=$master->model($code);
		$this->code=$code;
		$this->master=$master;
		$this->apply_model($model);
	}
	
	public function apply_model($model)
	{
		if (is_string($model)) $model=['type'=>$model];
		$this->model=$model;
		$this->auto_mode();
		$this->check_type();
	}
	
	public function check_type()
	{
		if ($this->type===null) return;
		$type_code=$this->core_type_keyword();
		$type_class=ValueType::get_shorthand_class($type_code);
		if (!($this->type instanceof $type_class))
		{
			$this->type->dispose();
			$this->type=null;
			if ($this->substitute_type!==null)
			{
				$this->substitute_type->dispose();
				$this->substitute_type=null;
			}
		}
	}
	
	public function auto_mode()
	{
		if ($this->core_type() instanceof ValueType_handles_fill) $this->mode=$this->core_type()->detect_mode();
		else $this->mode=static::detect_mode($this->model);
	}
	
	// необходимо для определения того, отличается ли подход к значению при изменении типа или аспекта.
	public static function detect_mode($model)
	{
		if (array_key_exists('auto', $model)) return static::MODE_AUTO;
		elseif (array_key_exists('const', $model)) return static::MODE_CONST;
		return static::MODE_SET;	
	}
	
	public function core_type()
	{
		if ($this->type===null)
		{
			$type_code=$this->core_type_keyword();
			$this->type=ValueType::for_value($this, $type_code);
		}
		return $this->type;
	}
	
	public function acting_type()
	{
		if ($this->substitute_type!==null) return $this->substitute_type;
		return $this->core_type();
	}
	
	public function create_substitute_type($content, $type_code=null)
	{
		if ($type_code===null) $type_code=static::type_by_content($this->content);
		return ValueType::for_value($this, $type_code);
	}
	
	public function implements_interface($interface_name)
	{
		return $this->acting_type() instanceof $interface_name; // можно было бы сделать через строки, но если что-то интересуется интерфейсами типа, наверняка сейчас будет обращаться с типу.
	}
	
	protected function core_type_keyword()
	{
		$type_code=$this->value_model_now('type');
		if ($type_code instanceof \Report_impossible) die('NO VALUE TYPE');
		return $type_code;
	}
	
	// STUB: этот, как и многие другие методы, пока не рассчитан на случай, когда значение существует в отрыве от набора. но когда такие случаи будут применяться, пока не ясно.
	public function pool()
	{
		if (!empty($this->master)) return $this->master->pool();
		return \Pokeliga\Entity\EntityPool::default_pool();
	}
	
	public function __construct()
	{
		// предотващает вызов метода value в качестве конструктора.
	}
	
	// это должен быть единственный способ, как кто-либо меняет содержимое значения! включая само значение.
	// FIXME: однако этот метод не проверяет свойства пула, в котором находится, если является частью сущности. Хотя в сущности и датасете поставлены такие проверки, однако некоторые задачи могут обращаться напрямую сюда. Нужно либо чтобы они обращались через датасет; или создавались только в случае пула, допускающего редактирование.
	// FIXME: кроме того, если значение находится в режиме auto, его содержимое может быть заменено отнюдь не только задачей, призванной его заполнять. это потому что источник изменения не представляется точнее, чем флагом $source, чтобы не переусложять вызов. нужно следить, чтобы посторонние операции не присваивали абсурдные значения.
	public function set($content, $source=Value::BY_OPERATION)
	{
		if ($content instanceof \Report) { vdump($content); die ('CANT SET TO REPORT'); }
		
		// важно, что это записывается до выхода из метода. даже если значение не изменилось или стало плохим, нужно отметить, что пришла инструкция от такого-то источника, ведь это и есть последний источник.
		$this->last_source=$source;
		$this->valid=null;
		
		$validity=null;
		$compliant=null;
		$content=$this->normalize($content, $validity, $compliant); // возвращает обработанное значение или \Report_impossible
		if ($content instanceof \Report_impossible)
		{
			$this->set_failed($source);
			return;
		}

		if ( ($this->is_filled()) && ($this->content===$content) ) return; // ничего не поменялось, ничего менять не надо.
		if ( (is_object($this->valid)) && (! ($this->valid instanceof Validator_delay) ) ) { vdump($content); vdump($this); debug_dump(); die ('SETTING WHILE VALIDATING'); } // отложенная валидация заменяет значение $this->valid на актуальный валидатор, когда значение получено.
		
		$result=$this->before_set($content, $source);
		if ($result instanceof \Report_impossible)
		{
			$this->set_failed($source);
			return;
		}
		
		// если мы дошли до этой точки, содержимое точно поменяется (не только состояние).
		if ($this->valid===null) $this->valid=$validity; // valid могла быть изменена вызовом before_set().
		$this->set_state(static::STATE_FILLED);
		
		if ($compliant and $this->substitute_type!==null)
		{
			$this->substitute_type->dispose();
			$this->substitute_type=null;
		}
		elseif (!$compliant)
		{
			$type_code=static::type_by_content($content);
			if ($this->substitute_type!==null and !ValueType::is_shorthand($this->substitute_type, $type_code))
			{
				$this->substitute_type->dispose();
				$this->substitute_type=null;
			}
			if ($this->substitute_type===null) $this->substitute_type=$this->create_substitute_type($content, $type_code);
			$content=$this->substitute_type->to_good_content($content); // подменный тип должен быть выбран так, чтобы уж точно обрабатывать содержимое.
			if ($content instanceof \Report_impossible) die('BAD SUBSTITUTE CONTENT');
		}
		
		$this->content=$content;
		$this->content_changed($source);
	}
	
	protected function before_set($content, $source)
	{
		$this->make_calls('before_set', $content, $source); // STUB: в будущем может обрабатывать ответ от подписанных вызовов.
	}
	
	public function set_failed($source=Value::NEUTRAL_CHANGE)
	{
		$this->last_source=$source;
		$this->filler_task=null;
		$this->set_state(static::STATE_FAILED);
		$this->valid=false; // можно установить и null, при запросе всё равно будет переправлено на false.
	}
	
	public function state() { return $this->state; }
	public function set_state($state) { $this->state=$state; }
	public function has_state($state) { return $this->state===$state; }
	public function has_any_state(...$states) { return in_array($this->state, $states); }
	public function is_failed() { return $this->has_state(static::STATE_FAILED); } // проваленное значение?
	public function is_filled() { return $this->has_state(static::STATE_FILLED); } // значение заполнено?
	public function is_final() { return $this->has_any_state(static::STATE_FILLED, static::STATE_FILLED); }
	public function is_filling() { return $this->has_state(static::STATE_FILLING); } // значение заполняется?
	public function to_fill() { return $this->has_state(static::STATE_UNFILLED); } // значение требует действий для заполнения?
	
	public function set_filler($filler)
	{
		if ($this->is_filled()) return;
		if ( ($this->is_filling()) && ($this->filler_task===$filler) ) return;
		if (!$this->to_fill()) { vdump($this); die('BAD SETTING FILLER'); }
		$this->filler_task=$filler;
		$this->set_state(Value::STATE_FILLING);
	}
	
	public function content_changed($source=Value::BY_OPERATION)
	{
		$this->valid_content_task=null;
		if ($this->type!==null) $this->type->content_changed($source);
		if ($this->substitute_type!==null) $this->substitute_type->content_changed($source);
		$this->make_calls('set', $this->content, $source);
	}
	
	public function default_correction_mode()
	{
		if (!empty($this->master)) $this->master->correction_mode;
		return ValueSet::VALUES_SOFT;
	}
	
	// только базовые проверки. данная функция сначала проверяет дополнтельные условия модели помимо типа.
	public function normalize($content, &$validity=null, &$compliant=null, $correction_mode=null)
	{
		$compliant=false;
		if ($content instanceof \Report_impossible) return $content;
		
		if ($correction_mode===null) $correction_mode=$this->default_correction_mode();
		$content_ready=false; // когда эта переменная становится "да", то содержимое переменной $content готово к отправке (нормализовано).
		
		if ($correction_mode===ValueSet::VALUES_ANY) $content_ready=true; // если у набора данных не включена строгость, то в значения может попасть любое содержимое! действительность при этом неизвестна.
		elseif ($this->mode===static::MODE_CONST)
		{
			$content_ready=true;
			$content=$this->value_model_now('const'); // действительность неизвестна. вдруг это значение, которое константно и недействительно?
		}
		
		if (!$content_ready)
		{
			if ( ($this->in_value_model('replace')) && ((is_int($content))||(is_string($content))) && (array_key_exists($content, $replacements=$this->value_model_now('replace'))) ) $content=$replacements[$content];
			if ( ($content===null) && ($this->in_value_model('null')) && ($this->value_model_now('null')===true) ) $content_ready=true;
			elseif ( ($this->in_value_model('normal_content')) && (in_array($content, $this->value_model_now('normal_content'), true)) ) $content_ready=true;
		}
		
		$type=$this->core_type();
		if ($type instanceof ValueType_auto)
		{
			$compliant=false; // FIXME! тут должно быть какое-то другое разрешение с подбором заместительного типа.
			return $content;
		}
		$result=$type->to_good_content($content);
		$compliant = $content===$result;
		if ($content_ready) return $content;
		
		if ($result instanceof \Report_impossible) return $result;
		
		// $result - исправленное значение, всегда возвращает верное значение для данного типа, даже если приходится выдумывать его из головы.
		// $content - значение, которое предлагается присвоить.
		
		if ( ($correction_mode===ValueSet::VALUES_STRICT) && ($result!==$content) ) return new \Report_impossible('strict_content', $this);
		// значение, которое пытаются присвоить, потребовало исправления - в этом режиме это недопустимо.
		
		elseif ( ($correction_mode===ValueSet::VALUES_MID) && ($result!=$content) ) return new \Report_impossible('mid_strict_content', $this);
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
		$compliant=true;
		return $result;
	}
	
	public function reset()
	{
		if ($this->has_state(static::STATE_UNFILLED)) return;
		if ($this->mode===static::MODE_CONST) return; // содержание константного данного меняется только вместе с моделью, нет выгоды в сбрасывании.
		$this->set_state(static::STATE_UNFILLED);
		$this->filler_task=null;
		$this->content=null;
		if ($this->substitute_type!==null)
		{
			$this->substitute_type->dispose();
			$this->substitute_type=null;
		}
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
		if ($this->is_filled()) return $this->content;
		return new \Report_impossible('unfilled_value', $this);
	}
	
	public function strict_content()
	{
		return $this->normalize($this->content(), null, null, ValueSet::VALUES_STRICT);
	}
	
	// STUB: вообще для этого уровня коррекции требуется лучшее название, чем "MID". Что-то другое должно быть между STRICT и SOFT, чтобы по названиям было сразу ясно, о чём речь.
	public function mid_content()
	{
		return $this->normalize($this->content(), null, null, ValueSet::VALUES_MID);
	}
	
	public $valid_content_task;
	public function valid_content($now=true)
	{
		$valid=$this->is_valid($now);
		if ($valid===true) return $this->content();
		if ($valid instanceof \Report_task)
		{
			if ($this->valid_content_task===null) $this->valid_content_task=\Pokeliga\Task\Task_delayed_call::with_call([$this, 'valid_content'], $valid->task); // чтобы не повторяться. важно сбрасывать это при изменении содержимого!
			if ($now)
			{
				$this->valid_content_task->complete();
				if ($this->valid_content_task->successful()) return $this->content();
				// если задача не справилась, то метод доработает до конца.
			}
			else return new \Report_task($this->valid_content_task, $this);
		}
		return new \Report_impossible('invalid_content', $this);
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
	
	// задача этого запроса - немедленно возвратить значение. Если для этого требуется завершить процесс (задачу), то она немедленно завершается.	
	// возможность аргумента $code нужна для соответствия интерфейсу ValueHost, собственно для обращений типа @current_player.owned_pokemon.count
	public function value($code=null)
	{
		$this->log('value');
		if ($code!==null) return $this->relate_by_interface('\Pokeliga\Data\ValueHost', 'value', $code);
		if ($this->is_filled()) return $this->content();
		elseif ($this->to_fill())
		{
			$this->fill();
			return $this->value(); // после операции выше статус должен измениться на FILLED , FILLING или FAILED.
		}
		elseif ($this->is_filling())
		{
			$this->filler_task->complete();
			return $this->value();  // после операции выше статус должен измениться на FILLED или FAILED.
		}		
		elseif ($this->is_failed())
		{
			return new \Report_impossible('value_failed', $this);
		}
		die ('UNKNOWN STATE 2');
	}
	
	public function request($code=null)
	{
		if ($code!==null) return $this->relate_by_interface('\Pokeliga\Data\ValueHost', 'request', $code);
		if ($this->is_filled()) return new \Report_resolution($this->content(), $this);
		elseif ($this->to_fill())
		{
			$this->fill();
			return $this->request(); // после операции выше статус должен измениться на FILLED , FILLING или FAILED.
		}
		elseif ($this->is_filling())
		{
			return new \Report_task($this->filler_task, $this);
		}		
		elseif ($this->is_failed())
		{
			return new \Report_impossible('value_failed_requested', $this);
		}
		die ('UNKNOWN STATE 3');
	}
	
	public function relate_by_interface($interface, $method, ...$args)
	{
		$type=$this->acting_type();
		if ($type instanceof $interface) return $type->$method(...$args);
		vdump($interface, $method, $this);
		return new \Report_impossible('no_interface', $this);
	}
	
	// этот метод осуществляет действия, необходимые для того, чтобы перевести значение из UNFILLED или IRRELEVANT в FILLING (или FILLED, если удаётся сразу, или IMPOSSIBLE, если сразу ясна невозможность).
	// Важно! Даже если у значения режим "автоматика", оно не заполняет само себя автоматикой. Например, хранимые в БД поля сущности, даже автоматические, стараются получить значения из БД, если весь комплекс не устарел. а значить об таких тонкостях, как правило, должен ValueSet.
	public function fill()
	{
		if ($this->mode===static::MODE_CONST) $this->set($this->value_model_now('const'), static::NEUTRAL_CHANGE);
		// заполнение константы не нарушает картины: либо константа та же, что была в БД; либо модель изменился в результате того, что картина уже нарушена.
		else
		{
			$type=$this->core_type();
			if ($type instanceof ValueType_handles_fill) $type->fill();
			else $this->master->fill_value($this);
		}
	}
	
	public function get_generator_task()
	{
		if ($this->in_value_model('auto'))
		{
			$filler_class=$this->value_model_now('auto');
			if (string_instanceof($filler_class, 'Pokeliga\Data\Filler')) $filler=$filler_class::for_value($this);
			else $filler=$filler_class::filler_for_value($this);
			return $filler;
		}
	}
	
	public function get_validator_tasks()
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
		$result=[];
		$by_type=$this->acting_type()->list_validators();
		if (!empty($by_type)) $result=array_merge($result, $by_type);
		if ($this->in_value_model('validators')) $result=array_merge($result, $this->value_model_now('validators'));
		return $result;
	}
	
	// возвращает true, false или \Report_tasks
	public function is_valid($now=true)
	{
		$result=$this->estimate_validity();
		if ($result===null) $validity=Validator::for_value('delay', $this);
		
		if ($result instanceof \Pokeliga\Task\Task)
		{
			if ($now and !$result->completed()) $result->complete();
			if ($result->completed()) $result=$result->successful();
		}
		
		$this->valid=$result;
		if ($result instanceof \Pokeliga\Task\Task) return new \Report_promise($result, $this);
		return $result;
	}
	
	// возвращает либо null - с нахрапу оценить действительность нельзя; или значение, которое можно присвоить $this->valid. никогда не возвращает Validator_delay!
	public function estimate_validity()
	{
		if ($this->is_failed()) return false;
		elseif (is_object($this->valid)) return $this->valid;
		elseif ($this->is_filled())
		{
			if (is_bool($this->valid)) return $this->valid;
			elseif ( ($this->mode===static::MODE_CONST) && ($this->content===$this->value_model_now('const')) ) return true;
			elseif ( ($this->in_value_model('auto_valid')) && (in_array($this->content, $this->value_model_now('auto_valid'), true)) ) return true;
			elseif ( ($this->in_value_model('auto_invalid')) && (in_array($this->content, $this->value_model_now('auto_invalid'), true)) ) return false;
			elseif ($this->valid===null)
			{
				$validators=$this->get_validator_tasks();
				if (empty($validators)) return true;
				return ValidationPlan_single_value::from_validators($validators);
			}
			else die ('BAD VALIDATION STATE 1');
		}
		// в противном случае оценить действительность не получается.
	}
	
	public function change_model($new_model)
	{
		if ($this->model==$new_model) return false;
		$this->valid=null;
		$this->apply_model($new_model);
		return true;
	}
	
	public function template($name, $line=[])
	{
		if ($this->is_filled()) return $this->template_for_filled($name, $line);
		elseif ($this->is_failed()) return $this->template_for_failed($name, $line);
		else
		{
			$result=$this->acting_type()->template_pre_filled($name, $line);
			if ($result!==null) return $result;
			
			$report=$this->request();
			if ($report instanceof \Report_impossible) return $report;
			if ($report instanceof \Report_success) return $this->template($name, $line);
			if ($report instanceof \Report_tasks)
			{
				$task=new \Pokeliga\Task\Task_delayed_call
				(
					new Call([$this, 'template'], $name, $line),
					$report
				);
				return new \Report_task($task, $this);
			}
		}
	}
	
	// следует вызывать только когда значение заполнено!
	public function default_template($line=[])
	{
		$this->acting_type()->default_template($line);
	}
	
	// создаёт шаблон по умолчанию для значения, которое уже заполнено.
	public function template_for_filled($name, $line=[])
	{
		if ( ($name===null) || ($name===static::DEFAULT_TEMPLATE_CODE) ) return $this->default_template($line);
		return $this->acting_type()->template($name, $line);
	}
	
	public function generic_template($name, $line=[])
	{
		return 'UNKNOWN TEMPLATE: '.$name;
	}
	
	public function template_for_failed($name, $line=[])
	{
		if (!empty($result=$this->acting_type()->template_for_failed($name, $line))) return $result;
		return $this->default_template_for_failed($name, $line);
	}
	
	// STUB
	public function default_template_for_failed($name, $line=[])
	{
		return 'FAILED TEMPLATE: '.$this->code.'.'.$name;
	}
	
	public function __call($method, $args)
	{
		return $this->acting_type()->$method(...$args);
	}
}

?>