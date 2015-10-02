<?
namespace Pokeliga\Nav;

abstract class Page_xml extends Page_view_from_db
{
	public
		$template_class='Template_page_xml',
		$possible_messages=[],
		$deliver_html=[], // список сообщений, возвращающих html.
		$message=null,
		$request_code=null,
		$xml_header=true,
		$input_model=
		[
			'message'=>
			[
				'type'=>'string'
			],
			'request_code'=>
			[
				'type'=>'string'
			]
		];
	
	public function send_header()
	{
		if ($this->xml_header) header('Content-Type: text/xml; charset=utf-8');
		else parent::send_header();
	}
	
	public function analyze_input()
	{
		if (!$this->input->input_success) return $this->sign_report(new \Report_impossible('bad_input'));	
		
		if (!in_array($this->input->content_of('message'), $this->possible_messages, true)) return $this->sign_report(new \Report_impossible('bad_message'));
		
		return $this->advance_step();
	}
	
	public function message()
	{
		return $this->input->content_of('message');
	}
	
	public function method()
	{
		return $this->message();
	}
	
	public function request_code()
	{
		return $this->input->content_of('request_code');
	}
	
	public function delivers_html()
	{
		return in_array($this->message(), $this->deliver_html, true);
	}
}

class Template_page_xml extends Template_page_from_db
{
	const
		NO_HTML_KEY='page.xml',
		DELIVERS_HTML_KEY='page.xml_html';
		
	public
		$elements=['method', 'request_code', 'result', 'eval'],
		$appendable=false;
	
	public function make_template($code, $line=[])
	{
		if ($code==='method') return $this->page->method();
		if ($code==='request_code') return $this->page->request_code();
		if ($code==='result') return $this->content();
		if ($code==='eval')
		{
			$template=Template_extract_eval::from_template($this->content(), $line);
			return $template;
		}
		return parent::make_template($code, $line);
	}
	
	public function get_db_key($now=true)
	{
		if ($this->page->delivers_html()) return static::DELIVERS_HTML_KEY;
		else return static::NO_HTML_KEY;
	}
}

class Template_extract_eval extends Template
{
	public
		$subtemplate;
	
	public static function from_template($subtemplate, $line=[])
	{
		$template=static::with_line($line);
		$template->subtemplate=$subtemplate;
		return $template;
	}
	
	public function progress()
	{
		if (!($this->subtemplate instanceof \Pokeliga\Template\Template))
		{
			$eval=$this->extract_eval($this->subtemplate);
			$this->finish_with_resolution($eval);
		}
		elseif ($this->subtemplate->failed()) $this->impossible('failed_subtemplate');
		elseif ($this->subtemplate->successful())
		{
			$eval=$this->extract_eval($this->subtemplate->resolution);
			$this->finish_with_resolution($eval);
		}
		else $this->register_dependancy($this->subtemplate);
	}
	
	public function extract_eval($text)
	{
		preg_match_all('/<script>(.+?)<\/script>/s', $text, $m);
		$eval=implode("\n", $m[1]);
		return $eval;
	}
}

class Template_composed_xml extends Template_composed
{
	public
		$content;

	public static function with_content($content, $line=[])
	{
		$template=static::with_line($line);
		$template->content=$content;
		return $template;
	}
	
	public function spawn_subtasks()
	{
		$list=[];
		foreach ($this->content as $tag=>$content)
		{
			if ( (is_string($content)) && (preg_match('/[<>]/', $content)) ) $content="<![CDATA[$content]]>";
			$list[]="<$tag>$content</$tag>";
		}
		return $list;
	}
}
?>