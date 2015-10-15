<?

namespace Pokeliga\Template;

/*
формат ответа об операнде (все элементы должны присутствовать):

[
	'replace'	=> null (не менять) или строка (чем заменить операнд в выражении.
	'span'		=> длина изначального операнда в тексте, чтобы знать, откуда требуется дальнейшая обработка.
	'constant'	=> если true, то значение этого операнда постоянно (не функция, не переменная и т.д.).
];

*/

abstract class OperandParser extends Subparser
{
	const
		RECOGNIZE_EX='replace_me';
}

class OperandParser_generic extends OperandParser
{
	static
		$init=false,
		$operands=['brackets', /* 'var', */ 'number', 'string', /* 'func', */ 'keyword', 'value', 'array', 'symbol'];
		
	public function parse()
	{
		static::init();
		$operand=null;

		$recognized=false;
		foreach (static::$operands as $type=>$data)
		{
			if (!preg_match($data['ex'], $this->content)) continue;
			$class=$data['class'];
			$operand=$class::process($this->content, $this->common);
			$recognized=true;
			break;
		}
		if (!$recognized) throw new \Exception('BAD OPERAND: '.$this->content);
		return $operand;
	}
	
	protected function init()
	{
		if (static::$init) return;
		
		static::$operands=array_fill_keys(static::$operands, []);
		foreach (static::$operands as $operand=>&$data)
		{
			$class='Pokeliga\Template\OperandParser_'.$operand;
			$data['class']=$class;
			$data['ex']=$class::RECOGNIZE_EX;
		}
		
		static::$init=true;
	}
}

class OperandParser_symbol extends OperandParser
{
	const
		RECOGNIZE_EX='/^\s*(null|true|false)/';
		
	public function parse()
	{
		return
		[
			'replace'	=> trim($this->content),
			'span'		=> mb_strlen($this->content),
			'constant'	=> true,
		];
	}
}

class OperandParser_number extends OperandParser
{
	const
		RECOGNIZE_EX='/^\s*\-?(\d{0,10}\.\d{1,10}|\d{1,10})/';
		
	public function parse()
	{
		if (!preg_match(static::RECOGNIZE_EX, $this->content, $m)) return;
		return
		[
			'replace'	=> (float)$m[0],
			'span'		=> mb_strlen($m[0]),
			'constant'	=> true,
		];
	}
}

class OperandParser_string extends OperandParser
{
	const
		RECOGNIZE_EX='/^\s*\'(?<code>\d{0,4})\'/';
		
	public function parse()
	{
		if (!preg_match(static::RECOGNIZE_EX, $this->content, $m)) return;
		$code=$m['code'];
		if (!array_key_exists($code, $this->common->strings)) throw new \Exception('UNKNOWN STRING');
		return
		[
			'replace'	=> var_export($this->common->strings[$code], true),
			'span'		=> mb_strlen($m[0]),
			'constant'	=> true,
		];
	}
}

class OperandParser_array extends OperandParser
{
	const
		RECOGNIZE_EX='/^\s*\[/';
		
	public function parse()
	{
		$brackets=['[', ']'];
		$offset=mb_strpos($this->content, $brackets[0]);
		$brackets_content=static::brackets_content($this->content, $brackets, $offset);
		
		$elements=static::parse_commands($brackets_content, $breaker=',', $brackets=['[', ']']);
		
		$array=[];
		$codefrags=[];
		foreach ($elements as $element)
		{
			if (preg_match('/^\s*(?<key>\'\d+\'|\d+)\s*=>(?<value>.+)$/', $element, $m))
			{
				$key=OperandParser_generic::compile($m['key'], $this->common);
				if ($key['constant']!==true) throw new \Exception('UNIMPLEMENTED YET: variable_array');
				
				$value=ExpressionParser::compile($m['value'], $this->common);
				if (!$value['constant']) throw new \Exception('UNIMPLEMENTED YET: variable_array');
				
				$array[]=$key['replace'].'=>'.$value['replace'];
			}
			else
			{
				$value=ExpressionParser::compile($element, $this->common);
				if (!$value['constant']) throw new \Exception('UNIMPLEMENTED YET: variable_array');
				$array[]=$value['replace'];
				
			}
		}
		
		return
		[
			'replace'	=> '['.implode(', ', $array).']',
			'span'		=> mb_strlen($brackets_content)+mb_strlen($brackets[0])+mb_strlen($brackets[1])+$offset,
			'constant'	=> true,
		];
	}
}

class OperandParser_brackets extends OperandParser
{
	const
		RECOGNIZE_EX='/^\s*\(/';
		
	public function parse()
	{
		$brackets=['(', ')'];
		$offset=mb_strpos($this->content, $brackets[0]);
		$brackets_content=static::brackets_content($this->content, $brackets, $offset);
		
		$span=mb_strlen($brackets_content)+mb_strlen($brackets[0])+mb_strlen($brackets[1]);
		$subexpr=ExpressionParser::process($brackets_content, $this->common);
		
		return
		[
			'replace'	=> $brackets[0].$subexpr['replace'].$brackets[1],
			'span'		=> $span,
			'constant'	=> false
		];
	}
}

/*
class OperandParser_var extends OperandParser
{
	const
		RECOGNIZE_EX='/^\s*\$[a-z\d_]/i';
		
	public function parse()
	{
		if (!preg_match('/^(?<base>\s*\$(?<var_name>[a-z\d_]+))(?<rest>.*)$/i', $this->content, $m)) return;
		
		$var_name=$m['var_name'];
		$replacement='$this->var(\''.$var_name.'\')';
		$span=mb_strlen($m['base']);
		
		$rest=$m['rest'];
		while (mb_substr($rest, 0, 1)==='[')
		{
			die('UNIMPLEMENTED YET: array var');
			// $expression=static::brackets_content($rest, ['[', ']']);
			// $subexpr=ExpressionParser::to_operand($expression, $this->common);
		}
		
		return
		[
			'replace'	=> $replacement,
			'span'		=> $span,
			'constant'	=> false
		];
	}
}

class OperandParser_func extends OperandParser
{
	const
		RECOGNIZE_EX='/^\s*[a-z][a-z\d_]*\(/i';
}
*/

class OperandParser_keyword extends OperandParser
{
	const
		RECOGNIZE_EX='/^\s*\{\{[#\.]?[\.a-z\d_]+/i';
		
	public function parse()
	{
		$brackets=['{{', '}}'];
		$offset=mb_strpos($this->content, $brackets[0]);
		$brackets_content=static::brackets_content($this->content, $brackets, $offset);
		
		$keyword=PathwayParser_keyword::process($brackets_content, $this->common);
		$precalc_id=$this->common->add_precalc('new Compacter_template_keyword('.var_export($keyword['track'], true).(($keyword['line']!==null)?(', '.$keyword['line']):('')).')');
		return
		[
			'replace'	=> '$_PRECALC['.$precalc_id.']',
			'span'		=> mb_strlen($brackets_content)+$offset+mb_strlen($brackets[0])+mb_strlen($brackets[1]),
			'constant'	=> false,
		];
	}
}

class OperandParser_value extends OperandParser
{
	const
		RECOGNIZE_EX='/^\s*@[#\.]?[\.a-z\d_]+/i';
		
	public function parse()
	{
		if (!preg_match('/^\s*@(?<track>[a-z\.\d_]+)/i', $this->content, $m)) throw new \Exception('BAD VALUE');
		
		// STUB: нужны ещё конструкции типа @pokemon[id=100].levelable
		$path=PathwayParser_value::process($m['track'], $this->common);
		$precalc_id=$this->common->add_precalc('new Compacter_value_keyword('.var_export($path['track'], true).')');
		
		return
		[
			'replace'	=> '$_PRECALC['.$precalc_id.']',
			'span'		=> mb_strlen($m[0]),
			'constant'	=> false,
		];
	}
}

?>