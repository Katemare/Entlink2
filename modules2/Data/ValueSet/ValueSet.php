<?
namespace Pokeliga\Data;

class ValueSet implements ValueHost, Pathway, \Pokeliga\Template\Templater
{
	const
		VALUE_FACTORY='\Pokeliga\Data\Value',
		GENERIC_ELEMENT_KEY='_generic_element',
	
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
		$generic_model, // если установить этот параметр (модель значения), то в наборе может быть значение с любым ключом; при отсутствии ключа используется данная модель.
		$default_table,
		$values=[],
		$subscribe_to_changes=true,
		$changed_from_db=true, // запоминает, изменились ли данные в наборе по сравнению с данными из БД. По умолчанию истина потому, что большая часть ValueSet'ов вообще не имеют отношения с БД - это необходимо для совместимости.
		$correction_mode=ValueSet::VALUES_SOFT;
	
	
	public static function from_model($model=null)
	{
		$set=new static();
		if ($model!==null) $set->init_model($model);
		return $set;
	}
	
	public function init_model($model)
	{
		if ($this->model!==null) return; // только для первоначального задания модели.
		if (array_key_exists(static::GENERIC_ELEMENT_KEY, $model))
		{
			$this->generic_model=$model[static::GENERIC_ELEMENT_KEY];
			unset($model[static::GENERIC_ELEMENT_KEY]);
		}
		$this->model=$model;
	}
	
	// чтобы вызывать при наследовании.
	public function __construct()
	{
	}

	// FIX! не гарантирует, что модель полная, если поля модели заполняются лениво.
	public function get_complete_model()
	{
		return $this->model;
	}
	
	public function to_array($keys=null, $now=true)
	{
		if ($keys===null) $keys=array_keys($this->get_complete_model());
		$tasks=[];
		$result=[];
		foreach ($keys as $key)
		{
			$report=$this->request($key);
			if ($report instanceof \Report_tasks) $tasks=array_merge($tasks, $report->tasks);
			elseif (empty($tasks))
			{
				if ($report instanceof \Report_resolution) $result[$key]=$report->resolution;
				else $result[$key]=$report;
			}
		}
		if (!empty($tasks))
		{
			if ($now)
			{
				Process_collection::complete_tasks(...$tasks);
				return $this->to_array($keys, false);
			}
			return new \Report_tasks($tasks, $this);
		}
		return $result;
	}
	
	public function value($code)
	{
		$value=$this->produce_value($code);
		$result=$value->value();
		return $result; // может быть класса \Report с описанием стадии процесса.
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
		
		if (!$this->model_code_exists($code))
		{
			if ($soft) return new \Report_impossible('unknown_value_code', $this);
			vdump($this);
			throw new \Exception('unknown ValueSet value code');
		}
		if (!array_key_exists($code, $this->values))
		{
			$value=$this->create_value($code);
			$this->setup_value($value);	
			$this->values[$code]=$value;
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
		$value_factory=static::VALUE_FACTORY;
		$value=$value_factory::for_valueset($this, $code);
		return $value;
	}
	
	public function setup_value($value)
	{
		$this->model[$value->code]=$value->model; // на случай, если значение подкорректировало модель (например, нормализовало).
		if ($this->subscribe_to_changes)
		{
			$value->add_call
			(
				function($value, $content, $source) // предполагает черту Caller_backreference
				{
					$value->master->before_value_set($value, $content, $source);
					
					// обращение идёт таким образом, а не через $this, потому что при клонировании сущности в другой пул контекст этой функции не меняется, и если воспользоваться $this, то она укажет на другую сущность.
				},
				'before_set'
			);
		}
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
			$model_entry=$this->generate_model_entry($code);
			if ($model_entry===null)
			{
				if ($soft) return ($model_entry instanceof \Report_impossible) ? $model_entry : new \Report_impossible('no_model_code', $this);
				else { vdump($this->model); die ('UNKNOWN VALUE CODE 1: '.$code); }
			}
			else $this->model[$code]=$model_entry;
		}
		return $this->model[$code];
	}	
	
	protected function generate_model_entry($code)
	{
		if ($this->generic_model!==null) return $this->generic_model; // не учитывает, о каком ключе идёт речь - цифровом, текстовом... по данному подходу любой ключ возможен.
	}
	
	public function model_soft($code)
	{
		return $this->model($code, true);
	}
	
	public function model_code_exists($code)
	{
		return !($this->model_soft($code) instanceof \Report_impossible); // вызов model() нужен для того, что некоторые фрагменты модели могут создаваться по запросу.
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
			if ($this->produce_value_soft($code) instanceof \Report_impossible) continue;
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
		if (!($result instanceof \Report)) return new \Report_resolution($result, $this); // приведения к стандарту request()
		return $result;
	}
	
	public function change_model($value_code, $new_model, $rewrite=true)
	{
		if ( (!$rewrite) && (array_key_exists($value_code, $this->model)) ) return false;
		if ( array_key_exists($value_code, $this->model) and $this->model[$value_code]===$new_model) return;
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
	public function fill_value($value)
	{
		$value->set_failed(); // этот набор почти что абстрактный и не знает, как заполнять значения.
	}
	
	// для реализации интерфейса Pathway, ведь значения сами являются Templater'ами, а некоторые - ValueHost'ами.
	public function follow_track($track, $line=[])
	{
		return $this->produce_value_soft($track);
	}
	
	// для реализации интерфейса Templater
	public function template($code, $line=[])
	{
	}
}

// это набор однородных значений, заполняемых по числовым индексам от нуля.
class MonoSet extends ValueSet
{
	const
		COUNT_CODE='count';

	public function model($ord, $soft=false)
	{
		return $this->model;
	}
	
	public function ord_exists($ord)
	{
		return array_key_exists($ord, $this->values);
	}
	
	public function add($value, $ord=null)
	{
		if ($ord===null) $ord=$this->generate_ord($value);
		if ($this->ord_exists($ord)) die ('BAD ORD');
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
		if ($code===static::COUNT_CODE) return new \Report_resolution(count($this->values, $this));
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