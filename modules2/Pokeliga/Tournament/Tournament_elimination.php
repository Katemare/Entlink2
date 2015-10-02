<?
namespace Pokeliga\Pokeliga;

class Tournament_elimination extends Tournament_flow
{
	static
		$tasks=
		[
			'start'=>'Task_tournament_elimination_start',
			'finish'=>'Task_tournament_elimination_finish',
			'advance'=>'Task_tournament_elimination_advance'
		];
		
	public function plan()
	{
		$entries=$this->entity->value('entries');
		if ($entries instanceof \Report_impossible) return $entries;
		$entries=$entries->values;
		
		$basenum=$this->entity->value('basenum');
		if ($basenum instanceof \Report_impossible) return $basenum;
		
		if (count($entries)!==$basenum) return $this->sign_report(new \Report_impossible('bad_entries_count'));
		if (log($basenum, 2) % 1 !== 0) return $this->sign_report(new \Report_impossible('unimplemented_elimination_count'));
		
		$remaining=$basenum;
		$plan=['stages'=>[], 'battles'=>[]];
		$battles=[];
		$next_battle_index=0;
		$stage=-1;
		while ($remaining>1)
		{
			$to_eliminate=(int)($remaining/2); // наверное, возможны сценарии, когда в битве исключается другое число участников - например, 2/3 или 3/4 или даже случайное число. но пока это не применяется, и я пока не знаю, как сделать - видимо, нужно консультироваться с данными о типе битвы.
			$plan['stages'][++$stage]=['battles'=>$to_eliminate, 'battlers'=>$to_eliminate*2, 'carry'=>$remaining-$to_eliminate*2, 'first_battle_index'=>$next_battle_index];
			$max_stage_index=$next_battle_index+$to_eliminate-1;
			for ($battle_ord=0; $battle_ord<$to_eliminate; $battle_ord++)
			{
				$battle=['stage'=>$stage];
				if ($stage===0) $battle['chars']=[$battle_ord*2, $battle_ord*2+1];
				else $battle['chars_source']=[$plan['stages'][$stage-1]['first_battle_index']+$battle_ord*2, $plan['stages'][$stage-1]['first_battle_index']+$battle_ord*2+1];
				
				$index=$next_battle_index+$battle_ord;
				$plan['battles'][$index]=$battle;
				
				if ($to_eliminate==1)
				{
					$plan['finals']=$index;
					
					// пользуемся тем, что в php массив - значение, а не объект, так что приравнивание к массиву копирует.
					$battle['eliminated']=true;
					$index++;
					$plan['battles'][$index]=$battle;
					$plan['third_place_battle']=$index;
				}
			}
			$next_battle_index+=$battle_ord;
			$remaining-=$to_eliminate;
		}
		$plan['max_stage']=$stage;
		
		return $plan;
	}
}

class Task_tournament_elimination_populate extends Task_for_entity
{
	use Task_steps;
	
	const
		STEP_REQUEST=0,
		STEP_CREATE_BATTLES=1,
		STEP_ASSIGN_CHARS=2,
		STEP_START_BATTLES=3,
		STEP_FINISH=4;
	
	public
		$battles=[];
	
	public function run_step()
	{
		if ($this->step===static::STEP_REQUEST)
		{
			return $this->sign_report(new \Report_task($this->pre_request(['plan', 'entries', 'contributor', 'title'])));
		}
		elseif ($this->step===static::STEP_CREATE_BATTLES)
		{
			$plan=$this->entity->value('plan');
			if ($plan instanceof \Report_impossible) return $plan;			
			$contributor=$this->entity->value('contributor');
			if ($contributor instanceof \Report_impossible) return $contributor;
			$tournament_title=$this->entity->value('title');
			if ($tournament_title instanceof \Report_impossible) return $tournament_title;
			
			$tasks=[];
			$time=time();
			$pool=$this->pool();
			
			for ($x=0; $x<$plan['stages'][0]['battles']; $x++)
			{
				$battle=$pool->new_entity('TournamentBattle');
				$battle->set('tournament', $this->entity);
				$battle->set('index', $x);
				$battle->set('title', $tournament_title.' № '.($x+1)); // STUB
				$battle->set('contribution_date', $time);
				$battle->set('contributor', $contributor);
				$task=$battle->save()->task;
				$tasks[]=$task;
				$this->battles[$x]=$battle;
			}
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_ASSIGN_CHARS)
		{
			$entries=$this->entity->value('entries');
			if ($entries instanceof \Report_impossible) return $entries;
			$entries=$entries->values;
			shuffle($entries);
			
			$pool=$this->pool();
			
			foreach ($this->battles as $index=>$battle)
			{
				for ($side=0; $side<2; $side++)
				{
					$battle_entry=$pool->new_entity('TournamentEntry');
					$battle_entry->set('battle', $battle->db_id);
					$battle_entry->set('char', array_shift($entries));
					$battle_entry->set('ord', $side);
					$task=$battle_entry->save()->task;
					$tasks[]=$task;
				}
			}
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_START_BATTLES)
		{
			return $this->advance_step();
			// пусть запускается по таймеру или вручную.
			// return $this->entity->task_request('advance');
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			return $this->sign_report(new \Report_success());
		}
	}
}

class Task_tournament_elimination_start extends Task_for_entity
{
	use Task_steps;
	
	const
		STEP_REQUEST=0,
		STEP_FINISH_CURRENT=1,
		STEP_REQUEST_BATTLES=2,
		STEP_CHECK_PLAN=3,
		
		STEP_SPAWN_STAGE=10,
		STEP_ASSIGN_CHARS=11,
		
		STEP_CONTINUE_STAGE=12,
		STEP_FINISH_TASK=13,
		
		STEP_FINISH_TOURNAMENT=30,
		STEP_FINISH_TASK2=31;
	
	public
		$battles,
		$to_start,
		$plan,
		$stage_to_spawn;
	
	public function run_step()
	{
		if ($this->step===static::STEP_REQUEST)
		{
			return $this->sign_report(new \Report_task($this->pre_request(['plan', 'battles', 'current_battles', 'battles_at_once', 'contributor'])));
		}
		elseif ($this->step===static::STEP_FINISH_CURRENT)
		{
			$current_battles=$this->entity->value('current_battles');
			if ($current_battles instanceof \Report_impossible) return $current_battles;
			$current_battles=$current_battles->values;
			if (count($current_battles)==0) return $this->advance_step();
			
			$tasks=[];
			foreach ($current_battles as $battle)
			{
				$task=$this->entity->task_request('judge', $battle);
				$taks[]=$task;
			}
			
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_REQUEST_BATTLES)
		{
			$battles=$this->entity->value('battles');
			if ($battles instanceof \Report_impossible) return $battles;
			$this->battles=$battles->values;
			
			$tasks=[];
			foreach ($this->battles as $battle)
			{
				$task=$this->pre_request(['index', 'state', 'entries'], $battle);
				$tasks[]=$task;
			}
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_CHECK_PLAN)
		{
			$plan=$this->entity->value('plan');
			if ($plan instanceof \Report_impossible) return $plan;
			$this->plan=$plan;
			$battles_at_once=$this->entity->value('battles_at_once');
			if ($battles_at_once instanceof \Report_impossible) return $battles_at_once;
			
			$current_stage=null;
			$last_existing_stage=null;
			$battles_by_index=[];
			foreach ($this->battles as $battle)
			{
				$index=$battle->value('index');
				$battles_by_index[$index]=$battle;
				if ( ($last_existing_stage===null) || ($this->plan['battles'][$index]['stage']>$last_existing_stage) ) $last_existing_stage=$this->plan['battles'][$index]['stage'];
				
				$state=$battle->value('state');
				if (!in_array($state, [TournamentBattle::STATE_ANNOUNCED, TournamentBattle::STATE_UNANNOUNCED]))
				{
					// рассматриваются только текущие (предполодительно законченные) и прошедшие битвы.
					if ( ($current_stage===null) || ($this->plan['battles'][$index]['stage']<$current_stage) ) $current_stage=$this->plan['battles'][$index]['stage'];
				}
			}
			ksort($battles_by_index);
			$this->battles=$battles_by_index;
			
			if ($current_stage===null) $current_stage=0; // если нет текущих и прошедших битв, то турнир только начался.
			
			$first_battle=$this->plan['stages'][$current_stage]['first_battle_index'];
			$last_battle=$first_battle+$this->plan['stages'][$current_stage]['battles']-1;
			$this->to_start=[];
			for ($x=$first_battle; $x<=$last_battle; $x++)
			{
				$battle=$this->battles[$x];
				$state=$battle->value('state');
				if (in_array($state, [TournmentBattle::STATE_ANNOUNCED, TournmentBattle::STATE_UNANNOUNCED])) $this->to_start[]=$battle;
				if (count($this->to_start)>=$battles_at_once) break;
			}
			
			if ( (empty($this->to_start)) && ($last_existing_stage<$this->plan['max_stage']) )
			{
				$this->stage_to_spawn=$last_existing_stage+1;
				return $this->advance_step(static::STEP_SPAWN_STAGE);
			}
			elseif (empty($this->to_start)) return $this->advance_step(static::STEP_FINISH_TOURNAMENT);
			else return $this->advance_step(static::STEP_CONTINUE_STAGE);
		}
		elseif ($this->step===static::STEP_SPAWN_STAGE)
		{
			$contributor=$this->entity->value('contributor');
			if ($contributor instanceof \Report_impossible) return $contributor;
			$tournament_title=$this->entity->value('title');
			if ($tournament_title instanceof \Report_impossible) return $tournament_title;
			$battles_at_once=$this->entity->value('battles_at_once');
			if ($battles_at_once instanceof \Report_impossible) return $battles_at_once;
			$time=time();
		
			$first_battle=$this->plan['stages'][$this->stage_to_spawn]['first_battle_index'];
			$last_battle=$first_battle+$this->plan['stages'][$this->stage_to_spawn]['battles']-1;
			$tasks=[];
			for ($x=$first_battle; $x<=$last_battle; $x++)
			{
				$new_battle=$this->pool()->new_entity('TournamentBattle');
				$new_battle->set('tournament', $this->entity->db_id);
				$new_battle->set('index', $x);
				$new_battle->set('title', $tournament_title.' № '.($x+1)); // STUB
				$new_battle->set('contribution_date', $time);
				$new_battle->set('contributor', $contributor);
				$this->battles[$x]=$new_battle;
				$task=$new_battle->save()->task;
				$tasks[]=$task;
				
				if (count($this->to_start)<$battles_at_once) $this->to_start[]=$new_battle;
			}
			
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_ASSIGN_CHARS)
		{
			$first_battle=$this->plan['stages'][$this->stage_to_spawn]['first_battle_index'];
			$last_battle=$first_battle+$this->plan['stages'][$this->stage_to_spawn]['battles']-1;
			$tasks=[];
			for ($x=$first_battle; $x<=$last_battle; $x++)
			{
				$target_battle=$this->battles[$x];
				foreach ($this->plan['battles'][$x]['chars_source'] as $side=>$source_index)
				{
					$source_battle=$this->battles[$source_index];
					$entries=$source_battle->value('entries');
					$first_place=[];
					foreach ($entries->values as $entry)
					{
						if ($entry->value('place')!==1) continue;

						$new_entry=$this->pool()->new_entity('TournamentEntry');
						$new_entry->set('battle', $target_battle->db_id);
						$new_entry->set('char', $entry->value('char'));
						$new_entry->set('ord', $side);
						$task=$new_entry->save()->task;
						$tasks[]=$task;
					}
				}
			}
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_CONTINUE_STAGE)
		{
			$tasks=[];
			foreach ($this->to_start as $battle)
			{
				$battle->set('state', TournamentBattle::STATE_CURRENT);
				$task=$battle->save()->task;
				$tasks[]=$task;
			}
			$task=$new_entry->save()->task;
			$tasks[]=$task;
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_FINISH_TOURNAMENT)
		{
			$entries=$finals->value('entries');
			$places=[ 'by_char'=>[], 'by_place'=>[] ];
			$reverse_battles=$this->battles;
			krsort($reverse_battles);
			foreach ($reverse_battles as $index=>$battle)
			{
				$entries=$battle->value('entries');
				foreach ($entries->values as $entry)
				{
					if (array_key_exists($entry->value('char'), $places['by_char'])) continue;
					
				}
			}
		}
		elseif ( ($this->step===static::STEP_FINISH_TASK) || ($this->step===static::STEP_FINISH_TASK2) )
		{
			return $this->sign_report(new \Report_success());
		}
	}
}
?>