<?
namespace Pokeliga\Form;

class Form_search extends Form
{
	const
		MODE_GO='go',
		MODE_SEARCH='search',
		PER_PAGE=50;
		
	public
		$model=
		[
			'search'=>
			[
				'type'=>'search',
				'template'=>'input'
			],
			'mode'=>
			[
				'type'=>'enum',
				'options'=>[Form_adopts_find_player::MODE_GO, Form_adopts_find_player::MODE_SEARCH],
				'template'=>'submit'
			],
			'page'=>
			[
				'type'=>'unsigned_int',
				'template'=>'hidden',
				'min'=>1,
				'default'=>1
			],
			'results_page'=>
			[
				'type'=>'unsigned_int'
			],
			'count'=>
			[
				'type'=>'unsigned_int'
			],
			'results_list'=>
			[
				'type'=>'linkset'
			],
			'result_ids'=>
			[
				'type'=>'array'
			]
		],
		$erase_session_on_success=false,		// потому что результат работы формы записывается в сессию.
		$erase_session_on_display=false,
		
		$source_setting=InputSet::SOURCE_GET,
		$input_fields=['search', 'mode', 'page'],
		$content_template_class='Template_search_form',
		$results_template_class='Template_search_results',
		$no_results_template_class='Template_from_db',
		$results_db_key='form.search_results',
		$no_results_db_key='form.search_no_results',
		$allows_exact_result=false;
	
	public function input_is_invalid()
	{
		$this->erase_session();
	}
	
	public function prepare_display()
	{
		parent::prepare_display();
		$this->fill_defaults(['page']);
	}
	
	public function template($code, $line=[])
	{
		if ($code==='results') return $this->results_template($line);
		return parent::template($code, $line);
	}
	
	public function results_template($line=[])
	{
		if (!$this->has_session_data()) return '';
		$this->fill_from_session(['results_page', 'count', 'result_ids']);
		$ids=$this->content_of('result_ids');
		if (empty($ids))
		{
			$this->erase_session();
			$class=$this->no_results_template_class;
			$template=$class::with_line($line);
			$template->db_key=$this->no_results_db_key;
			return $template;
		}
		$results=$this->produce_value('results_list');
		$selector=Select_from_ids::for_value($results);
		$selector->ids=$ids;
		$result=$selector->master_fill(); // айди уже известны, обращений к БД не понадобится.
		if (!($result instanceof \Report_final)) $selector->complete();
		$class=$this->results_template_class;
		$template=$class::with_line($line);
		if ( ($this->results_db_key!==null) && ($template instanceof \Pokeliga\Template\Template_from_db) ) $template->db_key=$this->results_db_key;
		if ($template instanceof Template_search_results) $template->form=$this;
		$this->erase_session();
		return $template;
	}
	
	public function max_page()
	{
		return ceil($this->content_of('count')/static::PER_PAGE);
	}
}

abstract class Task_process_search_form extends Task_for_fieldset
{
	use Task_steps;
	
	const
		STEP_REQUEST_EXACT=0,
		STEP_ANALYZE_EXACT=1,
		STEP_REQUEST_COUNT=2,
		STEP_REQUEST_IDS=3,
		STEP_GATHER_IDS=4,
		STEP_SAVE_IDS=5;
	
	public
		$exact_entity=null,
		$request=null,
		$search=null,
		$page=null,
		$count=null,
		$ids=null,
		$max_results=100;
	
	public function run_step()
	{
		if ($this->step===static::STEP_REQUEST_EXACT)
		{
			$this->inputset->erase_session();
			$this->search=$this->inputset->content_of('search');
		}
		
		if ($this->step===static::STEP_REQUEST_EXACT)
		{
			$field=$this->inputset;
			if ( (!$this->inputset->allows_exact_result) || ($field->content_of('mode')===$field::MODE_SEARCH) ) return $this->advance_step(static::STEP_REQUEST_COUNT);
			
			$this->exact_entity=$this->make_exact_entity();
			if ( (empty($this->exact_entity)) || ($this->exact_entity instanceof \Report_impossible) ) return $this->advance_step(static::STEP_REQUEST_COUNT);
			$result=$this->exact_entity->exists(false);
			if ($result===true) return $this->advance_step();
			if ($result===false) return $this->advance_step(static::STEP_REQUEST_COUNT);
			return $result;
		}
		elseif ($this->step===static::STEP_ANALYZE_EXACT)
		{
			if ($this->exact_entity->has_db_id()) Router()->redirect($this->exact_entity->value('profile_url'));
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_REQUEST_COUNT)
		{
			$query=$this->make_count_query();
			$this->request=Request_single::from_query($query);
			return $this->request->get_data_set();
			// здесь можно было бы использовать LinkSet, но запрос довольно специфический (поскольку используются две таблицы), а требуются только айди. сущности понадобятся только при переходе на следующую страницу.
		}
		elseif ($this->step===static::STEP_REQUEST_IDS)
		{
			if ($this->request->failed()) return $this->request->report();
			
			$field=$this->inputset;
			$count=$this->request->get_data();
			$count=reset($count)['cnt'];
			$this->count=$count;
			$page=$this->inputset->content_of('page');
			$this->page=min($page, floor($count/$field::PER_PAGE)+1);
			$query=$this->make_results_query();
			$this->request=Request_single::from_query($query);
			return $this->request->get_data_set();
		}
		elseif ($this->step===static::STEP_GATHER_IDS)
		{
			if ($this->request->failed()) return $this->request->report();
			
			$result=$this->request->get_data();
			$this->ids=[];
			foreach ($result as $row)
			{
				$this->ids[]=$row['id'];
			}
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_SAVE_IDS)
		{
			$this->inputset->save_to_session
			([
				'result_ids'=>$this->ids,
				'search'=>$this->search,
				'count'=>$this->count,
				'results_page'=>$this->page,
				FieldSet::EXPIRY_KEY=>time()+FieldSet::DEFAULT_EXPIRY
			]);
			return $this->sign_report(new \Report_resolution($this->ids));
		}
	}
	
	public function make_exact_entity()
	{
		die('NO EXACT ENTITY');
	}
	
	public function make_count_query()
	{
		$query=$this->make_base_query($this->search);
		$query['fields']=[ ['field'=>'id', 'function'=>'COUNT', 'alias'=>'cnt'] ];
		return $query;
	}
	
	public function make_results_query()
	{
		$field=$this->inputset;
		$query=$this->make_base_query($this->search);
		$query['limit']=[($this->page-1)*$field::PER_PAGE, $field::PER_PAGE];
		$query['order']=['id'];
		return $query;	
	}
	
	public abstract function make_base_query();
}

class Template_search_form extends Template_form
{
	public
		$form=null,
		$elements=['results'];
		
	public function make_template($code, $line=[])
	{
		if ($code==='results') return $this->form->results_template($line);
	}
	
	public function initiated()
	{
		parent::initiated();
		$this->page->register_requirement('js', Router()->module_url('Form', 'search.js'));
	}
}

class Template_search_results extends Template_from_db
{
	const
		PREVIOUS_DB_KEY='form.search_previous',
		NEXT_DB_KEY='form.search_next';

	public
		$elements=['count', 'page', 'max_page', 'previous', 'next', 'previous_url', 'next_url', 'list', 'from', 'to'],
		$form=null;
	
	public function results_page()
	{
		return $this->form->content_of('results_page');
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='count') return $this->form->content_of('count');
		if ($code==='page') return $this->results_page();
		if ($code==='previous')
		{
			if ($this->results_page()===1) return '';
			$template=static::with_line($line);
			$template->form=$this->form;
			$template->db_key=static::PREVIOUS_DB_KEY;
			return $template;
		}
		if ($code==='next')
		{
			if ($this->results_page()==$this->form->max_page()) return '';
			$template=static::with_line($line);
			$template->form=$this->form;
			$template->db_key=static::NEXT_DB_KEY;
			return $template;
		}
		if ($code==='previous_url')
			return
				'javascript:navigate_search_results_page('.
				($this->results_page()-1).', \''.
				$this->form->html_id().'\', \''.
				$this->form->name('page').
				'\')';
		if ($code==='next_url')
			return
				'javascript:navigate_search_results_page('.
				($this->results_page()+1).', \''.
				$this->form->html_id().'\', \''.
				$this->form->name('page').
				'\')';
		if ($code==='list')
		{
			$line['raw']=true;
			return $this->form->template('results_list', $line);
		}
		if ($code==='from')
		{
			$form=$this->form;
			return ($this->results_page()-1)*$form::PER_PAGE+1;
		}
		if ($code==='to')
		{
			$form=$this->form;
			return min(($this->results_page())*$form::PER_PAGE, $form->content_of('count'));
		}		
	}
}

class ValueType_search extends ValueType_string
{
	public
		$min=1,
		$max=50;
}
?>