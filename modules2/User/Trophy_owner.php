<?

// для наследования.
abstract class Trophy_owner extends Aspect
{
	static
		$common_model=
		[
			'trophies'=>
			[
				'type'=>'linkset',
				'id_group'=>'Trophy',
				'select'=>'backlinked',
				'backlink_field'=>'owner'
			],
			'public_trophies'=>
			[
				'type'=>'linkset',
				'id_group'=>'Trophy',
				'select'=>'filter_siblings',
				'sibling_codes'=>'trophies',
				'select_conditions'=>['stashed'=>false, 'public'=>true]
			]
		],
		$tasks=
		[
			'satisfies_trophy'		=>'Task_trophy_owner_satisfies',
			'benefits_from_trophy'	=>'Task_trophy_owner_benefits_from',
			'receive_trophy'		=>'Task_trophy_owner_receive'
		],
		$default_table='user_trophies',
		$init=false;
}

abstract class Task_trophy_analyst extends Task_for_entity
{
	use Task_steps;
	
	const
		STEP_PRE_REQUEST		=0,
		STEP_SECONDARY_REQUEST	=1,
		STEP_ANALYZE_TROPHIES	=2;
	
	public
		$pre_request=['trophies'],
		$additional_pre_request=[],
		$pre_request_from_trophies=['blueprint_entity', 'level'],
		$trophies;
	
	public function run_step()
	{
		if ($this->step===static::STEP_PRE_REQUEST)
		{
			$tasks=[];
			$tasks[]=$this->pre_request(array_merge($this->pre_request, $this->additional_pre_request));
			return $this->sign_report(new Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_SECONDARY_REQUEST)
		{
			$trophies=$this->entity->value('trophies');
			if ($trophies instanceof Report) return $trophies;
			$this->trophies=$trophies->values;
			$tasks=[];
			foreach ($this->trophies as $trophy)
			{
				$tasks[]=$this->pre_request($this->pre_request_from_trophies, $trophy);
			}
			if (empty($tasks)) return $this->advance_step();
			return $this->sign_report(new Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_ANALYZE_TROPHIES)
		{
			return $this->sign_report(new Report_impossible('no_analys_body'));
		}
	}
}

class Task_trophy_owner_satisfies extends Task_trophy_analyst
{
	public
		$blueprint,
		$level;
	
	public function apply_arguments()
	{
		$this->blueprint=reset($this->args);
		$this->level=next($this->args);
		if ($this->level===false) $this->level=1;
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_ANALYZE_TROPHIES)
		{
			$resolution=false;
			foreach ($this->trophies as $trophy)
			{
				if ($this->blueprint->equals($trophy->value('blueprint_entity')))
				{
					$resolution=$trophy->value('level')>=$this->level;
					break;
				}
			}
			return $this->sign_report(new Report_resolution($resolution));
		}
		else return parent::run_step();
	}
}

class Task_trophy_owner_benefits_from extends Task_trophy_owner_satisfies
{
	public
		$pre_request_from_blueprint=['trophy_type'];
	
	public function run_step()
	{
		if ($this->step===static::STEP_PRE_REQUEST)
		{
			$report=parent::run_step();
			if ($report instanceof Report_tasks)
			{
				$tasks=$report->tasks;
				$tasks[]=$this->pre_request($this->pre_request_from_blueprint, $this->blueprint);
				return $this->sign_report(new Report_tasks($tasks));
			}
			return $report;
		}
		elseif ($this->step===static::STEP_ANALYZE_TROPHIES)
		{
			$type=$this->blueprint->value('trophy_type');
			if ($type===TrophyBlueprint::TYPE_CUMULATIVE) return $this->sign_report(new Report_resolution(true));
			
			$report=parent::run_step();
			if ($report instanceof Report_resolution) $report->resolution=!$report->resolution;
			return $report;
		}
		else return parent::run_step();
	}
}

class Task_trophy_owner_receive extends Task_trophy_owner_benefits_from
{	
	const
		STEP_RECEIVE_NEW_TROPHY	=10,
		STEP_FINISH				=11,
		
		STEP_MODIFY_TROPHY		=20,
		STEP_FINISH2			=21,
		
		TROPHY_CLASS			='Trophy';
	
	public
		$for,
		$base_trophy;
	
	public function apply_arguments()
	{
		parent::apply_arguments();
		$this->for=next($this->args);
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_ANALYZE_TROPHIES)
		{
			foreach ($this->trophies as $trophy)
			{
				if ($this->blueprint->equals($trophy->value('blueprint_entity')))
				{
					$this->base_trophy=$trophy;
					return $this->advance_step(static::STEP_MODIFY_TROPHY);
				}
			}
			return $this->advance_step(static::STEP_RECEIVE_NEW_TROPHY);
		}
		elseif ($this->step===static::STEP_RECEIVE_NEW_TROPHY)
		{
			$trophy=$this->create_trophy();
			return $trophy->save();
		}
		elseif ($this->step===static::STEP_MODIFY_TROPHY)
		{
			// STUB: это каким-то образом должен делать сам чертёж трофея.
			$type=$this->blueprint->value('trophy_type');
			if ($type===TrophyBlueprint::TYPE_SINGLE) return $this->sign_report(new Report_success());
			elseif ($type===TrophyBlueprint::TYPE_CUMULATIVE)
				$this->base_trophy->set('level', $this->base_trophy->value('level')+$this->level);
			elseif ($type===TrophyBlueprint::TYPE_RATING)
			{
				if ($this->base_trophy->value('level')>=$this->level) return $this->sign_report(new Report_success());
				$this->base_trophy->set('level', $this->level);
				$this->sign_trophy($this->base_trophy);
			}
			else return $this->sign_report(new Report_impossible('bad_trophy_type'));
			return $this->base_trophy->save();
		}
		elseif (in_array([static::STEP_FINISH, static::STEP_FINISH2], $this->step))
		{
			return $this->sign_report(new Report_success());
		}
		else return parent::run_step();
	}
	
	public function create_trophy()
	{
		$class=static::TROPHY_CLASS;
		$trophy=$this->pool()->new_entity($class);
		$trophy->set('blueprint', $this->blueprint);
		$trophy->set('owner', $this->entity);
		$trophy->set('level', $this->level);
		$this->sign_trophy($trophy);
		return $trophy;
	}
	
	public function sign_trophy($trophy)
	{
		$trophy->set('date_received', time());
		if (!empty($this->for)) $trophy->set('for', $this->for);
	}
}

?>