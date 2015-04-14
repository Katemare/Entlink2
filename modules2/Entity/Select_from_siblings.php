<?

class Select_from_siblings extends Selector
{
	use Select_complex, Task_steps;
	
	const
		STEP_REQUEST=0,
		STEP_COMPOSE=1,
		STEP_COMPOSE_AGAIN=2;
	
	public
		$codes;
	
	public function codes()
	{
		if ($this->codes===null)
		{
			$this->codes=$this->value_model_now('sibling_codes');
			if (!is_array($this->codes)) $this->codes=[$this->codes];
		}
		return $this->codes;
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_REQUEST)
		{
			$tasks=[];
			foreach ($this->codes() as $code)
			{
				$report=$this->value->master->request($code);
				if ($report instanceof Report_impossible) return $this->sign_report(new Report_impossible('bad_sibling'));
				elseif ($report instanceof Report_tasks) $tasks=array_merge($tasks, $report->tasks);
			}
			if (!empty($tasks)) return $this->sign_report(new Report_tasks($tasks));
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_COMPOSE)
		{
			$list=[];
			$tasks=[];
			foreach ($this->codes() as $code)
			{
				$value=$this->value->master->produce_value($code);
				$result=$this->extract_from_value($value);
				if ($result instanceof Report_tasks) $tasks=array_merge($tasks, $result->tasks);
				elseif ($result instanceof Report) return $this->sign_report(new Report_impossible('bad_sibling'));
				elseif (empty($result)) continue;
				elseif (is_array($result)) $list=array_merge($list, $result);
				else $list[]=$result;
			}
			
			if (!empty($tasks)) return $this->sign_report(new Report_tasks($tasks));
			else return $this->sign_report(new Report_resolution($this->linkset_from_entities($list)));
		}
		elseif ($this->step===static::STEP_COMPOSE_AGAIN)
		{
			return $this->advance_step(static::STEP_COMPOSE); // задачи, созданные extract_from_value() разрешены и можно попытаться ещё раз.
		}
	}
	
	public function extract_from_value($value)
	{
		if ($value instanceof Value_provides_entity) return $value->get_entity();
		elseif ( ($content=$value->content()) instanceof EntitySet) return $content->values;
		else die('BAD ENTITY VALUE');
	}
}

class Select_linked_to_siblings extends Select_from_siblings
{
	const
		STEP_REQUEST_LINKED=3,
		STEP_COMPOSE_LINKED=4;

	public
		$linked_codes,
		$from_siblings;
	
	public function run_step()
	{
		if ($this->step===static::STEP_COMPOSE)
		{
			$report=parent::run_step();
			if ($report instanceof Report_resolution)
			{
				$this->from_siblings=$report->resolution->values;
				if (empty($this->from_siblings)) return $report;
				return $this->advance_step(static::STEP_REQUEST_LINKED);
			}
			return $report;
		}
		elseif ($this->step===static::STEP_REQUEST_LINKED)
		{
			$tasks=[];
			$linked_codes=$this->linked_codes();
			
			foreach ($this->from_siblings as $subentity)
			{
				foreach ($linked_codes as $code)
				{
					$report=$subentity->request($code);
					if ($report instanceof Report_tasks) $tasks=array_merge($tasks, $report->tasks);
					elseif ($report instanceof Report_impossible) return $report;
				}
			}
			if (!empty($tasks)) return $this->sign_report(new Report_tasks($tasks));
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_COMPOSE_LINKED)
		{
			$subvalues=[];
			$linked_codes=$this->linked_codes();
			
			foreach ($this->from_siblings as $subentity)
			{
				foreach ($linked_codes as $code)
				{
					$result=$subentity->value($code);
					if ($result instanceof Report_impossible) return $result;
					$subvalues[]=$subentity->value_object($code); // нужен для извлечения содержимого.
				}
			}
			
			$list=[];
			foreach ($subvalues as $subvalue)
			{
				$result=$this->extract_from_value($subvalue);
				if ($result instanceof Report_impossible) return $result;
				elseif ($result instanceof Report) return $this->sign_report(new Report_impossible('linked_entity_not_ready'));
				elseif (empty($result)) continue;
				elseif (is_array($result)) $list=array_merge($list, $result);
				else $list[]=$result;
			}
			if (empty($list)) return $this->sign_report(new Report_resolution($this->empty_linkset()));
			else return $this->sign_report(new Report_resolution($this->linkset_from_entities($list)));
		}
		else return parent::run_step();
	}
	
	public function linked_codes()
	{
		if ($this->linked_codes===null)
		{
			$this->linked_codes=$this->value_model_now('linked_codes');
			if (!is_array($this->linked_codes)) $this->linked_codes=[$this->linked_codes];
		}
		return $this->linked_codes;
	}
}

class Select_filter_siblings extends Select_from_siblings
{
	const
		STEP_REQUEST_FILTER=3,
		STEP_FILTER=4;
	
	public
		$from_siblings;
	
	public function run_step()
	{
		if ($this->step===static::STEP_COMPOSE)
		{
			$report=parent::run_step();
			if ($report instanceof Report_resolution)
			{
				$this->from_siblings=$report->resolution->values;
				if (empty($this->from_siblings)) return $report;
				return $this->advance_step(static::STEP_REQUEST_FILTER);
			}
			return $report;
		}
		elseif ($this->step===static::STEP_REQUEST_FILTER)
		{
			$fields=array_keys($this->value_model_now('select_conditions'));
			$tasks=[];
			foreach ($this->from_siblings as $subentity)
			{
				$tasks[]=$this->pre_request($fields, $subentity);
			}
			return $this->sign_report(new Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_FILTER)
		{
			$list=[];
			$conditions=$this->value_model_now('select_conditions');
			foreach ($this->from_siblings as $subentity)
			{
				$ok=true;
				foreach ($conditions as $field=>$value)
				{
					$test_value=$subentity->value($field);
					if ($test_value instanceof Report_impossible) return $test_value;
					if (is_array($value)) $ok=in_array($test_value, $value, true);
					else $ok=$test_value===$value;
					if (!$ok) break;
				}
				if ($ok) $list[]=$subentity;
			}
			
			if (empty($list)) return $this->sign_report(new Report_resolution($this->empty_linkset()));
			else return $this->sign_report(new Report_resolution($this->linkset_from_entities($list)));
		}
		else return parent::run_step();
	}
}
?>