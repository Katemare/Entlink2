<?
namespace Pokeliga\Form;

abstract class Form extends FieldSet
{
	use Object_id;
	
	const
		MODE_VIEW=1,
		MODE_PROCESS=2,
		UNAUTHORIZED_ERROR_PREFIX='form.error_',
		FORM_ID_CODE='form_id';

	public
		$slug=null,
		$input=null,
		$data=null,
		$action='',
		$pool=null,
		$operative=true, // совершает ли форма операции или работает в только чтении? FIX: EntityPool пока всё равно создаётся оперативный.
		$main_template_class='Template_form',
		$template_db_key='form.standard',
		
		$content_template_class='Template_from_db',
		$content_db_key=null,
		
		$js_on_submit=null,
		$page=null; // STUB: для обратной связи формы со страницей.
		
	public function pool()
	{
		if ($this->pool!==null) return $this->pool;
		if ( (array_key_exists('pool', $this->super_model)) && ($this->super_model['pool'] instanceof \Pokeliga\Entity\EntityPool) ) $this->pool=$this->super_model['pool'];
		else $this->pool=EntityPool::default_pool();
		return $this->pool;
	}
	
	public function __construct()
	{
		$this->generate_object_id();
	}
	
	public function html_id()
	{
		return 'form'.$this->object_id;
	}
	
	public static function for_fieldset($type_keyword, $fieldset, $code)
	{
		die('FORM FOR FIELDSET');
	}
	
	public function method()
	{
		static
			$convert=
			[
				InputSet::SOURCE_GET=>'get',
				InputSet::SOURCE_POST=>'post',
				InputSet::SOURCE_GET_POST=>'get'
			];
		return $convert[$this->source_setting];	
	}
	
	// STUB! в будущем должно быть частью обработки и подготовки формы.
	public function rightful()
	{
		return true;
	}
	
	public function template($code, $line=[])
	{
		if ($code===static::FORM_ID_CODE) return $this->html_id();
		return parent::template($code, $line);
	}
	
	public function main_template($line=[])
	{
		$template=parent::main_template($line);
		if ($template instanceof Template_form) $template->form=$this;
		return $template;
	}
	
	public function process_task()
	{
		if ($this->rightful()!==true) return $this->sign_report(new \Report_impossible('unauthorized'));
		return parent::process_task();
	}
	
	public function content_template($line=[])
	{
		$class=$this->content_template_class;
		$template=$class::with_line($line);
		if ($template instanceof Template_requies_fieldset) $template->set_fieldset($this);
		if ($template instanceof Template_form) $template->form=$this;
		if ( ($this->content_db_key!==null) && ($template instanceof \Pokeliga\Template\Template_from_db) ) $template->db_key=$this->content_db_key;
		return $template;
	}
	
	public function make_tracks()
	{
		parent::make_tracks();
		if (!array_key_exists('form', $this->tracks)) $this->tracks['form']=$this;
	}
	
	public function redirect_by_task($task)
	{
		$this->redirect_by_report($task->report());
	}
	
	public function redirect_by_report($report)
	{
		if ($report instanceof \Report_success) $this->redirect_successful();
		elseif ($report instanceof \Report_impossible) $this->redirect_failed();
		// если задача не закончена, то она явно подана ошибочно. пусть с этим разбирается объект Page_form, который предположительно и подсунул такую задачу.
	}
	
	public function redirect_successful()
	{
		$this->redirect_back();  // replace_me!
	}
	
	public function redirect_failed()
	{
		$this->redirect_back();
	}
	
	public function redirect_back()
	{
		Router()->get_back();
	}
}

class Template_form extends Template_fieldset
{		
	public
		$form=null,
		$checked_authorization=false,
		$db_key='form.standard',
		$elements=['content', 'action', 'slug', 'method', 'header_attributes', 'errors', 'onsubmit'];
	
	public function run_step()
	{
		if ( ($this->step===static::STEP_GET_KEY) && (!$this->checked_authorization) )
		{
			$this->checked_authorization=true;
			if ( ($rightful=$this->form->rightful())!==true)
			{
				$form=$this->form;
				if (is_string($rightful)) $this->db_key=$form::UNAUTHORIZED_ERROR_PREFIX.$rightful;
				else $this->db_key='form.unauthorized';
				return $this->advance_step(static::STEP_GET_TEXT);
			}
		}
		return parent::run_step();
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='content') return $this->form->content_template($line);
		if ($code==='action')
		{
			if (empty($this->form->slug)) return $this->form->action;
			return Router()->url('modules2/form.php');
		}
		if ($code==='slug') return (string)$this->form->slug;
		if ($code==='method') return $this->form->method();
		if ($code==='header_attributes') return ''; // STUB
		if ($code==='errors') return ''; // STUB
		if ($code==='onsubmit')
		{
			if ($this->form->js_on_submit===null) return '';
			return 'return '.$this->form->js_on_submit.'(\''.$this->form->html_id().'\');'; // можно было бы вставить всё это в шаблон формы, но я не уверена, что тяжелее: эта строчка со строковым сложением или инструция в шаблоне.
		}
	}
}

class Page_form extends Page_operation
{
	const
		URL_NO_FORM='form/no_form',
		URL_FALLBACK='form/bad_form';
		
	public
		$input_model=
		[
			'form_slug'=>
			[
				'type'=>'keyword',
				'name'=>'_form'
			]
		];
	/*
	public function pool()
	{
		if (!is_object($this->pool))
		{
			if ($this->inputset->operative) $this->pool=EntityPool::MODE_OPERATION;
			else $this->pool=$this->pool=EntityPool::MODE_READ_ONLY;
		}
		return parent::pool();
	}
	*/
	
	public function create_inputset()
	{
		$inputset=parent::create_inputset();
		$inputset->source_setting=InputSet::SOURCE_GET_POST;
		return $inputset;
	}
	
	public function process()
	{
		$form_slug=$this->input->content_of('form_slug');
		$form_class=Engine()->form_class_by_slug($form_slug);
		if (empty($form_class)) $this->redirect_no_form();
		
		$form=$form_class::create_for_process();
		$task=$form->process_task();
		if ($task instanceof \Pokeliga\Task\Task)
		{
			$task->complete();
			// global $debug; if ($debug) { debug_dump(); die('MEOW'); }
			$form->redirect_by_task($task);
		}
		elseif ($task instanceof \Report)
		{
			$form->redirect_by_report($report);
		}
		$this->redirect_fallback();
	}
	
	public function redirect_no_form()
	{
		$this->redirect(static::URL_NO_FORM);
	}
	
	public function redirect_fallback()
	{
		$this->redirect(static::URL_FALLBACK);
	}
}
?>