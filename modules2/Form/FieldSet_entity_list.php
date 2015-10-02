<?
namespace Pokeliga\Form;
// STUB: пока поддерживает только редактирование фиксированного списка.

class FieldSet_entity_list extends FieldSet_list
{
	const
		FILL_DEFAULTS_CLASS='Task_fieldset_entity_list_defaults';

	public function set_value($value_code, $content, $source_code=Value::BY_OPERATION, $rewrite=true)
	{
		if ($this->is_list_code($value_code))
		{
			$field=$this->produce_value($value_code); // модель обычно уже создана методом set.
			$field->entity_id=$content;
		}
		else parent::set_value($value_code, $content, $source_code);
	}
	
	public function process_valid()
	{
		$count=$this->content_of(static::COUNT_KEY);
		$tasks=[];
		
		for ($x=0; $x<$count; $x++)
		{
			$field=$this->produce_value($x);
			$tasks[]=$field->process_task(); // ввод не должен повторяться, потому что у значений уже должно быть поставлен статус filled.
		}
		
		$process=new Process_collection_absolute($tasks);
		return $this->sign_report(new \Report_task($process));
	}
	
	public function fill_defaults($codes=null, $rewrite=true)
	{
		if ($codes===null) $codes=array_keys($this->model);
		
		$tasks=[];
		
		$class=static::FILL_DEFAULTS_CLASS;
		$task=$class::for_fieldset($this);
		$task->rewrite=$rewrite;
		$tasks[]=$task;
		
		// STUB! пока ввод базового и нового поля не нужны.
		/*
		$result=parent::fill_defaults($codes, $rewrite);
		if ($result instanceof \Report_tasks) $tasks=array_merge($tasks, $result->tasks);
		*/
		
		if (!empty($tasks)) return $this->sign_report(new \Report_tasks($tasks));
	}
	
	public function set_by_linkset($linkset)
	{
		$count=count($linkset->values);
		$set[static::COUNT_KEY]=$count;
		
		// STUB
		$this->super_model['min']=$count;
		$this->super_model['max']=$count;
		
		$next_id=0;
		foreach ($linkset->values as $key=>$entity)
		{
			$set[$next_id++]=$entity->db_id; // STUB: пока не проверяет совпадение id_group и не поддерживает различные типы сущностей в одном сете.
		}
		$this->set_by_array($set);
	}
}

class Task_fieldset_entity_list_defaults extends Task_for_fieldset
{
	use Task_steps;
	
	const
		STEP_SET_SELECT=0,
		STEP_SAVE_LIST=1,
		STEP_DEFAULT_SUBFIELDS=2,
		STEP_FINISH=3;
		
	public
		$rewrite=true, // STUB: не совсем корректно используется.
		$select=null;
	
	public function run_step()
	{
		if ($this->step===static::STEP_SET_SELECT)
		{
			$result=$this->create_select();
			return $result;
		}
		elseif ($this->step===static::STEP_SAVE_LIST)
		{
			if ($this->select->failed()) return $this->select->report();
			$linkset=$this->select->value->content();
			$this->inputset->set_by_linkset($linkset);
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_DEFAULT_SUBFIELDS)
		{
			$tasks=[];
			$count=$this->inputset->content_of(FieldSet_list::COUNT_KEY);
			for ($x=0; $x<$count; $x++)
			{
				$value=$this->inputset->produce_value($x);
				$result=$value->fill_defaults(null, $this->rewrite);
				if ($result instanceof \Report_tasks) $tasks=array_merge($tasks, $result->tasks);
			}
			if (empty($tasks)) return $this->sign_report(new \Report_success());
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			return $this->sign_report(new \Report_success());
		}
	}
	
	public function create_select()
	{
		$this->list_model=$this->inputset->value_model();
		$this->select=Select::from_model($this->list_model, $this->inputset);
		return $this->sign_report(new \Report_task($this->select));
	}
}
?>