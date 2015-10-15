<?

namespace Pokeliga\Template;

class ParserCommon_expression extends ParserCommon
{
	public
		$precalc=[];
	
	public function absorb($common)
	{
		parent::absorb($common);
		if ($common instanceof ParserCommon_expression) $this->precalc+=$common->precalc;
	}
	
	public function add_precalc($content)
	{
		$free_id=$this->free_id($this->precalc);
		$this->precalc[$free_id]=$content;
		return $free_id;
	}
	
	public function create_sub($class=null)
	{
		$sub=parent::create_sub($class);
		if ($class===null or $class===get_class($this)) $sub->precalc=[];
		return $sub;
	}
	
	public function dissolve()
	{
		$this->precalc=[];
		parent::dissolve();
	}
}

class ExpressionParser extends Subparser
{
	const
		UNARY_OP	=0,
		BINARY_OP	=1,
		TERNARY_OP	=2,
		
		MODE_UNARY_OP	=0,
		MODE_OPERAND	=1,
		MODE_OP			=2;
		
	static
		$ex='[\(\s\-\+!]*([@\$\#]?[a-z\d]+|\d+|\d*\.\d+|\'\d+\'|\{\{)',
		$simple_ops=
		[
			self::UNARY_OP=>['!'],
			self::BINARY_OP=>
				['<>', '!=', '===', '!==', '*', '/', ' xor', '%', '**', '>=', '<=', '=>', '=<', '>', '<', '&', '|', ' and ', ' or ',  '.', '==', '='],
		],
		$special_ops=
		[
			self::UNARY_OP=>['ariphmetic'=>'[\-\+]+'],
			self::BINARY_OP=>['ariphmetic'=>'[\-\+]+']
		],
		$op_convert=['='=>'==', '^'=>'**', '=>'=>'>=', '=<'=>'<=', 'and'=>'&&', '&'=>'&&', 'or'=>'||', '|'=>'||', '<>'=>'!='];
	
	public
		$operand_count	=0,
		$ops_count		=0;
	
	public static function compile($content, ParserCommon $common)
	{
		$expr_common=$common->create_sub('Pokeliga\Template\ParserCommon_expression');
		$parser=new static($content, $expr_common);
		$parsed=$parser->parse();
		$result=static::compile_processed($parsed, $expr_common);
		$expr_common->dissolve();
		return $result;
	}
	
	public static function compile_processed($data, ParserCommon $common)
	{
		if ($data['constant'] or $data['mono_operand']) return $data['replace'];
		
		$args='[\'expression\'=>'.var_export($data['replace'], true);
		if (!empty($common->precalc))
		{
			$precalc=[];
			foreach ($common->precalc as $key=>$desc)
			{
				$precalc[]=$key.'=>'.$desc;
			}
			$args.=', \'precalc\'=>['.implode(', ', $precalc).']';
		}
		$args.=']';
		
		$expr_id=$common->add_codefrag('expression', var_export($args, true));
		
		return 'new Compacter_codefrag_reference('.$expr_id.')';		
	}
	
	protected function parse()
	{
		$offset=0;
		$content=trim($this->content);
		$result='';
		$constant=true;
		$start=true;
		
		$mode=static::MODE_UNARY_OP;
		
		while ($offset<mb_strlen($content))
		{
			$rest=mb_substr($content, $offset);
			if (preg_match('/^\s*$/', $rest)) // если выражения не осталось...
			{
				if ($mode===static::MODE_OP) break; // разобрали операнд, после него выражение может заканчиваться.
				throw new \Exception('BAD EXPRESSION'); // выражение не может заканчиваться сразу после начала или после знака.
			}
			
			if ($mode===static::MODE_OPERAND) // ожидается операнд
			{
				$operand=$this->parse_operand($rest);
				$this->operand_count++;
				
				if (!$operand['constant']) $constant=false;
				if ($operand['replace']!==null) $result.=$operand['replace'];
				else $result.=mb_substr($rest, 0, $operand['span']);
				$offset+=$operand['span'];
				$mode++;
			}
			else
			{
				$op=$this->parse_op($rest, (($mode===static::MODE_UNARY_OP)?(static::UNARY_OP):(static::BINARY_OP)) );
				if ($op===null)
				{
					if ($mode===static::MODE_UNARY_OP) $mode++;
					else throw new \Exception('BAD OP, expr: '.$content);
				}
				else
				{
					$this->ops_count++;
					$result.=$op['replace'];
					$offset+=$op['span'];
					if ($mode===static::MODE_OP) $mode=static::MODE_UNARY_OP;
					elseif ($mode===static::MODE_UNARY_OP) $mode++;
					else throw new \Exception('BAD MODE: '.$mode);
				}
			}
		}
		
		$mono=$constant || ($this->operand_count==1 and $this->ops_count==0);
		if ($constant) $result=var_export(eval('return '.$result.';'), true);
		elseif ($mono) $result=$this->common->precalc[0];
		return
		[
			'replace'		=>$result,
			'span'			=>mb_strlen($this->content),
			'constant'		=>$constant,
			'mono_operand'	=>$mono
		];
	}
	
	/*
	формат ответа об операторе:
	
	[
		'replace'	=> строка (чем заменить операнд в выражении.
		'span'		=> длина изначального операнда в тексте, чтобы знать, откуда требуется дальнейшая обработка.
	];
	
	*/
	
	// STUB: пока не предусмотрены тринарные операторы (как a?b:c), а также перевод новых, не предусмотренных операторов операторов в функции.
	public function parse_op($content, $type=self::BINARY_OP)
	{
		static $convert=[self::UNARY_OP=>'unary', self::BINARY_OP=>'binary'];
		if (!array_key_exists($type, $convert)) throw new \Exception('BAD OP TYPE');
	
		foreach (static::$special_ops[$type] as $key=>$special_ex)
		{
			if (preg_match('/^\s*'.$special_ex.'/', $content))
			{
				$method='parse_special_op_'.$key;
				$result=$this->$method($content, $type);
				if (is_array($result)) return $result;
			}
		}
	
		static $ex=[];
		if (!array_key_exists($type, $ex)) $ex[$type]=[];
		foreach (static::$simple_ops[$type] as $op)
		{
			if (!array_key_exists($op, $ex[$type])) $ex[$type][$op]='/^\s*'.preg_quote($op, '/').'/i';
			if (preg_match($ex[$type][$op], $content, $m))
			{
				return
				[
					'replace'	=>$this->normalize_op($op),
					'span'		=>mb_strlen($m[0])
				];
			}
		}
	}
	
	public function parse_special_op_ariphmetic($content, $type)
	{
		if (!preg_match('/^\s*'.static::$special_ops[$type]['ariphmetic'].'/', $content, $m)) throw new \Exception('BAD ARIPHMETIC');
		$minusi=substr_count($content, '-');
		$span=mb_strlen($m[0]);
		if (($minusi % 2)==1) return ['replace'=>'-', 'span'=>$span];
		elseif ($type===static::BINARY_OP) return ['replace'=>'+', 'span'=>$span];
		else return ['replace'=>'', 'span'=>$span];
	}
	
	public function normalize_op($op)
	{
		if (array_key_exists($op, static::$op_convert)) return static::$op_convert[$op];
		return $op;
	}
	
	/*
	формат ответа об операнде (все элементы должны присутствовать):
	
	[
		'replace'	=> null (не менять) или строка (чем заменить операнд в выражении.
		'span'		=> длина изначального операнда в тексте, чтобы знать, откуда требуется дальнейшая обработка.
		'constant'	=> если true, то значение этого операнда постоянно (не функция, не переменная и т.д.).
	];
	
	*/
	
	public function parse_operand($content)
	{
		return OperandParser_generic::process($content, $this->common);
	}

}

?>