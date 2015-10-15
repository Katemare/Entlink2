<?
namespace Pokeliga\Template;

// раскрывает блоки и возаращает результат (включая запускаемое разово описание команд), а также количество найденных блоков.

class TemplateCompiler
{
	protected
		$brackets=['code', 'keyword', 'comment'],
		$source,
		$common,
		
		$result,
		$plain=true;
		
	public static function compile($source, $brackets=null)
	{
		$compiler=new static($source, $brackets);
		return $compiler->parse();
	}
		
	protected function __construct($source, $brackets=null)
	{
		$this->source=$source;
		if ($brackets!==null) $this->brackets=(array)$brackets;
		$this->prepare_brackets();
		$this->common=new ParserCommon();
	}
	
	protected function prepare_brackets()
	{
		$this->brackets=array_fill_keys($this->brackets, []);
		foreach ($this->brackets as $code=>&$data)
		{
			$class='Pokeliga\Template\BlockParser_'.$code;
			$data=$class::$brackets;
			$data['class']=$class;
		}
	}
	
	protected function parse()
	{
		$offset=0;
		$parsed=[];
		
		while ($offset<mb_strlen($this->source))
		{
			$bracket_pos=false;
			$bracket_type=null;
			
			// ищет ближайшее начало блока.
			foreach ($this->brackets as $key=>$set)
			{
				$s=mb_strpos($this->source, $set[0], $offset);
				if ($s===false) continue;
				if ($bracket_pos===false or $s<$bracket_pos)
				{
					$bracket_pos=$s;
					$bracket_type=$key;
				}
			}
			
			if ($bracket_pos===false) // начало блока не найдено.
			{
				$parsed[]=mb_substr($this->source, $offset);
				break;
			}
			
			// фрагмент до начала блока добавляется как есть.
			$parsed[]=mb_substr($this->source, $offset, $bracket_pos-$offset);
			$offset=$bracket_pos;
			
			$block=$this->parse_block($offset, $bracket_type);
			$this->plain=false;
			
			$parsed[]=[$bracket_type, $block];
		}
		
		$result='';
		foreach ($parsed as $block)
		{
			if (is_string($block)) $result.=$block;
			elseif (is_array($block)) $result.=$block[1];
		}
		
		return
		[
			'result'=>$result,
			'eval_once'=>$this->common->eval_once()
		];
	}
	
	// эта функция вынуждена перебирать блок посимвольно, потому что в нём могут быть строки, а в строках - что угодно, похожее на технические конструкции.
	// возвращает раскрытый блок.
	protected function parse_block(&$offset, $block_type)
	{
		$set=$this->brackets[$block_type];
		if (mb_strpos($this->source, $set[0], $offset)!==$offset) throw new \Exception('BAD BLOCK 1 ['.$block_type.', '.$offset.']: '.$this->source);
		$offset+=mb_strlen($set[0]);
		$next_closing_bracket=mb_strpos($this->source, $set[1], $offset);
		if ($next_closing_bracket===false) throw new \Exception('BAD BRACKETS 1');
		$next_opening_bracket=mb_strpos($this->source, $set[0], $offset);
		
		$block='';
		$char=null;
		$depth=1;
		
		for ($pos=$offset; $pos<mb_strlen($this->source); $pos++)
		{
			if ($pos===$next_closing_bracket)
			{
				$depth--;
				if ($depth===0)
				{
					$offset=$pos+mb_strlen($set[1]);
					$block_parser=$set['class'];
					return $block_parser::compile($block, $this->common);
				}
				else
				{
					$block.=$set[1];
					$next_closing_bracket=mb_strpos($this->source, $set[1], $pos+mb_strlen($set[1]));
					$pos+=mb_strlen($set[1])-1;
					continue;
				}
			}
			elseif ($pos===$next_opening_bracket)
			{
				$depth++;
				$block.=$set[0];
				$next_opening_bracket=mb_strpos($this->source, $set[0], $pos+mb_strlen($set[0]));
				$pos+=mb_strlen($set[0])-1;
				continue;
			}
			
			$char=mb_substr($this->source, $pos, 1);
			if ($char==="'")
			{
				$offset=$pos;
				$block.=$this->parse_string($offset);
				if ($next_closing_bracket<$offset)
				{
					$next_closing_bracket=mb_strpos($this->source, $set[1], $offset);
					if ($next_closing_bracket===false) throw new \Exception('BAD BRACKETS 2');
				}
				if ($next_opening_bracket<$offset)
				{
					$next_opening_bracket=mb_strpos($this->source, $set[0], $offset);
				}
				$pos=$offset-1;
			}
			else $block.=$char;	
		}
		throw new \Exception('BAD BLOCK 2');
	}

	// получает позицию, на которой обнаружен апостроф; записывает в массив строчек строчку-содержимое между двумя апострофами (позволяет экранировать апостроф); возвращает '#', где # - номер строки, добавленной в массив строчек. Таким образом строчк с произвольным содержимым заменяются на безопасные '1', '2'... которые не содержат мешающих конструкций по скобками.
	protected function parse_string(&$offset)
	{
		if (mb_substr($this->source, $offset, 1)!=="'") throw new \Exception('BAD STRING 1');
		if (mb_strpos($this->source, "'", $offset+1)===false) throw new \Exception('BAD STRING 2: '.htmlspecialchars($this->source));
		
		$offset++;
		$char=null;
		$screen=0;
		$string='';
			
		static $screenable=['"', "'", '\\'];
		for ($pos=$offset; $pos<mb_strlen($this->source); $pos++)
		{
			if ($screen>0) $screen++; // 2 - cледующий символ после слеша.
			if ($screen>=3) $screen=0;
				
			$char=mb_substr($this->source, $pos, 1);
			if ($char==='\\')
			{
				if ($screen) $string.=$char;
				else $screen=1;
			}
			elseif ($screen)
			{
				if (in_array($char, $screenable, true)) $string.=$char;
				else $string.='\\'.$char;
			}
			elseif ($char==="'")
			{
				$string_id=$this->common->add_string($string);
				$offset=$pos+1;
				return "'".$string_id."'";
			}
			else $string.=$char;
		}
		throw new \Exception('BAD STRING 3');
	}

}

?>