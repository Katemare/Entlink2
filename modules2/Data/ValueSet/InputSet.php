<?
namespace Pokeliga\Data;

class InputSet extends ValueSet
{
	const
		SOURCE_GET='get',
		SOURCE_POST='post',
		SOURCE_SESSION='session',
		SOURCE_GET_POST='get_post',
		SOURCE_COOKIE='cookie',
		SOURCE_DEFAULT=InputSet::SOURCE_GET;

	static
		$settings=
		[
			'get'=>['_GET'],
			'post'=>['_POST'],
			'session'=>['_SESSION'],
			'get_post'=>['_GET', '_POST'],
			'cookie'=>['_COOKIE']
		];
		
	public
		$subscribe_to_changes=false,
		$source_setting=InputSet::SOURCE_DEFAULT,
		$source=['_GET'],
		$input_success=null;

	public static function from_model($model=null, $setting=null)
	{
		$inputset=parent::from_model($model);
		if ($setting!==null) $inputset->source_setting=$setting;
		return $inputset;
	}
	
	public static function instant_fill($name, $type, $source_setting=null)
	{
		if (is_array($type)) $model=[$name=>$type];
		else $model=[$name=>['type'=>$type]];
		$inputset=static::from_model($model, $source_setting);
		$inputset->input_value($name);
		return $inputset->content_of($name);
	}
	
	public function sources($setting=null)
	{
		if ($setting===null) $setting=$this->source_setting;
		if ( ($setting===null) || (!array_key_exists($setting, static::$settings)) ) $setting=static::SOURCE_DEFAULT;
		return static::$settings[$setting];
	}
		
	public function fill_value($value)
	{
		$this->input_value($value);
	}
	
	public function input_value($value, $source=null)
	{
		$value=$this->produce_value($value);
		if ($value instanceof InputSet) return $value->input(null, $source);
		if ( ($value->has_state(Value::STATE_FILLED)) && ($value->last_source===Value::BY_INPUT) ) return;
	
		$code=$value->code;
		$model=$this->model($code);
		if (array_key_exists('name', $model)) $name=$model['name'];
		else $name=$code;
		
		if ($value instanceof Value_upload) $source=['_FILE']; // FIXME: больше не работает
		else $source=$this->sources($source);
	
		$got_it=false;
		$content=null;
		foreach ($source as $source_code)
		{
			global $$source_code;
			$source=$$source_code;
			if (empty($source)) continue;
			if (array_key_exists($name, $source))
			{
				$got_it=true;
				$content=$source[$name];
			}
		}
		
		// debug('INPUT '.$value->code.': '.var_export($content, true).' (NAME '.$this->name($value->code).')'.((!$got_it)?(' FAILED'):('')));
		if (!$got_it)
		{
			if ($value->in_value_model('default'))
			{
				$content=$value->value_model_now('default');
				$value->set($content, Value::NEUTRAL_CHANGE);
			}
			else $value->set_state(Value::STATE_FAILED);
		}
		else $value->set($content, Value::BY_INPUT);
		
		return $value->has_state(Value::STATE_FILLED);
	}
	
	public function input($codes=null, $source=null)
	{
		$this->input_sucess=null;
		if ($codes===null) $codes=array_keys($this->model);
		
		foreach ($codes as $code)
		{
			$result=$this->input_value($code, $source);
			if ($result===false) $this->input_success=false;
		}
		if ($this->input_success===null) $this->input_success=true;
		
		if ($this->input_success===true) return $this->sign_report(new \Report_success());
		else return $this->sign_report(new \Report_impossible('bad_input'));
	}
	
	public function pack_input($codes=null)
	{
		if ($codes===null) $codes=array_keys($this->values);
		
		$packed=[];
		foreach ($codes as $value_code)
		{
			$value=$this->produce_value($value_code);
			if ($value instanceof InputSet) $content=$value->pack_input();
			else $content=$value->content();
			$packed[$value_code]=$content;
		}
		return $packed;
	}
	
	public function content_of($value_code)
	{
		$value=$this->produce_value($value_code);
		if (!($value instanceof ValueContent)) { vdump($value_code); vdump($this); die('BAD CONTENT CALL'); }
		return parent::content_of($value_code);
	}
	
	public function make_url_args($more_args=null)
	{
		$args=[];
		foreach ($this->values as $code=>$value)
		{
			if ($value->has_state(Value::STATE_FILLED)) $args[$code]=$value->content();
		}
		if (!empty($more_args)) $args=array_merge($args, $more_args);
		return Router()->compose_url_args($args);
	}
}

// набор вводимых полей, которым требуется проверка и поэтапный ввод.
class InputSet_complex extends InputSet
{
	public
		$input_fields=null,
		$input_task_class='Task_complex_input',
		
		$resolution=null,
		$errors=null;
	
	public function update_subfields()
	{
		return false;
	}
	
	public function input_fields()
	{
		if ($this->input_fields===null) return array_keys($this->model);
		return $this->input_fields;
	}
	
	public function input($codes=null, $source=null)
	{
		if ($codes!==null) return $this->sign_report(new \Report_impossible('partial_complex_input'));
		$class=$this->input_task_class;
		$task=$class::for_inputset($this, $source);
		return $this->sign_report(new \Report_task($task));
	}
}

trait Multistage_input
{
	/*
	public
		$model_stages=
		[
			'some_stage'=>['field3'],
			'other_stage'=>['field3', 'field4']
		],
		$model_stage=0;
	*/
	
	public function change_model_stage($new_stage)
	{
		if ($this->model_stage===$new_stage) return false;
		$this->model_stage=$new_stage;
		if (!array_key_exists($new_stage, $this->model_stages)) die ('NO MODEL FRAGMENT');
		
		$result=$this->set_model_stage($new_stage);
		if ($result===false) return false; // если ничего нового не добавилось, сообщаем, что новых полей нет, довводить ничего не надо.
		return true;
	}
	
	public function set_model_stage($new_stage)
	{
		if (empty($this->model_stages[$new_stage])) return false;
		if ($this->model_stages[$new_stage]===true) return true;
		$this->input_fields=array_unique(array_merge($this->input_fields, $this->model_stages[$new_stage]));
	}
}

abstract class Task_for_inputset extends Task
{
	public
		$inputset=null,
		$source=null;
		
	public static function for_inputset($inputset, $source=null)
	{
		$task=new static();
		$task->inputset=$inputset;
		$task->source=$source;
		return $task;
	}
}

// важно, чтобы эта задача постаралась ввести как можно больше полей в рамках того, какие значения являются действительными. она не должна прерываться от первого же плохого значения.
class Task_complex_input extends Task_for_inputset
{
	use Task_steps;
	
	const
		STEP_INPUT=0,
		STEP_VALIDATE=1,
		STEP_FINISH=2;
	
	public
		$inputted=[],
		$has_invalid_values=false,
		$new_values=[];
	
	public function run_step()
	{
		if ($this->step===static::STEP_INIT)
		{
			$this->inputset->input_success=null;
			$this->inputset->errors=null;
			$this->inputset->resolution=null;
		}
	
		if ($this->step===static::STEP_INPUT)
		{
			$subfields_left=$this->unfinished_subfields();
			//debug('INPUTTINNG '.implode(', ', $subfields_left));
			$this->new_values=$subfields_left;
			if (empty($subfields_left)) return $this->advance_step();
			$tasks=[];
			foreach ($subfields_left as $subfield)
			{
				//debug ('SUBFIELD '.$subfield.' PREINPUT IS '.var_export($this->inputset->content_of($subfield), true));
				$result=$this->inputset->input_value($subfield, $this->source);
				//debug ('SUBFIELD '.$subfield.' INPUT IS '.var_export($this->inputset->content_of($subfield), true));
				if ($result instanceof \Report_tasks) $tasks=array_merge($tasks, $result->tasks);
			}
			$this->inputted=array_merge($this->inputted, $subfields_left);
			if (!empty($tasks)) return $this->sign_report(new \Report_tasks($tasks));
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_VALIDATE)
		{
			$validators=[];
			foreach ($this->new_values as $value_code)
			{
				$value=$this->inputset->produce_value($value_code);
				if ($value instanceof InputSet) continue;
				$result=$value->is_valid(false);
				if ($result===false)
				{
					 // debug('INVALID '.$value_code.' of '.get_class($this->inputset).'. CONTENT:');
					 // vdump($value->content());
					// die('INVALID');
					$this->has_invalid_values=true;
				}
				if ($result instanceof \Report_tasks)
				{
					// debug('VALIDATING '.$value_code);
					$validators=array_merge($validators, $result->tasks);
				}
			}
			if (!empty($validators)) return $this->sign_report(new \Report_tasks($validators));
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			if ($this->has_invalid_values) return $this->sign_report(new \Report_impossible('has_invalid_values'));
			elseif ($this->inputset->update_subfields()) return $this->advance_step(static::STEP_INPUT);
			else return $this->sign_report(new \Report_success());
		}
	}
	
	public function completed_dependancy($task, $identifier=null)
	{
		if ( ($this->step===static::STEP_INPUT) && ($task->failed()) )
		{
			// vdump($task);
			// die('INVALID');
			$this->has_invalid_values=true; // засекает неверный ввод даже у дочерних InputSet'ов.
		}
		if ( ($task instanceof Validation ) && ($task->failed()) )
		{
			// debug('INVALID '.$task->value->code.' of '.get_class($this->inputset).'. CONTENT:');
			// vdump($task->value->content());
			// debug_dump(); 
			// die('INVALID');
			$this->has_invalid_values=true;
		}
	}
	
	public function unfinished_subfields()
	{
		return array_diff($this->inputset->input_fields(), $this->inputted);
	}
	
	public function finish($success=true)
	{
		$this->inputset->input_success=$success;
		parent::finish($success);
	}
}
?>