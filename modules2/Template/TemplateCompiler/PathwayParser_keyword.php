<?

namespace Pokeliga\Template;

class PathwayParser_keyword extends PathwayParser
{		
	protected
		$line;

	/*
	принимает содержимое строковое представление цепочки пути, а возврашает:
	[
		'track'	=> массив или строка
		'line'	=> null или готовая строка для eval,
	]
	*/
	
	protected function special_track($anchor, $track_string)
	{
		if ($anchor==='#') return [$anchor, $track_string];
		elseif ($anchor==='@')
		{
			$track=(array)$this->standard_track($track_string);
			array_unshift($track, $anchor);
			return $track;
		}
		else return parent::special_track($anchor, $track_string);
	}
	
	protected function parse_tail($tail)
	{
		$this->line=LineParser::compile($tail, $this->common);
	}
	
	protected function parse()
	{
		$result=parent::parse();
		$result['line']=$this->line;
		return $result;
	}

}

?>