<?
abstract class ValueSet implements ValueHost, Pathway, Templater
{
	use Report_spawner;
	
	const
		VALUES_STRICT=1,// только действительные значения, не исправлять.
		VALUES_MID=2,	// только действительные значения, исправлять мягко ('5a' становится 5, но слишком длинное "мяумяу" не становится "мяу")
		VALUES_SOFT=3,	// только действительные значения, исправлять как угодно.
		VALUES_NORMALIZE=4, // любые значения, по возможности приводить к норме.
		VALUES_ANY=5,	// любые значения.
		VALUES_ANY_STRICT_VALIDITY=6, // любые значения, но если были внесены какие-либо изменения, то обозначается недействительность.
		VALUES_ANY_MID_VALIDITY=7; // любые значения, но если были внесены серьёзные изменения, то обозначается недействительность.
		
	public
		$master,
		$model,
		$default_table,
		$values=[],
		$subscribe_to_changes=true,
		$changed_from_db=true, // запоминает, изменились ли данные в наборе по сравнению с данными из БД. По умолчанию истина потому, что большая часть ValueSet'ов вообще не имеют отношения с БД - это необходимо для совместимости.
		$correction_mode=ValueSet::VALUES_SOFT;
	
	
	public static function from_model($model=null)
	{
		$set=new static();
		if ($model!==null) $set->model=$model;
		return $set;
	}
	
	// чтобы вызывать при наследовании.
	public function __construct()
	{
	}
	
	public function value($code)
	{
		$value=$this->produce_value($code);
		$result=$value->value();
		return $result; // может быть класса Report с описанием стадии процесса.
	}
	
	// всегда возвращает отчёт.
	public function request($value)
	{
		$value=$this->produce_value($value); // на случай, если аргумент - код, а не готовое значение.
		return $value->request();
	}
	
	public function produce_value($code, $soft=false)
	{
		if ($code instanceof Value) return $code; // чтобы вызывать эту функцию, не зная точно, не является ли аргумент уже полученным значением.
		
		$this->model_soft($code); // у некоторых наборов вызывает создание необходимой модели.
		if (!array_key_exists($code, $this->model))
		{
			if ($soft) return $this->sign_report(new Report_impossible('unknown_value_code'));
			xdebug_print_function_stack();
			vdump($this);
			die ('UNKNOWN VALUE CODE 2: '.$code);	
		}
		if (!array_key_exists($code, $this->values))
		{
			$this->values[$code]=$this->create_value($code);		
		}
		return $this->values[$code];
	}
	
	public function produce_value_soft($code)
	{
		return $this->produce_value($code, true);
	}
	
	public function reset()
	{
		foreach ($this->values as $value)
		{
			$value->reset();
		}
	}
	
	public function create_value($code)
	{
		$value=Value::for_valueset($this, $code);

		$this->setup_value($value);

		return $value;
	}
	
	public function setup_value($value)
	{
		if (!$this->subscribe_to_changes) return;
		$value->add_call
		(
			function($value, $content, $source) // предполагает черту Caller_backreference
			{
				$value->master->before_value_set($value, $content, $source);
				
				// обращение идёт таким образом, а не через $this, потому что при клонировании сущности в другой пул контекст этой функции не меняется, и если воспользоваться $this, то она укажен на другую сущность.
			},
			'before_set'
		);	
	}
	
	public function pool()
	{
		return EntityPool::default_pool();
	}
	
	public function before_value_set($value, $content, $source)
	{
		if ( (!$this->changed_from_db) && ($source===Value::BY_OPERATION) ) $this->changed_from_db=true;
	}
	
	public function model($code, $soft=false)
	{
		if (!array_key_exists($code, $this->model))
		{
			if ($soft) return $this->sign_report(new Report_impossible('no_model_code'));
			else { vdump($this->model); die ('UNKNOWN VALUE CODE 1: '.$code); }
		}
		return $this->model[$code];
	}
	
	public function model_soft($code)
	{
		return $this->model($code, true);
	}
	
	public function set_value($value_code, $content, $source_code=Value::BY_OPERATION, $rewrite=true)
	{
		$value=$this->produce_value($value_code);
		if ( (!$rewrite) && ($value->has_state(Value::STATE_FILLED)) ) return;
		$value->set($content, $source_code);
	}
	
	public function set_by_array($data, $source_code=Value::BY_OPERATION, $rewrite=true)
	{
		foreach ($data as $code=>$content)
		{
			if ($this->produce_value_soft($code) instanceof Report_impossible) continue;
			$this->set_value($code, $content, $source_code, $rewrite);
		}
	}
	
	public function content_of($value_code)
	{
		$value=$this->produce_value($value_code);
		return $value->content();
	}
	
	public function valid_content($value_code, $now=true)
	{
		$value=$this->produce_value($value_code);
		return $value->valid_content($now);	
	}
	
	public function valid_content_request($value_code)
	{
		$result=$this->valid_content($value_code, false);
		if (!($result instanceof Report)) return $this->sign_report(new Report_resolution($result)); // приведения к стандарту request()
		return $result;
	}
	
	public function change_model($value_code, $new_model, $rewrite=true)
	{
		if ( (!$rewrite) && (array_key_exists($value_code, $this->model)) ) return false;
		$this->model[$value_code]=$new_model;
		if (array_key_exists($value_code, $this->values))
		{
			$value=$this->produce_value($value_code);
			$value->change_model($new_model);
		}
		return true;
	}
	
	public function clear()
	{
		$this->values=[];
	}
	
	// к этому методу обращаются составляющие значения, когда к ним приходит запрос на заполнение. Большая часть значений отвечает только за работу с типом и только некоторые, ссылающиеся на другие (reference), заполняют себя сами.
	abstract public function fill_value($value);
	
	// для реализации интерфейса Pathway, ведь значения сами являются Templater'ами, а некоторые - ValueHost'ами.
	public function follow_track($track)
	{
		return $this->produce_value_soft($track);
	}
	
	// для реализации интерфейса Templater
	public function template($code, $line=[])
	{
	}
}

abstract class MonoSet extends ValueSet
{
	const
		COUNT_CODE='count';

	public function model($ord, $soft=false)
	{
		return $this->model;
	}
	
	public function add($value, $ord=null)
	{
		if (is_null($ord)) $ord=$this->generate_ord($value);
		if (array_key_exists($ord, $this->values)) die ('BAD ORD');
		$this->values[$ord]=$value;
		return $ord;
	}
	
	public function add_new($ord=null)
	{
		$value=$this->create_value($ord);
		$ord=$this->add($entity, $ord);
		return $ord;
	}
	
	public function remove($value)
	{
		$ord=array_search($value, $this->values);
		if ($ord!==false) unset($this->values[$ord]);
		return $ord;
	}
	
	public function generate_ord($value)
	{
		if (empty($this->values)) return 0;
		return max(array_keys($this->values))+1;
	}
	
	// для обращений типа @current_player.active_pokemon.count к линксетам.
	public function request($code)
	{
		if ($code===static::COUNT_CODE) return $this->sign_report(new Report_resolution(count($this->values)));
		return parent::request($code);
	}
	
	public function value($code)
	{
		if ($code===static::COUNT_CODE) return count($this->values);
		return parent::request($code);
	}
	
	public function template($code, $line=[])
	{
		if ($code===static::COUNT_CODE)
		{
			if ( (count($this->values)==0) && (array_key_exists('on_empty', $line)) ) return $line['on_empty'];
			return count($this->values);
		}
		return parent::template($code, $line);
	}
}
?>