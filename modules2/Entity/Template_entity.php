<?
namespace Pokeliga\Entity;

// STAB: пока что этот шабло не поддерживает различия между сущностями с разными группами айди.
class Template_entity_js_export extends Template_from_db
{
	const
		STEP_REQUEST_DATA=-2,
		STEP_PARSE_DATA=-1,
		STEP_INIT=-2,
		
		NAME_EX='/^[a-z\d_]+$/',
		VAR_EX='/^\$?[a-z_\d]+$/'; // это не покрывает всех возможных названий перменных в js, в особенности элементов массива или объекта, но пока достаточно.
	
	public
		$entity;

	public static function for_entity($entity, $line=[])
	{
		$template=static::with_line($line);
		$template->context=$entity;
		$template->entity=$entity;
		return $template;
	}
		
	public
		$db_key='standard.entity_js_export',
		$elements=['id', 'var', 'properties'],
		$by_name=[];
	
	public function run_step()
	{
		// STUB: здесь должна быть возможность применить конструкцию типа "title as fragment_title" или "title=>fragment_title", чтобы экспортированные данные назывались иначе, а также добавить заранее установленных параметров, например, "type=>'point'". возможно, это решится просто добавлением возможности массивов в выражениях.
		if ($this->step===static::STEP_REQUEST_DATA)
		{
			if (!array_key_exists('var', $this->line)) return $this->sign_report(new \Report_impossible('no_var'));
			if (!preg_match(static::VAR_EX, $this->line['var'])) return $this->sign_report(new \Report_impossible('bad_var'));
			
			if (!array_key_exists('data', $this->line)) return $this->sign_report(new \Report_impossible('no_data_list'));
			$data=&$this->line['data'];
			$data=explode(',', $data);
			$converted_data=[];			
			foreach ($data as $name)
			{
	
				if (strpos($name, '=>')!==false)
				{
					$temp=explode('=>', $name);
					$converted_data[trim($temp[0])]=trim($temp[1]);
				}
				else $converted_data[]=trim($name);
			}
			$this->line['data']=$converted_data;
			// STUB: это лучше делать на этапе парсинга шаблона или же позволить пользователю использовать массивы.
			foreach ($data as $from=>$to)
			{
				if (is_numeric($from)) $name=$to; else $name=$from;
				if (!preg_match(static::NAME_EX, $name)) return $this->sign_report(new \Report_impossible('bad_data_entry'));
			}

			$tasks=[]; $mode=null;
			foreach ($data as $from=>$to)
			{
				if (is_numeric($from)) $name=$to; else $name=$from;
				$type=$this->entity->type;
				$type::locate_name($name, $mode);
				if ($mode===EntityType::VALUE_NAME)
				{
					$result=$this->entity->request($name);
					if ($result instanceof \Report_impossible) return $result;
					elseif ($result instanceof \Report_task)
					{
						$this->by_name[$name]=$result->task;
						$tasks[]=$result->task;
					}
					elseif ($result instanceof \Report_resolution) $this->by_name[$name]=$result->resolution;
					else die ('BAD VALUE REQUEST RESULT');
				}
				elseif ($mode===EntityType::TEMPLATE_NAME)
				{
					$result=$this->entity->template($name);
					if ($result instanceof \Report_impossible) return $result;
					elseif ($result instanceof \Report) die('BAD TEMPLATE RESULT');
					elseif ($result instanceof \Pokeliga\Task\Task)
					{
						$this->by_name[$name]=$result;
						$this->setup_subtemplate($result);
						$tasks[]=$result;
					}
					else $this->by_name[$name]=$result;
				}
				elseif ($mode===EntityType::TASK_NAME)
				{
					die('UNIMPLEMENTED YET: Task export');
				}
			}
			if (empty($tasks)) return $this->advance_step();
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_PARSE_DATA)
		{
			$tasks=[];
			foreach ($this->by_name as $name=>&$data)
			{
				if (!($data instanceof \Pokeliga\Task\Task)) continue;
				if ($data->failed()) return $data->report();
				$data=$data->resolution;
			}
			unset($data);
			foreach ($this->by_name as $name=>$data)
			{
				if (!($data instanceof Entity)) continue;
				$report=$data->verify(false);
				if ($report instanceof \Report_impossible) return $report;
				if ($report instanceof \Report_tasks) $tasks=array_merge($tasks, $report->tasks);
			}
			if (empty($tasks)) return $this->advance_step();
			return $this->sign_report(new \Report_tasks($tasks));
		}
		else return parent::run_step();
	}
	
	public function dependancy_resolved($task, $identifier=null)
	{
		if ( ($this->step===static::STEP_REQUEST_DATA) && ($task->failed()) ) $this->impossible('bad_data');
		parent::dependancy_resolved($task, $identifier);
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='id')
		{
			if (!empty($this->line['compact'])) return $this->line['var'].'.length';
			return;
		}
		if ($code==='var') return $this->line['var'];
		if ($code==='properties') return $this->properties_template($line);
	}
	
	public function properties_template($line=[])
	{
		$result=[];
		// тут можно было бы обратиться к шаблону из БД или даже срегенировать шаблон для использования с Template_from_text, но это понадобится разве что для наследования шаблона для работы с JSON, XML и так далее, а эта задача пока не решается.
		foreach ($this->line['data'] as $from=>$to)
		{
			if (is_numeric($from))
			{
				$name=$to;
				$alias=$to;
			}
			else
			{
				$name=$from;
				$alias=$to;
			}
			$value=$this->by_name[$name];
			
			if ($value instanceof Entity) $value=$value->db_id;
			elseif ($value===null) $value='null';
			else $value=var_export($value, true); // STUB: запись чисел, строк и в принципе массивов у php и js должна совпадать, а более сложных форм данных пока у этого шаблона спрашивать не стоит.
			
			$result[]=$alias.': '.$value;
		}
		$result=implode(', ', $result);
		return $result;
	}
}

class Template_entity_option extends Template_from_db
{	
	public
		$entity,
		$db_key='form.option',
		$elements=['value', 'selected', 'title'];
	
	public static function for_entity($entity, $line=[])
	{
		$template=static::with_line($line);
		$template->entity=$entity;
		return $template;
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='value') return $this->entity->db_id;
		if ($code==='title')
		{
			if (array_key_exists('title_template', $this->line)) $template_code=$this->line['title_template'];
			else $template_code='option_title';
			return $this->entity->template($template_code, $line);
		}
		if ($code==='selected')
		{
			if (!empty($this->line['selected'])) return 'selected';
			return '';
		}
		return parent::make_template($code, $line);
	}
}
?>