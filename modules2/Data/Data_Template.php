<?

// применяется по необходимости при обращении Valie::template(), если содержимое ещё не готово.

class Template_value_delay extends Template
{
	public
		$name,
		$value,
		$final=null;
		
	public static function for_value($value, $name, $line=[])
	{
		$template=static::with_line($line);
		$template->setup($value);
		$template->name=$name;
		return $template;
	}
	
	public function setup($value)
	{
		$this->value=$value;
	}
	
	public function progress()
	{
		if ($this->final!==null)
		{
			$report=$this->final->report();
			if ($report instanceof Report_impossible) $this->impossible('failed_content');
			elseif ($report instanceof Report_resolution)
			{
				$this->resolution=$report->resolution;
				$this->finish();
			}
			else die ('BAD FINAL TEMPLATE');
			return;
		}
		elseif ( ($this->value->has_state(Value::STATE_FAILED)) || ($this->value->has_state(Value::STATE_FILLED)) ) $this->process_ready_value();
		elseif ($this->value->has_state(Value::STATE_FILLING)) $this->register_dependancy($this->value->filler_task);
		else $this->value->fill();
	}
	
	public function process_ready_value()
	{
		if ($this->value->has_state(Value::STATE_FILLED)) $template=$this->value->template_for_filled($this->name, $this->line);
		elseif ($this->value->has_state(Value::STATE_FAILED)) $template=$this->value->template_for_failed($this->name, $this->line);
		else die ('VALUE NOT READY');
		
		$this->setup_subtemplate($template);
		
		if ($template instanceof Task)
		{
			$this->register_dependancy($template);
			$this->final=$template;
		}
		elseif ( ($template instanceof Report_impossible) || ($template===null)) $this->impossible('invalid_template');
		elseif ($template instanceof Report) die ('BAD READY TEMPLATE');
		else
		{
			$this->resolution=$template;
			$this->finish();
		}
	}
}

// FIX: здесь во многих местах упоминается Select_filter, потому это должно быть сделано изящнее.
class Template_found_options extends Template_field_select_searchable
{
	const
		RESULT_OPTION_DB_KEY='form.search_result_option',
		NEXT_PAGE_OPTION_DB_KEY='form.search_next_page_option',
		
		STEP_INIT=-2,
		STEP_GET_SEARCH_SELECT=-2,
		STEP_REQUEST_COUNT=-1; // вместо добавления опций в общую часть страницы.

	public
		$db_key='form.found_options',
		$search,
		$base_value,	// значение, заказавшее поиск.
		$range_select,	// выборщик, задающий диапазон.
		$search_select,	// выборщик, отвечающий за все найденные результаты.
		$page_select,
		$search_limit=30,
		$search_order='id',
		$found_elements=
		[
			'result_option',
			'next_page_option',
			'count_found',
			'search'
		],
		$count, $is_paged=null;
	
	public static function for_search($search, $value, $range_select=null, $line=[])
	{
		$template=static::with_line($line);
		$template->base_value=$value;
		$template->value=$value;
		$template->search=$search;
		$template->range_select=$range_select;
		return $template;
	}
	
	public function search_order()
	{
		if (empty($this->search_select)) return $this->search_order;
		if ($this->search_select->in_value_model('order')) return $this->search_select->value_model_now('order');
		return $this->search_order;
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_GET_SEARCH_SELECT)
		{
			// есть несколько ситуаций: дан или не дан поиск; используется или не используется ограничение диапазона; возможно ли представить это ограничение в виде единственного запроса.
			if ($this->search===null) // поисковый запрос не используется, просто перечислить все возможные опции, начиная с первой.
			{
				if (empty($this->range_select)) $this->search_select=Select_all::from_model($this->base_value->value_model()); // диапазон не задан, просто выбрать все.
				else $this->search_select=$this->range_select; // диапазон задан, показать его.
				return $this->advance_step();
			}
			
			if (empty($this->range_select)) // поисковый запрос есть, ограничения диапазона нет.
			{
				$id_group=$this->base_value->value_model_now('id_group');
				$this->search_select=$id_group::select_by_search($this->search); // обращаемся к типу сущности, задаём поиск без диапазона.
			}
			elseif ( (empty($this->range_select->query_convertable)) && (!($this->range_select instanceof Select_filter)) ) // диапазон есть, и он не может быть сведён до запроса.
			{
				return $this->sign_report(new Report_task($this->range_select)); // значит, сначала нужно его выполнить и получить айди.
			}
			
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_REQUEST_COUNT)
		{
			if (empty($this->search_select)) // если поисковый выборщик ещё не задан, то есть и поиск, и поисковый диапазон.
			{
				if ( (empty($this->range_select->query_convertable)) && (!($this->range_select instanceof Select_filter)) ) // если диапазон не может быть сведён к запросу, то к данному моменту он должен быть уже завершён.
				{
					if ($this->range_select->failed()) return $this->range_select->report();
					$ids=$this->range_select->resolution->ids();
					if (empty($ids))
					{
						$this->count=0;
						return $this->advance_step();
					}
					$this->range_select=Select_from_ids::from_ids($ids, $this->range_select->id_group()); // превращаем его в выборку по известным айди, которая может быть сведена к запросу.
				}
				$id_group=$this->base_value->value_model_now('id_group');
				$call=new Call([$id_group, 'transform_search_ticket'], $this->search);
				$this->search_select=$this->range_select->select_modified_by_calls($call);
			}
			$result=$this->get_count();
			if ($result instanceof Report) return $result;
			return $this->advance_step();
		}
		return parent::run_step();
	}
	
	public function get_count()
	{
		if ($this->count===null)
		{
			if ($this->forgo_count()) $this->count=false;
			else $this->count=$this->search_select->extract_count(); // возвращается RequestTicket
		}
		if ($this->count===false)
		{
			if ($this->page_select instanceof Selector)
			{
				if ($this->page_select->successful()) $this->count=count($this->page_select->resolution->values);
				elseif ($this->page_select->failed()) $this->count=0;
				else return $this->sign_report(new Report_task(new Task_delayed_call([$this, 'get_count'], $this->page_select)));
			}
			else return $this->count;
		}
		if ($this->count instanceof RequestTicket)
		{
			$result=$this->count->get_data_set();
			if ($result instanceof Report) return $result;
			$this->count=$result;
		}
		elseif ($this->count instanceof Report_task) $this->count=$this->count->task;
		if ($this->count instanceof Task)
		{
			if ($this->count->successful()) $this->count=$this->count->resolution;
			elseif ($this->count->failed()) return $this->count->report();
			else return $this->sign_report(new Report_task($this->count));
		}
		return $this->count;
	}
	
	public function is_paged()
	{
		if ($this->is_paged===null)
		{
			$count=$this->get_count();
			if ($count===false)
			{
				$report=$this->make_options();
				if ($report instanceof Report_impossible) return;
				if ($report instanceof Report_tasks) return $this->sign_report(new Report_task(new Task_delayed_call([$this, 'is_paged'], $report)));
				$count=$this->get_count(); // теперь должно посчитаться.
			}
			if ($count instanceof Report_impossible) $result=$count;
			elseif ($count instanceof Report_task)
			{
				$callback=function()
				{
					$count=$this->get_count();
					if ($count instanceof Report_impossible) return $count;
					return $count>=$this->search_limit;
				};
				$result=Task_delayed_call::with_call($callback, $count->task);
			}
			elseif ($count instanceof Report) die('BAD COUNT REPORT');
			else $result=$count>$this->search_limit;
			$this->is_paged=$result;
		}
		if ($this->is_paged instanceof Task)
		{
			if ($this->is_paged->successful()) $result=$this->is_paged->resolution;
			elseif ($this->is_paged->failed()) $result=$this->is_paged->report();
			else return $this->sign_report(new Report_task($this->is_paged));
			$this->is_paged=$result;
		}
		return $this->is_paged;
	}
	
	// возвращает истину в случае, если получение точного счёта слишком затратно и нужно получить только то, переваливают ли результаты за страницу.
	public function forgo_count()
	{
		return $this->range_select!==null && $this->range_select instanceof Select_filter; // STUB
	}
	
	public static function with_selector($select, $line=[])
	{
		$template=static::with_line($line);
		$template->search_select=$select;
		return $template;
	}
	
	public function options($now=true)
	{
		return Template_field_select::options($now);
	}
	
	public function make_options()
	{
		// STUB: в будущем будет возможность листать страницы.
		if ($this->count===0) return [];
		if ($this->page_select===null)
		{
			if ($this->forgo_count()) $limit=$this->search_limit+1;
			else $limit=$this->search_limit;
			$this->page_select=$this->search_select->select_limited($limit, $this->search_order());
		}
		if (!$this->page_select->completed()) return $this->sign_report(new Report_task($this->page_select));
		if ($this->page_select->failed()) return $this->page_select->report();

		$options=[];
		foreach ($this->page_select->resolution->values as $entity)
		{
			$template=Template_entity_option::for_entity($entity, $this->line);
			$options[$entity->db_id]=$template->template('title');
		}
		
		return $options;
	}
	
	public function recognize_element($code, $line=[])
	{
		if (in_array($code, $this->found_elements, true)) return true;
		return parent::recognize_element($code, $line);
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='options') return Template_field_select::make_template($code, $line);
		if ($code==='search') return (($this->search===null)?(''):($this->search));
		if ($code==='count_found')
		{
			return $this->get_count();
		}
		return parent::make_template($code, $line);
	}
	
	public function ValueHost_request($code)
	{
		if ($code==='paged') return $this->is_paged();
		elseif ($code==='forgo_count') return $this->forgo_count();
		return parent::ValueHost_request($code);
	}
}

// отличается тем, что имеет пагинатор и запрашивает _у_ элементов списка, а не перечисляет непосредственно их в виде элементов списка.
abstract class Template_list extends Template_composed
{	
	public
		$entry_template,
		
		$paged=false,
		$current_page=1,
		$count,
		$per_page=50,
		$page_var='p';
	
	public function spawn_subtasks()
	{
		$subjects=$this->get_list_subjects();
	
		if ($subjects instanceof Report_impossible) return $subjects;
	
		if (array_key_exists('entry_template', $this->line)) $entry_template=$this->line['entry_template'];
		elseif ($this->entry_template!==null) $entry_template=$this->entry_template;
		else return $this->sign_report(new Report_impossible('no_template_code'));
		
		if (is_string($entry_template)) $entry_template=preg_split('/\s*,\s*/', $entry_template);
		
		static $ask_master=1, $ask_subject=2;
		
		$ask=[];
		foreach ($entry_template as $key=>$template_code)
		{
			$ask[$key]=[];
			if ($template_code{0}==='#')
			{
				$ask[$key]['mode']=$ask_master;
				$ask[$key]['master']=Template::master_instance();
				$ask[$key]['code']=substr($template_code, 1);
			}
			else
			{
				$ask[$key]['mode']=$ask_subject;
				$ask[$key]['code']=$template_code;
			}
		}
		
		$list=[];
		foreach ($subjects as $subject)
		{
			if (!is_object($subject))
			{
				$list[]=$subject;
				continue;
			}
			
			foreach ($ask as $data)
			{
				$element=null;
				if ($data['mode']===$ask_subject)
				{
					$element=$subject->template($data['code'], $this->line);
				}
				elseif ($data['mode']===$ask_master)
				{
					$element=$data['master']->template($data['code'], $this->line);
					if ( ($element instanceof Template) && (empty($element->context)) && ($subject instanceof Template_context) ) $element->context=$subject;
				}
				if ($element===null) $list[]='MISSING TEMPLATE: '.$data['code'];
				else $list[]=$element;
			}
		}
		
		if ( ($this->paged) && ($this->count>$this->per_page) )
		{
			$pages=Template_pages::with_params($this->count, [], $this->current_page, $this->per_page, $this->page_var);
			array_unshift($list, $pages);
		}
		
		return $list;
	}
	
	public function progress()
	{
		if ( ($this->paged===true) && ($this->count===null) )
		{
			$result=$this->request_count();
			if ($result instanceof Report_impossible) $this->impossible('no_count_data');
			elseif ($result instanceof Report_task)
			{
				$this->count=$result->task;
				$this->register_dependancy($this->count);
			}
			elseif ($result instanceof Report_resolution) $this->count=$result->resolution;
			elseif (is_numeric($result)) $this->count=$result;
			else { vdump($result); die('BAD COUNT REPORT'); }
			return;
		}
		if ( ($this->paged===true) && ($this->count instanceof Task) )
		{
			if ($this->count->successful()) $this->count=$this->count->resolution;
			else $this->impossible('no_count_data');
			return;
		}
		
		parent::progress();
	}
	
	public abstract function get_list_subjects();
	
	public abstract function request_count();
}

class Template_list_call extends Template_list
{
	public
		$call=null,
		$list=null;
		
	public static function with_call($call, $line=[])
	{
		$template=static::with_line($line);
		$template->call=$call;
		return $template;
	}
	
	public function get_list_subjects()
	{
		$list=$this->get_complete_list();
		if ($this->paged)
		{
			$list=array_slice($list, ($this->current_page-1)*$this->per_page, $this->per_page);
		}
		return $list;
	}
	
	public function get_complete_list()
	{
		if ($this->list!==null) return $this->list;
		
		$call=$this->call;
		$this->list=$call($this->line);
		return $this->list;
	}
	
	public function request_count()
	{
		return count($this->get_complete_list());
	}
}
?>