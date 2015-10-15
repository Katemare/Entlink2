<?
namespace Pokeliga\Form;

class FieldSet extends \Pokeliga\Data\InputSet_complex implements \Pokeliga\Template\Templater, \Pokeliga\Template\Template_context
{
	use \Pokeliga\Entlink\Shorthand;

	const
		SOURCE_DEFAULT=InputSet::SOURCE_POST,
		ERRORS_KEY='__errors',
		EXPIRY_KEY='__expires',
		DEFAULT_EXPIRY=3600,
		FIELD_NAME_CODE='_name', // используется в шаблоне типа {{form._name|field=id}}, возвращает имя поля (к примеру, добаляет префикс).
		PREFIX_CODE='_prefix',
		
		MODE_DISPLAY=1,
		MODE_PROCESS=2,
		
		XML_EXPORT=false;

	static
		$basic_model=null;
		
	public
		$form=null,
		$master=null,
		$super_model=[], // модель, которая поставляется формой. в отличие от $model, которая устанавливается классом. содержит, например, префикс.
		$prefix=null,
		$code=null,
		$mode=FieldSet::MODE_PROCESS,
		$main_template_class='Template_fieldset',
		$template_db_key=null,
		$process_valid_class=null,
		$erase_session_on_success=true,
		$erase_session_on_display=true,
		$process_success=null;

	public static function for_form($type_keyword, $form, $code)
	{
		return static::for_fieldset($type_keyword, $form, $code);
		// пока нет разницы между созданием для формы и созданием для старшего набора полей.
	}
	
	public static function for_fieldset($type_keyword, $fieldset, $code)
	{
		$element=static::from_shorthand($type_keyword);
		$element->setup_from_fieldset($fieldset, $code);
		return $element;
	}
	
	public static function standalone($type_keyword, $super_model=[], $source_setting=null)
	{
		$element=static::from_shorthand($type_keyword);
		$element->setup_standalone($super_model, $source_setting);
		return $element;
	}
	
	public static function standalone_for_display($type_keyword, $super_model=[], $source_setting=null)
	{
		$element=static::standalone($type_keyword, $super_model, $source_setting);
		$element->mode=static::MODE_DISPLAY;
		return $element;
	}
	
	public static function standalone_for_process($type_keyword, $super_model=[], $source_setting=null)
	{
		$element=static::standalone($type_keyword, $super_model, $source_setting);
		$element->mode=static::MODE_PROCESS;
		return $element;
	}
	
	public static function create($super_model=[], $source_setting=null, $model=null)
	{
		$element=new static();
		if ($model!==null) $element->model=$model;
		$element->setup_standalone($super_model, $source_setting);
		return $element;
	}
	
	public static function create_for_display($super_model=[], $source_setting=null, $model=null)
	{
		$element=static::create($super_model, $source_setting, $model);
		$element->mode=static::MODE_DISPLAY;
		return $element;
	}
	
	public static function create_for_display_from_model($model)
	{
		return static::create_for_display([], null, $model);
	}
	
	public static function create_for_process($super_model=[], $source_setting=null, $model=null)
	{
		$element=static::create($super_model, $source_setting, $model);
		$element->mode=static::MODE_PROCESS;
		return $element;
	}
	
	public static function create_for_process_from_model($model)
	{
		return static::create_for_process([], null, $model);
	}
	
	public static function xml_exportable()
	{
		return static::XML_EXPORT===get_called_class();
	}
	
	public static function create_for_xml()
	{
		if (!static::xml_exportable()) return $this->sign_report(new \Report_impossible('not_exportable'));
		
		$prefix=InputSet::instant_fill('prefix', 'keyword');
		if ($prefix instanceof \Report_impossible) $prefix='';
		$super_model=['prefix'=>$prefix];
		
		$element=static::create_for_display($super_model);
		return $element;
	}

	public function instantiated() { }
	
	public function setup_from_fieldset($fieldset, $code)
	{
		$this->instantiated();
		$this->source_setting&=$fieldset->source_setting;		
		$this->code=$code;
		$this->mode=$fieldset->mode;
		$this->super_model=$fieldset->model($code);
		
			$this->master=$fieldset;
		if ($fieldset instanceof Form)
		{
			$this->form=$fieldset;
		}
		else
		{
			$this->form=$fieldset->form;
			if (array_key_exists('prefix', $fieldset->super_model))
			{
				if (!array_key_exists('prefix', $this->super_model)) $this->super_model['prefix']=$fieldset->super_model['prefix'];
				else $this->super_model['prefix']=$fieldset->super_model['prefix'].$this->super_model['prefix'];
			}
		}
		$this->supply_model();
		$this->prefix_model();
	}
	
	public function setup_standalone($super_model=[], $source_setting=null)
	{
		$this->instantiated();
		if ($source_setting===null) $source_setting=$this->source_setting;
		if ($source_setting===null) $source_setting=static::SOURCE_DEFAULT;
		$this->source_setting=$source_setting;
		$this->super_model=$super_model;
		$this->supply_model();
		$this->prefix_model();	
	}
	
	public function supply_model()
	{
		if ($this->model!==null) return;
		if (static::$basic_model===null) { vdump($this); die ('NO MODEL'); }
		$this->model=static::$basic_model;
	}
	
	public function xml_export($field=false)
	{
		if ($field===false)
		{
			$field=InputSet::instant_fill('field', 'keyword');
			if ($field instanceof \Report_impossible) $field=null;
		}
		if ($field===null) return $this->main_template();
		else return $this->template($field);
	}
	
	public function pool()
	{
		if (!empty($this->form)) return $this->form->pool();
		return parent::pool();
	}
	
	public function change_model($value_code, $new_model, $rewrite=true)
	{
		$result=parent::change_model($value_code, $new_model, $rewrite);
		if ($result===true) $this->prefix_model($value_code);
		return $result;
	}
	
	public function prefix()
	{
		if ($this->form!==null) $form_prefix=$this->form->prefix();
		else $form_prefix='';
		if ( (!empty($this->super_model)) && (array_key_exists('prefix', $this->super_model)) ) $model_prefix=$this->super_model['prefix'];
		elseif ($this->prefix!==null) $model_prefix=$this->prefix;
		else $model_prefix='';
		return $form_prefix.$model_prefix;
	}
	
	public function add_prefix($s)
	{
		return $this->prefix().$s;
	}
	
	public function prefix_model($codes=null)
	{
		if ($codes===null) $codes=array_keys($this->model);
		elseif (!is_array($codes)) $codes=[$codes];
		foreach ($codes as $code)
		{
			$model=&$this->model[$code];
			if (array_key_exists('name', $model)) $basic_name=$model['name'];
			else $basic_name=$code;
			$model['name']=$this->add_prefix($basic_name);
		}
	}
	
	public function create_value($code)
	{
		$model=$this->model($code);
		if (!array_key_exists('fieldset_type', $model)) return parent::create_value($code);
		
		$subfield=FieldSet::for_fieldset($model['fieldset_type'], $this, $code);
		return $subfield;
	}
	
	public function template($code, $line=[])
	{
		if ($code===static::FIELD_NAME_CODE) return $this->field_name_template($line);
		if ($code===static::PREFIX_CODE) return $this->prefix();
		
		$value=$this->produce_value_soft($code);
		if ($value instanceof \Report) return;
		if ($value instanceof FieldSet) return $value->main_template($line);
	
		if (!empty($line['raw'])) return $value->template(null, $line);
		
		if ($value->in_value_model('template')) $template_code=$value->value_model_now('template');
		else $template_code=$value::DEFAULT_FIELD_TEMPLATE;
		$template=Template_field::for_fieldset($template_code, $this, $value, $line);
		return $template;
	}

	public function main_template($line=[])
	{
		$this->mode=static::MODE_DISPLAY;
		$class=$this->main_template_class;
		$template=$class::with_line($line);
		if ($template instanceof \Pokeliga\Template\Template_from_db) $template->db_key=$this->template_db_key;
		if ($template instanceof Template_requies_fieldset) $template->set_fieldset($this);
		if ($template instanceof Template_fieldset) $template->main=true;
		$template->context=$this->make_context();
		return $template;
	}
	
	public function field_name_template($line=[])
	{
		if (!array_key_exists('field', $line)) return;
		$name=$this->name($line['field']);
		if ($name===false) return;
		return $name;
	}
	
	public function make_context()
	{
		return $this;
	}

	public function main_template_initiated($template)
	{
		$this->consider_template_line($template->line);
	}
	
	// важно, чтобы этот метод вызывался раньше следующего! потому что сессионное имя может зависеть от параметров, переданных в командной строке.
	public function consider_template_line($line)
	{
	}
	
	public function prepare_display()
	{
		if ($this->form!==null) return; // все данные готовит старшая форма.
		$this->correction_mode=static::VALUES_ANY_MID_VALIDITY;
		if ($this->has_session_data()) $this->fill_from_session();
		else return $this->fill_defaults(null, false);
	}
	
	public function templaters()
	{
		return [$this];
	}
	
	public function follow_track($track, $line=[])
	{
		$tracks=$this->tracks();
		if (array_key_exists($track, $tracks)) return $tracks[$track];
		return parent::follow_track($track, $line);
	}
	
	public $tracks;
	public function tracks()
	{
		if ($this->tracks===null) $this->make_tracks();
		return $this->tracks;
	}
	
	public function make_tracks()
	{
		$this->tracks=['fieldset'=>$this];
		if (!empty($this->form)) $this->tracks['form']=$this->form;
	}
	
	public function name($code)
	{
		$model=$this->model($code);
		if ($model===false) return $model; // STUB: пока не реализовано, а просто вываливается.
		if (array_key_exists('name', $model)) return $model['name'];
		return $code;
	}
	
	public function input_value($value, $source=null)
	{
		$value=$this->produce_value($value);
		
		if ($value instanceof FieldSet_sub) return $this->sign_report(new \Report_task($value->process_task()));
		elseif ($value instanceof FieldSet) return $value->input($source);
		
		$result=parent::input_value($value, $source);
		if ($result instanceof \Report) return $result;
		
		if ($value->in_value_model('template'))
		{
			$class=Template_field::compose_prototype_class($value->value_model_now('template'));
			if ( ($value->has_state(Value::STATE_FAILED)) && (string_instanceof($class, 'Template_field_on_failure')) ) $value->set($class::content_on_failure(), Value::BY_INPUT);
			if (string_instanceof($class, 'Template_field_process_content')) $value->set($class::process_content($value->content), Value::BY_INPUT);
		}
	}
	
	public function process_task()
	{
		$this->mode=static::MODE_PROCESS;
		return Task_for_formfield_process::for_fieldset($this);
	}
	
	public function process_valid()
	{
		$task=$this->create_valid_processor();
		if ($task instanceof \Report) return $task;
		$this->process_success=&$task->resolution;
		
		if ($this->erase_session_on_success)
		{
			$task->add_call
			(
				function($task) { if ($task->successful()) $this->erase_session(); },
				'complete'
			);
		}
		return $this->sign_report(new \Report_task($task));
	}
	
	public function create_valid_processor()
	{
		$class=$this->process_valid_class;
		if (empty($class)) { vdump($this); debug_dump(); die ('UNPROCESSABLE FORM'); }
		$task=$class::for_fieldset($this);
		return $task;
	}
	
	// это можно сделать и через вызовы, но может быть, так быстрее и логичнее.
	public function input_is_invalid()
	{
		$this->save_input_to_session();
	}
	
	public function process_is_invalid()
	{
		$this->save_input_to_session();
	}
	
	// задача этого метода - сохранить содержимое всех полей (действительных, недействительных). она вызывается после неудачной попытки обработать форму, если ввод был плохим или обработка не удалось по другой причине. во время ввода были созданы все значения, которые могли быть в форме - ввод продолжался, даже когда была уже ясно ошибочность данных, специально чтобы в $values оказались все возможные поля.
	// следует вызывать только у старшего набора, желательно у формы.
	public function save_input_to_session($master=true, &$container=[])
	{
		if ($master)
		{
			if (!($this instanceof Form)) return; // STUB
		
			global $_SESSION;
			if (!array_key_exists($session_name=$this->session_name(), $_SESSION)) $_SESSION[$session_name]=[];
			$container=&$_SESSION[$session_name];
		}
	
		$this->reset();
		$this->correction_mode=ValueSet::VALUES_ANY;
		$values_with_templates=[];
		foreach ($this->model as $code=>$model)
		{
			$value=$this->produce_value($code);
			if ($value instanceof FieldSet)
			{
				$container[$code]=[];
				$value->save_input_to_session(false, $container[$code]);
				continue;
			}
			
			if ( (!array_key_exists('template', $model)) || ($model['template']===false) ) continue;
			$values_with_templates[]=$value;
			$this->input_value($value);
			if (!$value->has_state(Value::STATE_FILLED)) continue;
			$content=$value->content();
			if ($content instanceof \Report_impossible) continue;
			$container[$code]=$content;
		}
		
		if ($master)
		{
			if (!empty($this->errors)) $container[static::ERRORS_KEY]=$this->errors;
			$container[static::EXPIRY_KEY]=time()+static::DEFAULT_EXPIRY;
		}
	}
	
	public function save_to_session($data=null)
	{
		if ($data===null) return $this->save_input_to_session();
		$name=$this->session_name();
		global $_SESSION;
		$_SESSION[$name]=$data;
	}
	
	public function erase_session()
	{
		$name=$this->session_name();
		global $_SESSION;
		unset($_SESSION[$name]);
	}
	
	public function has_session_data()
	{
		$name=$this->session_name();
		global $_SESSION;
		if (!array_key_exists($name, $_SESSION)) return false;
		if ( (array_key_exists(static::EXPIRY_KEY, $_SESSION)) && ($_SESSION[static::EXPIRY_KEY]<time()) )
		{
			$this->erase_session();
			return false;
		}
		return true;
	}
	
	public function read_session()
	{
		if (!$this->has_session_data()) return $this->sign_report(new \Report_impossible('session_empty'));
		$name=$this->session_name();
		global $_SESSION;
		return $_SESSION[$name];	
	}
	
	public function fill_from_session($master=true, $container=[])
	{
		if ($master)
		{
			if (!$this->has_session_data()) return;
			$container=$this->read_session();
			global $_SESSION;
			if ($this->erase_session_on_display) $this->erase_session(); // STUB
		}
		
		foreach ($container as $code=>$content)
		{
			$value=$this->produce_value_soft($code);
			if ($value instanceof \Report_impossible) continue;
			if ($value instanceof FieldSet)
			{
				$value->fill_from_session(false, $content);
				continue;
			}
			$value->set($content, Value::BY_INPUT);
		}
	}
	
	public function set_value($code, $content, $source=Value::BY_OPERATION, $rewrite=true)
	{
		$value=$this->produce_value($code);
		if ( ($value instanceof FieldSet) && (!($value instanceof FieldSet_sub) ) ) $value->set_by_array($content, $source, $rewrite);
		// потому что FieldSet_sub оснащена как Value и имеет свой метод set.
		else parent::set_value($code, $content, $source, $rewrite);
	}
	
	public function session_name()
	{
		return $this->add_prefix(get_class($this));
	}
	
	// вызывается только при показе формы, не при обработке ввода. вызывается из задачи показа главного шаблона формы.
	public function fill_defaults($codes=null, $rewrite=true)
	{
		return $this->fill_defaults_from_model($codes, $rewrite);
	}
	
	public function fill_defaults_from_model($codes=null, $rewrite=true)
	{
		if ($codes===null) $codes=array_keys($this->model);
		$content=[];
		$fieldsets=[];
		foreach ($codes as $code)
		{
			$model=$this->model($code);
			// STUB: не поддерживает процедурных значений.
			if (array_key_exists('default_for_display', $model)) $content[$code]=$model['default_for_display'];
			elseif (array_key_exists('default', $model)) $content[$code]=$model['default'];
			elseif (array_key_exists('fieldset_type', $model)) $fieldsets[]=$code;
		}
		$this->set_by_array($content, Value::NEUTRAL_CHANGE, $rewrite);
		
		$tasks=[];
		foreach ($fieldsets as $code)
		{
			$value=$this->produce_value($code);
			$report=$value->fill_defaults(null, $rewrite);
			if ($report instanceof \Report_tasks) $tasks=array_merge($tasks, $report->tasks);
		}
		
		if (!empty($tasks)) return $this->sign_report(new \Report_tasks($tasks));
	}
}

abstract class Task_for_fieldset extends Task_for_inputset
{
	public
		$form=null;
		
	public static function for_fieldset($field, $source=null)
	{
		$task=static::for_inputset($field, $source);
		$task->form=$field->form;
		return $task;
	}
}

class Task_for_formfield_process extends Task_for_fieldset
{
	use Task_steps;
	
	const
		STEP_INPUT=0,
		STEP_PROCESS=1,
		STEP_ANALYZE=2,
		STEP_INVALID_INPUT=3,
		STEP_INVALID_PROCESS=4,
		STEP_FINISH=5;
		
	public
		$input_task=null,
		$process_task=null,
		$final_task=null;
		
	public function run_step()
	{
		if ($this->step===static::STEP_INPUT)
		{
			$report=$this->inputset->input(null, $this->source);
			if ( ($report instanceof \Report_success) || ($report===true) ) return $this->advance_step();
			elseif ($report instanceof \Report_impossible) $this->advance_step(static::STEP_INVALID_INPUT);
			elseif ($report instanceof \Report_task) $this->input_task=$report->task;
			return $report;
		}
		elseif ($this->step===static::STEP_PROCESS)
		{
			if ( ($this->input_task!==null) && ($this->input_task->failed()) )
			{
				$this->errors=$this->input_task->errors;
				return $this->advance_step(static::STEP_INVALID_INPUT);
			}

			$report=$this->inputset->process_valid();
			
			if ($report instanceof \Report_task)
			{
				$this->process_task=$report->task;
				return $report;
			}
			elseif ($report instanceof \Pokeliga\Task\Task)
			{
				$this->process_task=$report;
				return $this->sign_report(new \Report_task($this->process_task));
			}
			elseif ($report instanceof \Report_success) return $report;
			elseif ($report instanceof \Report_impossible)
			{
				$this->errors=$report->errors;
				return $this->advance_step(static::STEP_INVALID_PROCESS);
			}
			else { vdump($report); die ('BAD PROCESS REPORT'); }
		}
		elseif ($this->step===static::STEP_ANALYZE)
		{
			if ($this->process_task->failed())
			{
				$this->errors=$this->process_task->errors;
				return $this->advance_step(static::STEP_INVALID_INPUT);
			}
			return $this->process_task->report();
		}
		elseif ($this->step===static::STEP_INVALID_INPUT)
		{
			$result=$this->inputset->input_is_invalid();
			if ($result instanceof \Report_task) $this->final_task=$result->task;
			elseif ($result===null) return $this->sign_report(new \Report_impossible($this->errors));
			return $result;
		}
		elseif ($this->step===static::STEP_INVALID_PROCESS)
		{
			$result=$this->inputset->process_is_invalid();
			if ($result instanceof \Report_task) $this->final_task=$result->task;
			elseif ($result===null) return $this->sign_report(new \Report_impossible($this->errors));
			return $result;
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			if ($this->final_task!==null) return $this->final_task->report();
			return $this->sign_report(new \Report_impossible('invalid_fieldset'));
		}
	}
	
	public function finish($success=true)
	{
		$this->inputset->success=$success;
		if ($success) $this->inputset->resolution=$this->resolution;
		else $this->inputset->errors=$this->errors;
		parent::finish($success);
	}
}

abstract class FieldSet_sub extends FieldSet implements ValueContent
{
	use ValueModel_owner;
	
	public function &value_model_array()
	{
		return $this->super_model;
	}
	
	public function set($content, $source=Value::BY_OPERATION)
	{
		$this->set_by_array($content, $source);
	}

	public function process_valid()
	{
		$this->process_success=true;
		return $this->sign_report(new \Report_resolution($this->pack_input($this->input_fields)));
	}
	
	public function content()
	{
		if ($this->process_success!==true) die ('UNIMPLEMENTED YET: unfilled fieldset');
		return $this->resolution;
	}
	
	public function is_valid()
	{
		return $this->process_success===true;
	}
	
	public function state()
	{
		if ($this->process_success===null) return Value::STATE_UNFILLED;
		if ($this->process_success===true) return Value::STATE_FILLED;
		if ($this->process_success===false) return Value::STATE_FAILED;
		die('BAD FIELDSET STATE');
	}
	
	public function set_state($state)
	{
		die('DONT SET FIELDSET STATE');
	}
	
	public function has_state($state)
	{
		return $this->state()===$state;
	}
}

interface Template_requies_fieldset
{
	public function set_fieldset($fieldset);
}

class Template_fieldset extends Template_from_db implements Template_requies_fieldset
{
	const
		STEP_INIT=-1,
		STEP_PREPARE_FIELDSET=-1;
		
	public
		$field=null,
		$main=false;
	
	public function set_fieldset($fieldset)
	{
		$this->field=$fieldset;
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_PREPARE_FIELDSET)
		{
			if (!$this->main) return $this->advance_step();
			$this->field->page=$this->page; // STUB: в будущем не понадобится, потому что все данные должны поступать через командную строку, которая уже может сама обращаться к странице.	
			$result=$this->field->prepare_display();
			if ($result instanceof \Report_tasks) return $result;
			return $this->advance_step();
		}
		else return parent::run_step();
	}
	
	public function initiated()
	{
		parent::initiated();
		if (!$this->main) return;
		$this->field->main_template_initiated($this);
	}
}
?>