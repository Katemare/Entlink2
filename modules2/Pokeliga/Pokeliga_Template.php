<?
namespace Pokeliga\Pokeliga;

class Template_page_pokeliga extends Template_page_from_db
{
	public
		$begin=null,
		$pokeliga_elements=['content_class'];
	
	public function __construct()
	{
		parent::__construct();
		$this->context=Context_pokeliga_page::for_template($this);
	}
	
	public function recognize_element($code, $line=[])
	{
		if (in_array($code, $this->pokeliga_elements, true)) return true;
		return parent::recognize_element($code, $line);
	}
	
	public function begin()
	{
		if (is_null($this->begin))
		{
			$this->begin=Template_ob_inclusion::from_address(Engine()->server_address('compatible2/nav.php'));
			$content=$this->page->content();
			if ($content instanceof \Pokeliga\Task\Task) $this->begin->register_dependancy($content);
		}
		return $this->begin;
	}
	
	public function end()
	{
		$end=Template_ob_inclusion::from_address(Engine()->server_address('compatible2/end.php'));
		$begin=$this->begin();
		if ($begin instanceof \Pokeliga\Task\Task) $end->register_dependancy($begin);
		return $end;
	}
	
	public function content()
	{
		$content=parent::content();
		if ($content instanceof \Pokeliga\Task\Task)
		{
			$content->add_call
			(
				function() { $this->analyze_complete_content(); },
				'complete'
			);
		}
		return $content;
	}
	
	public function analyze_complete_content()
	{
		global $pgtitle;
		$pgtitle=$this->page->get_title_task()->now();
		global $content_requirements;
		$content_requirements=$this->page->requirements;
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='content_class') return '';
		return parent::make_template($code, $line);
	}
	
	public function initiated()
	{
		$this->page->register_requirement('js', Router()->module_url('Pokeliga', 'standard.js'), Page::PRIORITY_STANDARD_FRAMEWORK);
		parent::initiated();
	}
}

class Context_pokeliga_page extends Context
{
	public function setup($master)
	{
		$this->append($master, 'nav');
	}
}

class Template_ob_inclusion extends Template implements Template_page
{
	public
		$page,
		$server_address;
	
	public static function from_address($address)
	{
		$template=static::blank();
		$template->server_address=$address;
		return $template;
	}
	
	public function progress()
	{
		ob_start();
		include_once($this->server_address);
		$result=ob_get_contents();
		ob_end_clean();
		
		$this->resolution=$result;
		$this->finish();
	}
}

?>