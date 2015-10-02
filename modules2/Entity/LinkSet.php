<?
namespace Pokeliga\Entity;

// содержит набор сущностей, связанных с другой сущностью. предназначен для хранения в качестве содержимого в Value_linkset.
class LinkSet extends EntitySet
{
	public
		$entity,
		$pool;
		
	public static function for_value($value)
	{
		$linkset=new static();
		$linkset->setup_from_value($value);	
		return $linkset;
	}
	
	public static function for_entity($entity)
	{
		$linkset=new static();
		$linkset->setup_from_entity($entity);	
		return $linkset;
	}
	
	public function setup_from_entity($entity)
	{
		$this->entity=$entity;
		$this->pool=$this->entity->pool;
	}
	
	public function pool()
	{
		return $this->pool;
	}
	
	public function setup_from_value($value)
	{
		if (!empty($value->master->entity)) $this->setup_from_entity($value->master->entity);
	}
	
	public function ids()
	{
		$ids=[];
		foreach ($this->values as $entity)
		{
			$ids[]=$entity->db_id;
		}
		return $ids;
	}
}

class ValueType_linkset extends \Pokeliga\Data\ValueType implements \Pokeliga\Data\Value_provides_options, \Pokeliga\Data\ValueType_handles_fill, Value_contains_pool_member //, Value_searchable_options
{
	use Value_searchable_entity;
	
	const
		STANDARD_SELECTOR='Select',
		DEFAULT_TEMPLATE_CLASS='Template_value_linkset',
		DEFAULT_TEMPLATE_FORMAT_KEY='entry_template',
		SELECT_TEMPLATE_CODE='select',
		OPTIONS_TEMPLATE_CODE='options',
		HAS_CODE='has',
		HAS_SUBVALUE_CODE='has_subvalue',
		
		DEFAULT_OPTIONS_NUM=30,
		FOUND_OPTIONS_LIMIT=30;
	
	public
		$options=[];
	
	public static function type_conversion($content)
	{
		if ($content instanceof LinkSet) return $content;
		die ('BAD CONTENT');
	}
	
	// шаблоны, которые могут быть выполнены без заполнения линксета, чтобы лишний раз его не заполнять.
	public function template($code, $line=[])
	{
		if ($code===null) return $this->default_template($line);
		if ($code===static::SELECT_TEMPLATE_CODE) return $this->select_template($line);
		if ($code===LinkSet::COUNT_CODE)
		{
			$count_ticket=$this->get_selector()->extract_count();
			$report=$count_ticket->get_data_set();
			if ($report instanceof \Report_tasks)
			{
				$callback=function() use ($count_ticket)
				{
					$result=$count_ticket->compose_data();
					if ($result instanceof \Report_impossible) return 0;
					return $result;
				};
				return Task_delayed_call::with_call($callback, $report->tasks);
			}
			elseif ($report instanceof \Report_impossible) return 0;
			else return $report;
		}
		return parent::template($code, $line);
	}
	
	public function template_for_filled($code, $line=[])
	{
		if ($code===static::OPTIONS_TEMPLATE_CODE) return $this->options_template($line);
		if ($code===static::HAS_CODE)
		{
			if (!array_key_exists('id', $line)) return 'NO ID';
			return $this->content()->has_id($line['id']);
		}
		if ($code===static::HAS_SUBVALUE_CODE)
		{
			if (!array_key_exists('code', $line)) return 'NO CODE';
			if (!array_key_exists('value', $line)) return 'NO VALUE';
			return $this->content()->has_subvalue($line['code'], $line['value']);
		}
		return parent::template_for_filled($code, $line);
	}
	
	public function default_template($line=[])
	{
		$line=array_merge($this->value_model(), $line);
		
		$selector=$this->get_selector();
		
		if (array_key_exists('search', $line)) $selector=$selector->select_search($line['search']);
		if (array_key_exists('limit', $line))
		{
			if (array_key_exists('order', $line)) $order=$line['order']; else $order='id';
			$selector=$selector->select_limited($line['limit'], $order);
		}
		elseif (array_key_exists('order', $line)) $selector=$selector->select_ordered($line['order']);
		elseif (array_key_exists('random', $line)) $selector=$selector->select_random($line['random']);
		
		if (array_key_exists('per_page', $line))
		{
			if (array_key_exists('order', $line)) $order=$line['order']; else $order='id';
			if (array_key_exists('page_var', $line)) $page_var=$line['page_var']; else $page_var='p';
			$per_page=$line['per_page'];
			$selector=$selector->select_page($order, $page_var, $per_page);
		}
		
		$template=Template_linkset::with_selector($selector, $line);
		
		return $template;
	}
	
	public function select_template($line=[])
	{
		$template=Template_field::for_value('select', $this, $line);
		return $template;
	}
	
	public function options_template($line=[])
	{
		$template=Template_linkset_options::with_linkset($this->content, $line);
		return $template;
	}
	
	public $select;
	public function get_selector()
	{
		if ($this->select===null) $this->select=$this->create_selector();
		return $this->select;
	}
	
	public function create_selector()
	{
		if ($this->filler_task instanceof Select) return $this->filler_task;
		if (!$this->in_value_model('select'))
		{
			if ($this->has_state(Value::STATE_FILLED)) return $this->representative_selector();
			return null;
		}
		return $this->standard_selector();
	}
	
	public function standard_selector()
	{
		$class=static::STANDARD_SELECTOR;
		return $class::for_value($this);
	}
	
	// возвращает Селектор, представляющий готовый набор (а не тот, который по модели).
	public function representative_selector()
	{
		if ($this->in_value_model('select')) die('BAD REPRESENTATIVE');
		if (!$this->has_state(Value::STATE_FILLED)) die('BAD REPRESENTATIVE');
		
		$select=Select_from_ids::for_value($this);
		$select->ids=$this->content->ids();
		return $select;
	}
	
	public function fill()
	{
		$filler=$this->get_selector();
		if ($filler===null)
		{
			if ($this->in_value_model('default')) $this->set($this->value_model_now('default'), Value::NEUTRAL_CHANGE);
			else die('NO SELECT FOR LINKSET');
		}
		else $filler->master_fill();
	}
	
	public function detect_mode()
	{
		return \Pokeliga\Data\Value::MODE_AUTO;
	}
	
	public function ValueHost_request($code)
	{
		if (!$this->has_state(static::STATE_FILLED)) die('UNIMPLEMENTED YET: delayed subvalue');
		return $this->content()->request($code);
	}
	
	public function options($line=[])
	{
		$result=null;
		if (empty($line)) $options_key=''; else $options_key=serialize($line);
		
		if (!array_key_exists($options_key, $this->options))
		{
			$result=Task_linkset_make_options::for_value($this);
			$result->line=$line;
		}
		else $result=$this->options[$options_key];
		
		if ($result instanceof \Pokeliga\Task\Task)
		{
			if ($result->failed()) $result=false;
			elseif ($result->successful()) $result=$result->resolution;
			
		}
		$this->options[$options_key]=$result;
		
		if ($result instanceof \Pokeliga\Task\Task) return $this->sign_report(new \Report_task($result));
		if ($result===false) return $this->sign_report(new \Report_impossible('bad_options'));
		if (is_array($result)) return $result;
		die ('BAD LINKSET OPTIONS');
	}
	
	public function API_search_arguments()
	{
		return 'group='.$this->value_model_now('id_group').'&select='.$this->value_model_now('select'); // STUB! там могут быть ещё параметры, нужно спросить Select.
	}
	
	public function found_options_template($search=null)
	{
		if ($search===null) return $this->template('options');

		$value=Select_search::value_by_subselect($search, $this);
		
		$line=[];
		if ($this->in_value_model('option_title_template')) $line['title_template']=$this->value_model_now('option_title_template');
		
		$template=$value->template('options', $line);
		return $template;
	}
}

// FIX! требуется обновление.
class ValueType_linkset_ordered extends ValueType_linkset
{
	public function from_db($content)
	{
		if (!is_array($content)) return $content; // проверять действительность - не дело этой функции.
		
		$linkset=LinkSet::for_value($this);
		ksort($content);
		foreach ($content as $ord=>$id)
		{
			$entity=$linkset->create_value($ord, $id);
			$linkset->add($entity, $ord);
		}
		return $linkset;
	}
	
	public function for_db()
	{
		if (empty($linkset=$this->content)) return [];
		if (empty($linkset->values)) return [];
		$result=[];
		foreach ($linkset->values as $ord=>$entity)
		{
			$result[$ord]=$entity->db_id;
		}
		return $result;
	}
	
	public function add($value, $ord=null)
	{
		$ord=parent::add($value);
		ksort($this->values);
		return $ord;
	}
}

class Task_linkset_make_options extends \Pokeliga\Data\Task_for_value
{
	use \Pokeliga\Task\Task_steps;
	
	const
		STEP_FILL_VALUE=0,
		STEP_COLLECT_OPTIONS=1,
		STEP_COMPOSE=2,
		
		OPTION_TEMPLATE_CODE='title';
	
	public
		$options=[],
		$line=[];
	
	public function run_step()
	{
		if ($this->step===static::STEP_FILL_VALUE)
		{
			$result=$this->value->request();
			if ($result instanceof \Report_success) return $this->advance_step();
			return $result;
		}
		elseif ($this->step===static::STEP_COLLECT_OPTIONS)
		{
			$linkset=$this->value->value();
			if ($linkset instanceof \Report) return $linkset;
			
			$tasks=[];
			foreach ($linkset->values as $linked)
			{
				if (!($linked instanceof Entity)) return $this->sign_report(new \Report_impossible('bad_linked'));
				$id=$linked->db_id; // FIX! пока не позволяет в одном списке сущностей из разных групп айди, как, впрочем, и Линксет пока что.
				
				if ( (!empty($this->line)) && (array_key_exists('option_template', $this->line)) ) $code=$this->line['option_template'];
				else $code=static::OPTION_TEMPLATE_CODE;
				$template=$linked->template($code);
				
				
				if ($template===null) return $this->sign_report(new \Report_impossible('unknown_template'));
				elseif ($template->failed()) return $template->report();
				elseif ($template->successful()) $this->options[$id]=$template->resolution;
				else
				{
					$this->options[$id]=$template->resolution;
					$template->resolution=&$this->options[$id]; // ту же операцию сделает задача, если ей понадобится приравнять разрешение с разрешению некой подзадачи, и цепочка не разорвётся.
					$tasks[]=$template;
				}
			}
			
			if (empty($tasks)) return $this->advance_step();
			return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_COMPOSE)
		{
			// если сюда дошла очередь, то все зависимости сработали успешно.
			return $this->sign_report(new \Report_resolution($this->options));
		}
	}
	
	public function dependancy_resolved($task, $identifier=null)
	{
		if ( ($this->step===static::STEP_COLLECT_OPTIONS) && ($task->failed()) ) $this->impossible('bad_option');
		parent::dependancy_resolved($task, $identifier);
	}
}

class Template_linkset extends \Pokeliga\Data\Template_list
{
	public
		$linkset=null,
		$select=null,
		$entry_template='link';

	public static function with_linkset($linkset, $line=[])
	{
		$template=static::with_line($line);
		$template->linkset=$linkset;
		return $template;
	}
	
	public static function with_selector($select, $line=[])
	{
		$template=static::with_line($line);
		$template->select=$select;
		if ($select instanceof \Pokeliga\Template\Paged)
		{
			$template->paged=true;
			$template->current_page=$select->get_page();
			$template->per_page=$select->get_per_page();
			$template->page_var=$select->get_page_var();
		}
		return $template;
	}
	
	public function progress()
	{
		if ($this->linkset===null)
		{
			if ($this->select===null) $this->impossible('no_linkset');
			elseif ($this->select->successful()) $this->linkset=$this->select->value->content();
			elseif ($this->select->failed()) $this->impossible('no_linkset');
			else
			{
				$this->register_dependancy($this->select);
				$this->linkset=$this->select->value->content; // объект содержимого уже заполнен.
			}
		}
		else parent::progress();
	}
	
	public function get_list_subjects()
	{
		return $this->linkset->values;
	}
	
	public function request_count()
	{
		$request=$this->select->get_complete_count();
		if ($request instanceof \Pokeliga\Retriever\RequestTicket)
		{
			$report=$request->get_data_set();
			return $report;
		}
		die('UNIMPLEMENTED YET: non-request count');
	}
}

class Template_linkset_options extends Template_linkset
{
	const
		DEFAULT_ON_EMPTY='';
	
	public function spawn_subtasks()
	{
		if (empty($this->linkset->values)) return parent::spawn_subtasks();
		
		$list=[];
		foreach ($this->linkset->values as $entity)
		{
			$list[]=Template_entity_option::for_entity($entity, $this->line);
		}
		return $list;
	}
}
?>