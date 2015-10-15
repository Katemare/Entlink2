<?

namespace Pokeliga\Template;

/*
* Берёт строку, олицетворяющую командную строку к Pathway, и превращает её в строковое представление php-данных.
*/
class LineParser extends Subparser
{
	const
		ELEMENT_EX='/^\s*(?<key>[a-z\d\_]+)(?<op>:?=)(?<value>.*)$/';
	
	protected
		$content,
		$common;
	
	public function __construct($content, $common)
	{
		$this->content=$content;
		$this->common=$common;
	}
	
	public static function compile_processed($data, ParserCommon $common)
	{
		foreach ($data as $key=>&$val)
		{
			$val=var_export($key, true).'=>'.$val;
		}
		return '['.implode(', ', $data).']';
	}
	
	protected function parse()
	{
		$commandline=static::parse_commands($this->content, '|', ['{{', '}}'] );
		$final_line=[];
		$next_numbered_element=0;
		foreach ($commandline as $element)
		{
			if (preg_match(static::ELEMENT_EX, $element, $m))
			{
				$op=$m['op'];
				$value=$m['value'];
				$key=$m['key'];
				if ($op==='=') $value=var_export($value, true);
				elseif ($op===':=') $value=$this->parse_dynamic_element($value);
				$final_line[$key]=$value;
			}
			elseif ($element!=='')
			{
				$final_line[$next_numbered_element++]=var_export($element, true);
			}
		}
		return $final_line;
	}
	
	protected function parse_dynamic_element($string_value)
	{	
		return ExpressionParser::compile($string_value, $this->common);
	}
}

?>