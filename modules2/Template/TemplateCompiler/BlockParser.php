<?

namespace Pokeliga\Template;

abstract class BlockParser extends Subparser
{
	static
		$brackets='replace_me';
	
	public static function compile_processed($data, ParserCommon $common)
	{
		return $data['result_with_brackets'];
	}
	
	/*
	parse() Возвращает массив, описывающий блок, в формате:
		'result'				- содержимое блока.
		'result_with_brackets'	- содержимое вместе со внешними границами (часто <? ?>)
	*/
}

class BlockParser_keyword extends BlockParser
{
	static
		$brackets=['{{', '}}'];
		
	protected function parse()
	{
		$keyword=PathwayParser_keyword::process($this->content, $this->common);
		
		$result='$this->keyword('.var_export($keyword['track'], true).(($keyword['line']===null)?(''):(', '.$keyword['line'])).');';
		return
		[
			'result'=>$result,
			'result_with_brackets'=>'<? '.$result.' ?>'
		];
	}
}

class BlockParser_comment extends BlockParser
{
	static
		$brackets=['<!--', '-->'];
		
	protected function parse()
	{
		return
		[
			'result'=>'',
			'result_with_brackets'=>''
		];
	}
}

class BlockParser_code extends BlockParser
{
	static
		$brackets=['{|', '|}'],
		$possible_commands=['if', 'else', 'elseif', 'echo'];
	
	protected function parse()
	{
		$commands=static::parse_commands($this->content);
		
		static $all_commandex=null;
		if ($all_commandex===null)
		{
			$all_commandex=[];
			foreach (static::$possible_commands as $type)
			{
				$class='Pokeliga\Template\CodeParser_'.$type;
				$all_commandex[]=['ex'=>$class::RECOGNIZE_EX, 'type'=>$type, 'class'=>$class];
			}
		}

		$sequence=[];
		$result=[];
		foreach ($commands as $command)
		{
			$recognized=false;
			foreach ($all_commandex as $commandex)
			{
				if (!preg_match($commandex['ex'], $command)) continue;
				
				$class=$commandex['class'];
				$parsed=$class::process($command, $this->common);
				$sequence[]=$parsed['codefrag_id'];
				$result[]=$class::compile_processed($parsed, $this->common);
				$recognized=true;
				break;
			}
			if (!$recognized) throw new \Exception('BAD COMMAND: '.$command);
		}
		
		$result=implode(' ', $result);
		return ['result'=>$result, 'result_with_brackets'=>'<? '.$result.'?>', 'sequence'=>$sequence];
	}
}

?>