<?

namespace Pokeliga\Template;

/*
* Разбирает строку, отражающую путь (предназначенную для разбора Tracker'ом)
*/
abstract class PathwayParser extends Subparser
{
	const
		PATHWAY_EX='/^(?<anchor>[^a-z])?(?<track>[a-z\d\._]+)(\||$)/i';
		// в формулах, являющихся частью ключевых слов, следует употреблять or вметсто |, обозначая логическое ИЛИ.
		
	protected
		$track;
	
	protected function special_track($anchor, $track_string)
	{
		throw new \Exception('unknown anchor');
	}

	// STUB! здесь должна быть возможность уточнения типа {{pokemon[id=1].sprite}} и даже {{pokemon.former_owners[nickname~A*].linked_nickname}}.
	protected function standard_track($track_string)
	{
		$track=explode('.', $track_string);
		if (count($track)==1) $track=reset($track);
		return $track;
	}
	
	protected function parse()
	{
		if (!preg_match(static::PATHWAY_EX, $this->content, $m)) throw new \Exception('BAD PATHWAY 2: '.$this->content);
		if ($m['anchor']!=='') $this->track=$this->special_track($m['anchor'], $m['track']);
		else $this->track=$this->standard_track($m['track']);
		
		$rest=mb_substr($this->content, mb_strlen($m[0]));
		if (!empty($rest)) $this->parse_tail($rest);
		
		return
		[
			'track'=>$this->track
		];
	}
	
	protected function parse_tail($tail)
	{
		throw new \Exception('Excessive tail');
	}
}
?>