<?
namespace Pokeliga\Template;

class Template_js_var extends Template
{
	public
		$subtemplate;

	public static function from_template($subtemplate, $line=[])
	{
		$template=static::with_line($line);
		$template->subtemplate=$subtemplate;
		return $template;
	}
	
	public function initiated()
	{
		parent::initiated();
		$this->setup_subtemplate($this->subtemplate);
	}
	
	public function progress()
	{
		if ($this->subtemplate instanceof \Pokeliga\Task\Task)
		{
			if ($this->subtemplate->failed()) $this->impossible('bad_subtemplate');
			elseif ($this->subtemplate->successful())
			{
				$this->finish_with_resolution($this->for_js($this->subtemplate->resolution));
			}
			else $this->register_dependancy($this->subtemplate);
		}
		else $this->finish_with_resolution($this->for_js($this->subtemplate));
	}
	
	public function for_js($content)
	{
		if ($content===null) return 'null';
		return var_export($this->var_safe($content), true); // предположение, что этого хватит.
	}
	
	public function var_safe($content)
	{
		return str_replace(["'", "\n", "\r"], ["\'", '\n', '\n'], $content);
	}
}

class Template_js_html extends Template_js_var
{
	public function for_js($content)
	{
		if ( (array_key_exists('format', $this->line)) && ($this->line['format']==='object') )
			return "{html: '".$this->var_safe($this->filter_out_scripts($content))."', eval: '".$this->var_safe($this->extract_scripts($content))."'}";
			// дальнейшая обработка (заключение в кавычки) не требуется.
			
		if (array_key_exists('scripts', $this->line))
		{
			if ($this->line['scripts']) $content=$this->extract_scripts($content);
			else $content=$this->filter_out_scripts($content);
		}
		return parent::for_js($content);
	}
	
	public function extract_scripts($content)
	{
		preg_match_all('/<script>(.+?)<\/script>/s', $content, $m);
		$eval=implode("\n", $m[1]);
		return $eval;
	}
	
	public function filter_out_scripts($content)
	{
		return preg_replace('/<script>(.+?)<\/script>/s', '', $content);
	}
}

?>