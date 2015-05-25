<?

interface Paged
{
	public function get_page();
	public function get_per_page();
	public function get_page_var();
	public function get_complete_count();
}

class Template_pages extends Template_from_db
{
	public
		$db_key='standard.pages',
		$count,
		$current_page,
		$per_page=50,
		$page_var='p',
		$extend_ends=2, // 1 2 3... 10 11 12 ...18 19 20
		$extend_mid=1,
		$elements=['pages', 'url', 'page_var'],
		$page_numbers;
	
	public static function with_line($line=[])
	{
		$template=parent::with_line($line);
		if (array_key_exists('per_page', $line)) $template->per_page=$line['per_page'];
		if (array_key_exists('page_var', $line))
		{
			$template->page_var=$line['page_var'];
			$template->current_page=InputSet::instant_fill($template->page_var, ['type'=>'unsigned_int', 'min'=>1, 'default'=>1]);
		}
		if (array_key_exists('count', $line)) $template->count=$line['count'];
		return $template;
	}
	
	public static function with_params($count, $line=[], $page=1, $per_page=50, $page_var='p')
	{
		$template=static::with_line($line);
		$template->count=$count;
		$template->current_page=$page;
		$template->per_page=$per_page;
		$template->page_var=$page_var;
		return $template;
	}
	
	public function progress()
	{
		if ($this->step===static::STEP_INIT)
		{
			$this->current_page=min_max_int($this->current_page, 1, $this->max_page());
		}
		parent::progress();
	}
	
	public function max_page()
	{
		return (int)ceil($this->count/$this->per_page);
	}
	
	public function next_page()
	{
		if ($this->current_page<$this->max_page()) return $this->current_page+1;
		return false;
	}
	
	public function previous_page()
	{
		if ($this->current_page>0) return $this->current_page-1;
		return false;
	}
	
	public $has_hidden_pages;
	public function has_hidden_pages()
	{
		if ($this->has_hidden_pages!==null) return $this->has_hidden_pages;
		
		$prev=null; $hidden=false;
		foreach ($this->page_numbers() as $page)
		{
			if ( ($prev!==null) && ($page>$prev+1) )
			{
				$hidden=true;
				break;
			}
			$prev=$page;
		}
		$this->has_hidden_pages=$hidden;
		
		return $hidden;
	}
	
	public function page_url($page)
	{
		return Router()->compose_url($this->url_base, [$this->page_var=>$page]);
	}
	
	public function ValueHost_request($code)
	{
		if ($code==='hidden_pages') return $this->sign_report(new Report_resolution($this->has_hidden_pages()));
		return parent::ValueHost_request($code);
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='pages')
		{
			$template=Template_composed_call::with_call([$this, 'populate_pages'], $line);
			return $template;
		}
		if ($code==='url') return $this->url_base();
		if ($code==='page_var') return $this->page_var;
		return parent::make_template($code, $line);
	}
	
	public function url_base()
	{
		return Router()->compose_url(null, [$this->page_var=>null]);
	}
	
	public function page_numbers()
	{
		if ($this->page_numbers!==null) return $this->page_numbers;
		
		$max_page=$this->max_page();
		$pages=[];
		if ($max_page>0)
		{
			$pages=array_merge
			(
				range(1, min($max_page, $this->extend_ends)),
				range(max(1, $this->current_page-$this->extend_mid), min($max_page, $this->current_page+$this->extend_ends)),
				range(max(1, $max_page-$this->extend_ends+1), $max_page)
			);
			$pages=array_unique($pages);
			sort($pages);
		}
		$this->page_numbers=$pages;
		return $pages;
	}
	
	public function populate_pages($line=[])
	{
		$pages=$this->page_numbers();
		
		$list=[];
		$prev=null;
		foreach ($pages as $page)
		{
			if ( ($prev!==null) && ($page>$prev+1) ) $list[]='...';
			if ($page==$this->current_page) $list[]=$page;
			else
			{
				$template=Template_paginator_page::for_paginator($page, $this, $line);
				$list[]=$template;
			}
			$prev=$page;
		}
		return $list;
	}
}

class Template_paginator_page extends Template_from_db
{
	public
		$paginator,
		$page_number,
		$db_key='standard.link',
		$elements=['url', 'title'];
	
	public static function for_paginator($page, $paginator, $line=[])
	{
		$template=static::with_line($line);
		$template->page_number=$page;
		$template->paginator=$paginator;
		return $template;
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='url') return $this->url();
		if ($code==='title') return $this->page_number;
		return parent::make_template($code, $line);
	}
	
	public function url()
	{
		$page_number=$this->page_number;
		if ($page_number==1) $page_number=null;
		return Router()->compose_url(null, [$this->paginator->page_var=>$page_number]);
	}
}
?>