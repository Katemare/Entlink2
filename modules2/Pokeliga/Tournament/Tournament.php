<?
namespace Pokeliga\Pokeliga;

class Tournament extends EntityType
{		
	const
		MIN_REGISTRATION_GAP=300, // 5 min
		MIN_VOTING_GAP=300,
		
		STATE_UNANNOUNCED=0,	// создан, не объявлен
		STATE_ANNOUNCED=1,		// объявлен, запись не начата
		STATE_REGISTRATION=2,	// запись начата
		STATE_TO_POPULATE=3,	// запись окончена, битвы не созданы
		STATE_TO_START=4,		// битвы созданы, ещё не начало
		STATE_CURRENT=5,		// битвы в разгаре
		STATE_FINISHED=6,		// битвы закончены, итоги не подведены
		STATE_RESOLVED=7,		// итоги подведены, награды не розданы и очки не подсчитаны
		STATE_AWARDED=8,		// всё подсчитано и роздано
		// некоторые из этих этапов могут отсутствовать: например, турнир может быть сразу объявлен, или может не иметь записи (только распорядитель добавляет записи), или старт создание боёво со стартом могут быть автоматическими...
		// ставки проходят в этапах STATE_REGISTRATION и STATE_TO_POPULATE.
		
		TYPE_POOL=0, // макротурнир, бои в котором на самом деле не проводятся, а просто набираются участники.
		TYPE_ELIMINATION=1,
		TYPE_ROUNDROBIN=2,
		
		ENTRY_OWNER_ONLY=0,
		ENTRY_USER_BASED=1,
		ENTRY_ANONYMOUS=2,
		ENTRY_SINGLE_ADOPT=3,
		
		JUDGE_BY_VOTES=0,
		
		BATTLE_SIMPLE=0;
		
	static
		$init=false,
		$module_slug='pokeliga',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'=>'Tournament_basic',
			'reg'=>'Tournament_registration',
			'flow'=>'Tournament_flow',
			'judge'=>'Tournament_judge',
			'guessing'=>'Tournament_guessing',
			'contribution'=>'Contribution_identity'
		],
		$variant_aspects=['flow'=>'Task_tournament_determine_flow_aspect', 'judge'=>'Task_tournament_determine_judge_aspect'],
		$flow_keywords=
		[
			Tournament::TYPE_POOL=>'pool',
			Tournament::TYPE_ELIMINATION=>'elimination',
			Tournament::TYPE_ROUNDROBIN=>'round_robin'
		],
		$judging_keywords=
		[
			Tournament::JUDGE_BY_VOTES=>'judge_by_votes'
		],
		$default_table='tournaments';
}

class Tournament_basic extends Aspect
{		
	static
		$common_model=
		[
			'announce_date'=>
			[
				'type'=>'timestamp'
			],
			'state'=>
			[
				'type'=>'enum',
				'default'=>Tournament::STATE_ANNOUNCED,
				'options'=>
				[
					Tournament::STATE_UNANNOUNCED,	Tournament::STATE_ANNOUNCED,	Tournament::STATE_REGISTRATION,
					Tournament::STATE_TO_POPULATE,	Tournament::STATE_TO_START,		Tournament::STATE_CURRENT,
					Tournament::STATE_FINISHED,		Tournament::STATE_RESOLVED,		Tournament::STATE_AWARDED
				]
			],
			'populate_time'=>
			[
				'type'=>'timestamp',
				'null'=>true,
				'default'=>null,
				'validators'=>['greater_or_equal_to_sibling'],
				'greater_or_equal'=>['registration_end'],
				'dependancies'=>['registration_end']
			],
			'battles_start'=>
			[
				'type'=>'timestamp',
				'validators'=>['greater_or_equal_to_sibling'],
				'greater_or_equal'=>['guessing_end', 'registration_end'],
				'dependancies'=>['guessing_end', 'registration_end']
			],
			
			'tournament_type'=>
			[
				// система создания боёв и определения победителя турнира.
				'type'=>'enum',
				'options'=>[Tournament::TYPE_POOL, Tournament::TYPE_ELIMINATION, Tournament::TYPE_ROUNDROBIN],
				'default'=>Tournament::TYPE_ELIMINATION
			],
			'entry_type'=>
			[
				// тип заявок - например, в команде ли, анонимно ли... WIP
				'type'=>'enum',
				'options'=>[Tournament::ENTRY_OWNER_ONLY, Tournament::ENTRY_USER_BASED, Tournament::ENTRY_ANONYMOUS, Tournament::ENTRY_SINGLE_ADOPT],
				'default'=>Tournament::ENTRY_OWNER_ONLY
			],
			'judge_type'=>
			[
				// система оперделения победителя боя.
				'type'=>'enum',
				'options'=>[Tournament::JUDGE_BY_VOTES],
				'default'=>Tournament::JUDGE_BY_VOTES
			],
			'battle_type'=>
			[
				// система представления боя, например, одинарные битвы или двойные и описание арены. WIP
				'type'=>'enum',
				'options'=>[Tournament::BATTLE_SIMPLE],
				'default'=>Tournament::BATTLE_SIMPLE
			],
			
			'paused'=>
			[
				'type'=>'bool',
				'keeper'=>false,
				'pre_request'=>['paused_at'],
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'call'=>['_aspect', 'basic', 'paused']
			],
			'paused_at'=>
			[
				'type'=>'timestamp',
				'null'=>true,
				'default'=>null
			],
			'paused_reason'=>
			[
				'type'=>'text',
				'null'=>true,
				'default'=>null
			],
			'auto_pause'=>
			[
				'type'=>'array',
				'null'=>true,
				'default'=>null
				// массив в формате: [ код этапа (state) => 'Причина', код этапа... ]
			]
		],
		$tasks=
		[
			'cron'=>'Task_tournament_cron'
		],
		$templates=
		[
		],
		$init=false,
		$basic=true,
		$default_table='tournaments';
		
	public function profile_url()
	{
		return Router()->url('adopts/event.php?event='.$this->entity->value('id'));
	}
	
	public function edit_url()
	{
		return Router()->url('adopts/edit_event.php?event='.$this->entity->value('id'));
	}
	
	public function paused()
	{
		$paused_at=$this->entity->value('paused_at');
		if ($paused_at instanceof \Report_impossible) return $paused_at;
		return $paused_at>0;
	}
	
	public function status()
	{
		$time=time();
		if ($time<$this->entity->value('announce_date')) return GameEvent::STATUS_UNANNOUNCED;
		if ($time>=$this->entity->value('finish_date')) return GameEvent::STATUS_FINISHED;
		if ( (($registration_start=$this->entity->value('registration_start'))!==null) && ($time>=$registration_start) && ($time<$this->entity->value('registration_end')) ) return GameEvent::STATUS_REGISTRATION;
		if ( (($guessing_start=$this->entity->value('guessing_start'))!==null) && ($time>=$guessing_start) && ($time<$this->entity->value('guessing_end')) )return GameEvent::STATUS_VOTING;
		return GameEvent::STATUS_ANNOUNCED; // периоды после объявления, до конца, не занятые регистрацией и голосованием.
	}
}

trait Task_tournament_pauser // extends Task_for_entity
{
	public function advance_state($new_state)
	{
		$this->entity->set('state', $new_state);
		$auto_pause=$this->entity->value('auto_pause');
		if (array_key_exists($auto_pause, $new_state)) $this->pause($auto_pause[$new_state]);
	}
	
	public function pause($reason)
	{
		$this->entity->set('paused_at', time());
		$this->entity->set('paused_reason', $reason);
	}
}

class Task_tournament_cron extends Task_for_entity
{
	use Task_steps, Task_tournament_pauser;
	
	const
		STEP_PRE_REQUEST=0,
		STEP_JUMP_BY_STATE=1,
		
		STEP_MANAGE_UNANNOUNCED=2,	// запрашивает дату объявления.
		STEP_RESOLVE_UNANNOUNCED=3, // если требуется, объявляет.
		
		STEP_MANAGE_ANNOUNCED=4,	// запрашивает начало записи.
		STEP_RESOLVE_ANNOUNCED=5,	// если требуется, открывает запись.
		
		STEP_MANAGE_REGISTRATION=6,	// запрашивает конц записи.
		STEP_RESOLVE_REGISTRATION=7, // если требуется, закрывает запись.
		
		STEP_MANAGE_TO_POPULATE=8,	// запрашивает дату создания боёв.
		STEP_CHECK_POPULATE_TIME=9, // запрашивает возможность автоматически распределить бои: получает задачу либо отказ.
		STEP_RESOLVE_TO_POPULATE=9, // смотрит результат задачи.
		
		STEP_MANAGE_TO_START=10,	// запрашивает возможность начать бои: получает задачу либо отказ.
		STEP_RESOLVE_TO_START=11,	// смотрит результат задачи.
		
		STEP_MANAGE_CURRENT=12,		// запрашивает возможность продвинуть турнир: получает задачу либо отказ.
		STEP_RESOLVE_CURRENT=13,	// смотрит результат задачи.
		
		STEP_MANAGE_FINISHED=14,	// запрашивает возможность распределить места законченного турнира: получает задачу либо отказ.
		STEP_RESOLVE_FINISHED=15,	// смотрит результат задачи.
		
		STEP_MANAGE_RESOLVED=16,	// запрашивает возможность раздать награды: получает задачу либо отказ.
		STEP_RESOLVE_RESOLVED=17,	// смотрит результат задачи.
		
		STEP_FINISH=18;				// турнир в состоянии AWARDED не нуждается в обработке.
		
		// если на любом этапе возникает ошибка или если этап невозможно разрешить автоматически, турнир переводится в режим паузы до вмешательства распорядителя.
	
	static
		$step_by_state=
		[
			Tournament::STATE_UNANNOUNCED	=>Task_tournament_cron::STEP_MANAGE_UNANNOUNCED,
			Tournament::STATE_ANNOUNCED		=>Task_tournament_cron::STEP_MANAGE_ANNOUNCED,
			Tournament::STATE_REGISTRATION	=>Task_tournament_cron::STEP_MANAGE_REGISTRATION,
			Tournament::STATE_TO_POPULATE	=>Task_tournament_cron::STEP_MANAGE_TO_POPULATE,
			Tournament::STATE_TO_START		=>Task_tournament_cron::STEP_MANAGE_TO_START,
			Tournament::STATE_CURRENT		=>Task_tournament_cron::STEP_MANAGE_CURRENT,
			Tournament::STATE_FINISHED		=>Task_tournament_cron::STEP_MANAGE_FINISHED,
			Tournament::STATE_RESOLVED		=>Task_tournament_cron::STEP_MANAGE_RESOLVED,
			Tournament::STATE_AWARDED		=>Task_tournament_cron::STEP_FINISH
		];
	
	public
		$state,
		$task;
	
	public function run_step()
	{
		if ($this->step===static::STEP_PRE_REQUEST)
		{
			$task=$this->pre_request('state', 'paused', 'auto_pause');
			return $this->sign_report(new \Report_task($task));
		}
		elseif ($this->step===static::STEP_JUMP_BY_STATE)
		{
			$paused=$this->entity->value('paused');
			if ($paused===true) return $this->sign_report(new \Report_success());
			
			$state=$this->entity->value('state');
			if ($state instanceof \Report_impossible) return $state;
			if (!array_key_exists($state, static::$step_by_state)) return $this->sign_report(new \Report_impossible('unknown_tournament_state'));
			$step=static::$step_by_state[$state];
			return $this->advance_step($step);
		}
		
		elseif ($this->step===static::STEP_MANAGE_UNANNOUNCED)
		{
			$report=$this->entity->request('announce_date');
			if ($report instanceof \Report_tasks) return $report;
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_RESOLVE_UNANNOUNCED)
		{
			$date=$this->entity->value('announce_date');
			if ($date>time()) return $this->sign_report(new \Report_success());
			
			$this->entity->set('state', Tournament::STATE_ANNOUNCED);
			return $this->advance_step();
		}
		
		elseif ($this->step===static::STEP_MANAGE_ANNOUNCED)
		{
			$report=$this->entity->request('registration_start');
			if ($report instanceof \Report_tasks) return $report;
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_RESOLVE_ANNOUNCED)
		{
			$date=$this->entity->value('registration_start');
			if ($date>time()) return $this->sign_report(new \Report_success());
			
			$this->entity->set('state', Tournament::STATE_REGISTRATION);
			return $this->advance_step();
		}
		
		elseif ($this->step===static::STEP_MANAGE_REGISTRATION)
		{
			$report=$this->entity->request('registration_end');
			if ($report instanceof \Report_tasks) return $report;
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_RESOLVE_REGISTRATION)
		{
			$date=$this->entity->value('registration_end');
			if ($date>time()) return $this->sign_report(new \Report_success());
			
			$this->entity->set('state', Tournament::STATE_TO_POPULATE);
			return $this->advance_step();
		}
		
		elseif ($this->step===static::STEP_MANAGE_TO_POPULATE)
		{
			$report=$this->entity->request('populate_time');
			if ($report instanceof \Report_task) return $report;
			return $this->advance_tep();
		}
		elseif ($this->step===static::STEP_CHECK_POPULATE_TIME)
		{
			$date=$this->entity->value('populate_time');
			if ($date instanceof \Report_impossible) return $date;
			if ($date>time()) return $this->sign_report(new \Report_success());

			$report=$this->entity->task_request('populate');
			if ($report instanceof \Report_task)
			{
				$this->task=$report->task;
				return $report;
			}
			else return $this->sign_report(new \Report_impossible('bad_populate_task'));
		}
		
		elseif ( ($this->step===static::STEP_MANAGE_TO_START) || ($this->step===static::STEP_MANAGE_CURRENT) )
		{
			$report=$this->entity->task_request('advance'); // начинает турнир и продолжает его одна и та же задача, разница просто в количестве прошедших боёв.
			if ($report instanceof \Report_task)
			{
				$this->task=$report->task;
				return $report;
			}
			else return $this->sign_report(new \Report_impossible('bad_advance_task'));
		}
		
		elseif (in_array($this->step, [static::STEP_RESOLVE_TO_POPULATE, static::STEP_RESOLVE_TO_START, static::STEP_RESOLVE_CURRENT, static::STEP_RESOLVE_FINISHED, static::STEP_RESOLVE_RESOLVED], true))
		{
			if ($this->task->failed()) return $this->task->report();
			return $this->advance_step(static::STEP_JUMP_BY_STATE);
		}
	}
}

/*
Участники попадают в турнир следующими способами:

1. Пользовательская запись.
2. Занесение "ботов" распорядетелем.
3. Занесение старшим турниром.

Голосование длится после окончания записидо создания боёв (бои создаются по пересечении порога guessing_end)
*/

class Tournament_registration extends Aspect
{
	static
		$common_model=
		[
			'registration_start'=>
			[
				'type'=>'timestamp',
				'validators'=>['greater_or_equal_to_sibling'],
				'greater_or_equal'=>['announce_date'],
				'dependancies'=>['announce_date']
			],
			'registration_end'=>
			[
				'type'=>'timestamp',
				'validators'=>['greater_than_sibling'],
				'greater_than'=>['registration_start'],
				'greater_gap'=>Tournament::MIN_REGISTRATION_GAP,
				'dependancies'=>['registration_start']
			],
			'entries_per_user'=>
			[
				'type'=>'unsigned_int',
				'max'=>100,
				'default'=>1
				// значение 0 означает, что записывать может только распорядитеь, ботов.
			],
			'applicants'=>
			[
				'type'=>'linkset',
				'id_group'=>'TournamentEntry',
				'select'=>'backlinked',
				'backlink_field'=>'tournament'
			],
			'considerable_applicants'=>
			[
				// если необходимо ограничить число заявок от одного участника, то подсчитывать нужно именно в этом списке.
				'type'=>'linkset',
				'id_group'=>'TournamentEntry',
				'select'=>'backlinked',
				'additional_conditions'=>[ ['field'=>'reviewed', 'op'=>'!=', 'value'=>TournamentEntry::REVIEW_DENIED] ],
				'backlink_field'=>'tournament'
			]
		],
		$templates=
		[
			'reg_form'=>true
		],
		$tasks=
		[
		],
		$right=
		[
			'to_register'=>['pre_request'=>['entry_type', 'considerable_applicants'], 'task'=>'Task_calc_tournament_right_register']
		],
		$init=false,
		$basic=false,
		$default_table='tournaments';
		
	public function has_right($right, $user)
	{
		if ($right==='to_register')
		{
			if ($this->entity->value('state')!==Tournament::STATE_REGISTRATION) return EntityType::RIGHT_FINAL_DENY;
			if (empty($user)) return EntityType::RIGHT_FINAL_DENY;
			return EntityType::RIGHT_WEAK_ALLOW;
		}
	}
}

class Task_calc_tournament_right_register extends Task_calc_aspect_right
{
	use Task_steps
	{
		Task_steps::progress as steps_progress;
	}
	
	const
		STEP_BASIC_RIGHT=0,
		STEP_REQUEST_USERS=1,
		STEP_COUNT_ENTRIES=2,
		
		MAX_ENTRIES=1; // STUB! далее должно указываться в настройках турнира.
	
	public
		$applicants,
		$chars;
	
	public function progress()
	{
		if ($this->requested===true) $this->resolve();
		elseif (empty($this->user)) $this->finish_with_resolution(EntityType::RIGHT_FINAL_DENY);
		else parent::progress();
	}
	
	public function resolve()
	{
		$this->steps_progress();
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_BASIC_RIGHT)
		{
			if ($this->entity->value('state')!==Tournament::STATE_REGISTRATION) return $this->sign_report(new \Report_resolution(EntityType::RIGHT_FINAL_DENY));
			
			$tasks=[];
			$applicants=$this->entity->value('considerable_applicants');
			if ($applicants instanceof \Report_impossible) return $applicants;
			$this->applicants=$applicants->values;
			
			$tasks=[];
			foreach ($this->applicants as $entry)
			{
				$report=$entry->request('char');
				if ($report instanceof \Report_impossible) return $report;
				elseif ($report instanceof \Report_tasks) $tasks=array_merge($tasks, $report->tasks);
			}
			
			if (empty($tasks)) return $this->advance_step();
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_REQUEST_USERS)
		{
			$tasks=[];
			$this->chars=[];
			foreach ($this->applicants as $entry)
			{
				$char=$entry->value('char');
				if ($char instanceof \Report_impossible) return $char;
				
				$report=$char->request('owner');
				if ($report instanceof \Report_impossible) return $report;
				elseif ($report instanceof \Report_tasks) $tasks=array_merge($tasks, $report->tasks);
				
				$this->chars[]=$char;
			}
			
			if (empty($tasks)) return $this->advance_step();
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_COUNT_ENTRIES)
		{
			$user_entries=0;
			foreach ($this->chars as $char)
			{
				$owner=$char->value('owner');
				if ($owner instanceof \Report_impossible) return $owner;
				if ($this->user->equals($owner))
				{
					$user_entries++;
					if ($user_entries>=static::MAX_ENTRIES) return $this->sign_report(new \Report_resolution(EntityType::RIGHT_WEAK_DENY));
				}
			}
			return $this->sign_report(new \Report_resolution(EntityType::RIGHT_WEAK_ALLOW));
		}
	}
}

abstract class Tournament_flow extends Aspect
{
	static
		$common_model=
		[
			'plan'=>
			[
				'type'=>'array',
				'pre_request'=>['entries', 'basenum'],
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'call'=>['_aspect', 'flow', 'plan']
			],
			
			'battle_timespan'=>
			[
				'type'=>'timespan',
				'default'=>86400, // day
				'min'=>60 // minute
			],
			'battles_at_once'=>
			[
				'type'=>'unsigned_int',
				'min'=>1,
				'max'=>8,
				'default'=>2
			],
			'basenum'=>
			[
				'type'=>'unsigned_int',
				'min'=>2,
				'max'=>32,
				'default'=>8
			],
			'entries'=>
			[
				'type'=>'linkset',
				'id_group'=>'TournamentEntry',
				'select'=>'backlinked',
				'additional_conditions'=>['reviewed'=>TournamentEntry::REVIEW_APPROVED],
				'backlink_field'=>'tournament'
			],
			
			'battles'=>
			[
				'type'=>'linkset',
				'id_group'=>'TournamentBattle',
				'select'=>'backlinked',
				'backlink_field'=>'tournament'
			],
			'current_battles'=>
			[
				'type'=>'linkset',
				'id_group'=>'TournamentBattle',
				'null'=>true,
				'default'=>null,
				'select'=>'tournament_battles',
				'state'=>TournamentBattle::STATE_CURRENT
			],
			'ladder'=>
			[
				'type'=>'linkset',
				'id_group'=>'TournamentEntry',
				'select'=>'backlinked',
				'backlink_field'=>'tournament',
				'order'=>'place'
			],
			'sub_tournaments'=>
			[
				'type'=>'linkset',
				'id_group'=>'Tournament',
				'null'=>true,
				'default'=>null,
			],
			'super_tournament'=>
			[
				'type'=>'entity',
				'id_group'=>'Tournament',
				'null'=>true,
				'default'=>null
			]
		],
		$rights=
		[
			'to_populate'=>['pre_request'=>['state'], 'request_user_rights'=>['admin']],
			'to_advance'=>['pre_request'=>['state'], 'request_user_rights'=>['admin']],
			'to_resolve'=>['pre_request'=>['state'], 'request_user_rights'=>['admin']]
		],
		$templates=
		[
		],
		$tasks=
		[
			'populate'=>true,
			'resolve'=>true,
			'advance'=>true
		],
		$init=false,
		$basic=false,
		$default_table='tournaments';
		
	public abstract function plan();
	
	public function has_right($right, $user)
	{
		if ($right==='to_populate')
		{
			if ($this->entity->value('state')!==Tournament::STATE_TO_POPULATE) return EntityType::RIGHT_FINAL_DENY;
			if ( (!Engine()->console) && (!$user->value('admin')) ) return EntityType::RIGHT_FINAL_DENY;
			return EntityType::RIGHT_WEAK_ALLOW;
		}
		elseif ($right==='to_advance')
		{
			if (!in_array($this->entity->value('state'), [Tournament::STATE_TO_START, Tournament::STATE_CURRENT], true)) return EntityType::RIGHT_FINAL_DENY;
			if ( (!Engine()->console) && (!$user->value('admin')) ) return EntityType::RIGHT_FINAL_DENY;
			return EntityType::RIGHT_WEAK_ALLOW;
		}
		elseif ($right==='to_resolve')
		{
			if ($this->entity->value('state')!==Tournament::STATE_FINISHED) return EntityType::RIGHT_FINAL_DENY;
			if ( (!Engine()->console) && (!$user->value('admin')) ) return EntityType::RIGHT_FINAL_DENY;
			return EntityType::RIGHT_WEAK_ALLOW;
		}
	}
}

class Task_tournament_determine_flow_aspect extends Task_determine_aspect
{
	const
		NO_VALUE_ERROR='no_type',
		BAD_VALUE_ERROR='bad_type',
		VALUE_CODE='tournament_type',
		ASPECTS_ARRAY='flow_keywords';
		
	public function progress()
	{
		$type=$this->entity->request(static::VALUE_CODE);
		if ($type instanceof \Report_tasks) $type->register_dependancies_for($this);
		elseif ($type instanceof \Report_impossible) $this->impossible(static::NO_VALUE_ERROR);
		elseif ($type instanceof \Report_resolution)
		{
			$type=$type->resolution;
			$array=static::ASPECTS_ARRAY;
			if (array_key_exists($type, Tournament::$$array))
			{
				$array=Tournament::$$array;
				$this->finish_with_resolution('Tournament_'.$array[$type]);
			}
			else $this->impossible(static::BAD_VALUE_ERROR);
		}
	}
}

class Task_tournament_determine_judge_aspect extends Task_tournament_determine_flow_aspect
{
	const
		VALUE_CODE='judging_type',
		ASPECTS_ARRAY='judging_keywords';
}

abstract class Tournament_judge extends Aspect
{
	static
		$templates=
		[
			'judging'=>true // сводка предстоящего, текущего или завершившегося разрешения битвы.
		],
		$tasks=
		[
			'judge'=>true // собственно разрешение битвы.
		],
		$init=false,
		$basic=false,
		$default_table='tournaments';
}

class Tournament_guessing extends Aspect
{
	static
		$common_model=
		[
			'guesses'=>
			[
				'type'=>'linkset',
				'id_group'=>'TournamentGuess',
				'select'=>'backlinked',
				'backlink_field'=>'tournament'
			],
			'guess_multiplier'=>
			[
				'type'=>'unsigned_number',
				'default'=>1
			]
		],
		$templates=
		[
			'guess_form'=>true
		],
		$tasks=
		[
		],
		$init=false,
		$basic=false,
		$default_table='tournaments_guesses';
		
		public function make_complex_template($name, $line=[], &$do_setup=true)
		{
			if ($name==='guess_form')
			{
				$form=Form_tournament_guess::create_for_display(['entity_id'=>$this->entity->db_id]);
				$template=$form->main_template($line);
				$do_setup=false;
				return $template;
			}
		}
}
?>