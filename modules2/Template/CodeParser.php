<?
namespace Pokeliga\Template;

function parse_blocks($text, $brackets=['(', ')'], &$has_blocks)
{
	$has_blocks=false;
	if (!is_array(reset($brackets))) $brackets=[$brackets];
	
	$offset=0;
	$strings=[];
	$next_string_key=0;
	$parsed=[];
	$next_codefrag_id=0;
	$codefrags=[];
	while ($offset<mb_strlen($text))
	{
		$st=false;
		$br=null;
		foreach ($brackets as $key=>$set)
		{
			$s=mb_strpos($text, $set[0], $offset);
			if ($s===false) continue;
			if ( ($st===false) || ($s<$st) )
			{
				$st=$s;
				$br=$key;
			}
		}
		if ($st===false)
		{
			$parsed[]=mb_substr($text, $offset);
			break;
		}
		
		$parsed[]=mb_substr($text, $offset, $st-$offset);
		$offset=$st;
		$block=parse_block($text, $offset, $br, $strings, $brackets, $next_codefrag_id);
		$has_blocks=true;
		if (!empty($block['codefrags'])) $codefrags+=$block['codefrags'];
		$block=$block['result_with_brackets'];
		
		if (is_numeric($br)) $marker=$set[0]; else $marker=$br;
		$parsed[]=[$marker, $block];
	}
	
	$result='';
	foreach ($parsed as $block)
	{
		if (is_string($block)) $result.=$block;
		elseif (is_array($block)) $result.=$block[1];
		else die ('BAD PARSED BLOCK');
	}
	
	ksort($codefrags);
	$eval_once=implode(' ', $codefrags);
	return
	[
		'result'=>$result,
		'eval_once'=>$eval_once
	];
}

// эта функция вынуждена перебирать блок посимвольно, потому что в нём могут быть строки, а в строках - что угодно, похожее на технические конструкции.
function parse_block($text, &$offset, $block_type, &$strings, $brackets=[['(', ')']], &$next_codefrag_id)
{
	$set=$brackets[$block_type];
	if (mb_strpos($text, $set[0], $offset)!==$offset) die ('BAD BLOCK 1 ['.$block_type.', '.$offset.']: '.$text);
	
	$offset+=mb_strlen($set[0]);
	$next_closing_bracket=mb_strpos($text, $set[1], $offset);
	if ($next_closing_bracket===false) die ('BAD BRACKETS 1');
	$next_opening_bracket=mb_strpos($text, $set[0], $offset);
	
	$block='';
	$char=null;
	$depth=1;
	
	for ($pos=$offset; $pos<mb_strlen($text); $pos++)
	{
		if ($pos===$next_closing_bracket)
		{
			$depth--;
			if ($depth===0)
			{
				$offset=$pos+mb_strlen($set[1]);
				$func='parse_block_'.$block_type;
				return $func($block, $strings, $next_codefrag_id);
			}
			else
			{
				$block.=$set[1];
				$next_closing_bracket=mb_strpos($text, $set[1], $pos+mb_strlen($set[1]));
				$pos+=mb_strlen($set[1])-1;						
				continue;
			}
		}
		elseif ($pos===$next_opening_bracket)
		{
			$depth++;
			$block.=$set[0];
			$next_opening_bracket=mb_strpos($text, $set[0], $pos+mb_strlen($set[0]));
			$pos+=mb_strlen($set[0])-1;
			continue;
		}
		
		$char=mb_substr($text, $pos, 1);
		if ($char==="'")
		{
			$offset=$pos;
			$block.=parse_string($text, $offset, $strings);
			if ($next_closing_bracket<$offset)
			{
				$next_closing_bracket=mb_strpos($text, $set[1], $offset);
				if ($next_closing_bracket===false) die ('BAD BRACKETS 2');
			}
			if ($next_opening_bracket<$offset)
			{
				$next_opening_bracket=mb_strpos($text, $set[0], $offset);
			}
			$pos=$offset-1;
		}
		else $block.=$char;	
	}
	vdump($text);
	die ('BAD BLOCK 2');
}

function parse_string($text, &$offset, &$strings)
{
	if (mb_substr($text, $offset, 1)!=="'") die ('BAD STRING 1');
	if (mb_strpos($text, "'", $offset+1)===false) die ('BAD STRING 2: '.htmlspecialchars($text));
	
	$offset++;
	$char=null;
	$screen=0;
	$string='';
		
	static $screenable=['"', "'", '\\'];
	for ($pos=$offset; $pos<mb_strlen($text); $pos++)
	{
		if ($screen>0) $screen++; // 2 - cледующий символ после слеша.
		if ($screen>=3) $screen=0;
			
		$char=mb_substr($text, $pos, 1);
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
			if (empty($strings)) $key=0;
			else $key=max(array_keys($strings))+1;
			$strings[$key]=$string;
			$offset=$pos+1;
			return "'".$key."'";
		}
		else $string.=$char;
	}
	die('BAD STRING 3');
}

/*
принимает содержимое {{фигурных скобок}}, а возврашает:
[
	'track'	=> массив или строка
	'line'	=> null или готовая строка для eval,
	'codefrags'=> массив, возможно, пустой
]
*/
function parse_keyword($brackets_content, $strings, &$next_codefrag_id, &$next_precalc_id=null)
{
	static $by_code=1, $by_keyword=2;
	$mode=null;
	
	if (!preg_match('/^(?<master_pointer>[\#@])?(?<track>[a-z\d\._]+)(\||$)/i', $brackets_content, $m)) die ('BAD KEYWORD 2: '.$brackets_content);
	if ($m['master_pointer']==='#')
	{
		$track=[$m['master_pointer'], $m['track']];
		$mode=$by_code;
	}
	else
	{
		$track=explode('.', $m['track']);
		if (!empty($m['master_pointer'])) array_unshift($track, $m['master_pointer']);
		if (count($track)==1) $track=reset($track);
		$mode=$by_keyword;
	}
	
	// STUB! здесь должна быть возможность уточнения типа {{pokemon[id=1].sprite}} и даже {{pokemon.former_owners[nickname~A*].linked_nickname}}.
	/*
	if (mb_substr($brackets_content, mb_strlen($m[0]), 1)==='[')
	{
	}
	*/
	
	$codefrags=[];
	$final_line=null;
	$rest=mb_substr($brackets_content, mb_strlen($m[0]));
	// в формулах, являющихся частью ключевых слов, следует употреблять or вметсто |, обозначая логическое ИЛИ.
	
	if (empty($rest)) $commandline=null;
	else
	{
		$commandline=parse_commands($rest, '|', ['{{', '}}'] );
		$final_line=[];
		$next_numbered_element=0;
		foreach ($commandline as $element)
		{
			if (preg_match('/^\s*(?<key>[a-z\d\_]+)(?<op>:?=)(?<value>.*)$/', $element, $m))
			{
				if ($m['op']===':=')
				{
					$sub_next_codefrag_id=$next_codefrag_id;
					$subexpr=CodeFragment_expression::parse_instance($m['value'], $strings, $sub_next_codefrag_id, $main_frag_id);
					if ($subexpr instanceof \Report_resolution)
					{
						$value=var_export($subexpr->resolution, true);
						$m['op']='=';
					}
					elseif ($subexpr instanceof \Report_impossible) die ('BAD SUBEXPR');
					else
					{
						$next_codefrag_id=$sub_next_codefrag_id;
						if (is_array($subexpr)) $codefrags+=$subexpr;
						else $codefrags[$main_frag_id]=$subexpr;
						
						if ($next_precalc_id!==null)
						{
							$subexpr_precalc_id=$next_precalc_id++;
							$precalc[$subexpr_precalc_id]='new Compacter_codefrag_reference('.$main_frag_id.')';
							$value='$_PRECALC['.$subexpr_precalc_id.']';
						}
						else $value='new Compacter_codefrag_reference('.$main_frag_id.')';
					}
				}
				elseif ($m['op']==='=')
				{
					$value=var_export($m['value'], true);
				}
				$final_line[]=var_export($m['key'], true).'=>'.$value;
			}
			elseif ($element!=='')
			{
				$final_line[]=($next_numbered_element++).'=>'.var_export($element, true);
			}
		}
		$final_line='['.implode(', ', $final_line).']';
	}
	
	return
	[
		'track'=>$track,
		'line'=>$final_line,
		'codefrags'=>$codefrags
	];
}

function parse_block_code($block, $strings, &$next_codefrag_id)
{
	$commands=parse_commands($block);
//	echo 'COMMANDS'; var_dump($commands);
	
	static $all_commandex=null;
	if ($all_commandex===null)
	{
		$all_commandex=[];
		foreach (CodeFragment_command::$possible_commands as $type)
		{
			$prototype=CodeFragment::get_prototype($type);
			$ex=$prototype::$basic_commandex;
			if (!is_array($ex)) $ex=[$ex];
			foreach ($ex as $x)
			{
				$all_commandex[]=['ex'=>$prototype::basic_commandex(), 'type'=>$type];
			}
		}
	}

	$codefrags=[];
	$sequence=[];
	foreach ($commands as $command)
	{
		$recognized=false;
		foreach ($all_commandex as $commandex)
		{
			if (!preg_match($commandex['ex'], $command)) continue; // опрашивает насчёт каждой команды по очереди различные операторы.
			$prototype=CodeFragment::get_prototype($commandex['type']);
			
			$main_frag_id=null;
			$codefrag=$prototype::parse_instance($command, $strings, /* все последующие - ссылки */ $next_codefrag_id, $main_frag_id);
			// возвращает массив ТЕКСТОВЫХ представлений фрагментов кода, необходимых для выполнения команды. Кроме того, увеличивается счётчик айди фрагментов, а в переменную $main_frag_id записывается айди корневой команды.

			if ($codefrag===null) continue;
			if (is_array($codefrag)) $codefrags+=$codefrag;
			else $codefrags[$main_frag_id]=$codefrag;
			if ($main_frag_id!==null) $sequence[]=$main_frag_id;
			$recognized=true;
			break;
		}
		if (!$recognized) { vdump($commands); die ('BAD COMMAND: '.$command); }
	}
	
	if (count($sequence)==0) return 'EMPTY COMMANDS';
	
	$result='';
	foreach ($sequence as $id)
	{
		$result.='$this->command('.$id.'); ';
	}
	return ['result'=>$result, 'result_with_brackets'=>'<? '.$result.'?>', 'codefrags'=>$codefrags, 'sequence'=>$sequence];
}

function parse_block_keyword($block, $strings, &$next_codefrag_id)
{
	$keyword_next_codefrag_id=$next_codefrag_id;
	$keyword=parse_keyword($block, $strings, $keyword_next_codefrag_id);
	if ($keyword===null) die ('BAD KEYWORD 3: '.$block);
	$next_codefrag_id=$keyword_next_codefrag_id;
	
	$result='$this->keyword('.var_export($keyword['track'], true).(($keyword['line']===null)?(''):(', '.$keyword['line'])).');';
	return
	[
		'result'=>$result,
		'result_with_brackets'=>'<? '.$result.'?>',
		'codefrags'=>$keyword['codefrags']
	];
}

function parse_block_comment($block, $strings, &$next_codefrag_id)
{
	return
	[
		'result'=>'',
		'result_with_brackets'=>'',
		'codefrags'=>[]
	];
}

// эта фунцкия рассчитывает на то, что строки уже были запакованы, так что все скобки - это действительно технические конструкции.
function brackets_content($text, $brackets=['{', '}'], $start=0, $include_brackets=false)
{
	if ($start>0) $text=mb_substr($text, $start);
	if (mb_substr($text, 0, mb_strlen($brackets[0]))!==$brackets[0]) die ('BAD BRACKETS BLOCK');
	if ( substr_count($text, $brackets[0])!==substr_count($text, $brackets[1])) die ('BAD BRACKETS 3: '.$text);
	
	$offset=1;
	$depth=1;
	$next_opening_bracket=null;
	$next_closing_bracket=null;
	
	while ($offset<mb_strlen($text))
	{
		if ($next_opening_bracket===null) $next_opening_bracket=mb_strpos($text, $brackets[0], $offset);
		if ($next_closing_bracket===null) $next_closing_bracket=mb_strpos($text, $brackets[1], $offset);
//		echo 'NEXT [ '.$next_opening_bracket.', NEXT ] '.$next_closing_bracket.'<br>';
		
		if ( ($next_opening_bracket!==false) && ($next_opening_bracket<$next_closing_bracket) )
		{
			$depth++;
			$offset=$next_opening_bracket+mb_strlen($brackets[0]);
			$next_opening_bracket=null;
		}
		elseif
		(
			($next_closing_bracket!==false) &&
			( ($next_opening_bracket===false) || ($next_closing_bracket<$next_opening_bracket) )
		)
		{
			if ($depth==0) die ('BAD BRACKETS 4');
			$depth--;
			if ($depth==0)
			{
				$result=mb_substr($text, mb_strlen($brackets[0]), $next_closing_bracket-mb_strlen($brackets[0]));
				if ($include_brackets) $result=$brackets[0].$result.$brackets[1];
				return $result;
			}
			
			$offset=$next_closing_bracket+mb_strlen($brackets[1]);
			$next_closing_bracket=null;
		}
		else die ('BAD BRACKETS 5');
	}
	
	die('BAD BRACKETS 6');
}

function parse_commands($block, $breaker=';', $brackets=['{', '}'])
{
	$block=trim($block);
	
	$next_breaker=mb_strpos($block, $breaker);
	if ($next_breaker===false) return tidy_commands([$block]);
	if (mb_substr($block, -mb_strlen($breaker))!==$breaker) $block.=$breaker;
	if ( ($brackets_count=substr_count($block, $brackets[0]))!==substr_count($block, $brackets[1])) die ('BAD BRACKETS 7');
	if ( $brackets_count==0 ) return tidy_commands(array_slice(explode($breaker, $block), 0, -1));
	
	$offset=0;	
	$next_opening_bracket=null;
	$next_closing_bracket=null;
	$command_start=0;

	$commands=[];
	$tries=100;
	$try=0;
	while ( ($offset<mb_strlen($block)) && (++$try<=$tries) )
	{
//		echo 'OFFSET '.$offset.'<br>';
		if ( ($next_breaker===null) || ($next_breaker<$offset) )  $next_breaker=mb_strpos($block, $breaker, $offset);
		if ($next_opening_bracket===null) $next_opening_bracket=mb_strpos($block, $brackets[0], $offset);
		if ($next_closing_bracket===null) $next_closing_bracket=mb_strpos($block, $brackets[1], $offset);
//		echo 'NEXT ; '.$next_breaker.', NEXT ( '.$next_opening_bracket.', NEXT ) '.$next_closing_bracket.'<br>';
	
		if
		(
			($next_closing_bracket!==false) &&
			($next_closing_bracket<$next_opening_bracket) &&
			($next_closing_bracket<$next_breaker)
		) // открывающая скобка, если находится внутри команды, не может быть до закрывающей.
			die ('BAD BRACKETS 8');
		elseif ( ($next_opening_bracket===false) || ($next_breaker<$next_opening_bracket) )
		{
			$commands[]=mb_substr($block, $command_start, $next_breaker-$command_start);
			$offset=$next_breaker+mb_strlen($breaker);
			$command_start=$next_breaker+1;
			$next_breaker=null;
		}
		else // elseif ($next_opening_bracket<$next_closing_bracket)
		{
			$brackets_block=brackets_content($block, $brackets, $next_opening_bracket, true);
			if ( ( $brackets[1]!=='}') || (mb_substr($brackets_block, -2*mb_strlen($brackets[1]), mb_strlen($brackets[1]))===$brackets[1]) )
			{
				$offset=$next_opening_bracket+mb_strlen($brackets_block);
				$next_closing_bracket=null;
				$next_opening_bracket=null;
				continue;
			}
			$commands[]=mb_substr($block, $command_start, $next_opening_bracket-$command_start).$brackets_block;
			$command_start=$next_opening_bracket+mb_strlen($brackets_block);
			if (mb_substr($block, $command_start, mb_strlen($breaker))===$breaker) $command_start++;
			$offset=$command_start;
			$next_closing_bracket=null;
			$next_opening_bracket=null;
		}
	}
	
	if ($try>$tries) die ('ENDLESS LOOP');
	
	return tidy_commands($commands);
}

function tidy_commands($commands)
{
	return $commands; // STUB
	foreach ($commands as $key=>&$command)
	{
		$command=trim($command);
		if (empty($command)) unset($commands[$key]);
	}
	return $commands;
}

abstract class CodeFragment extends Task
{
	use Prototyper;
	
	static
		$prototype_class_base='CodeFragment_';
	
	public
		$host=null, // шаблон или иное кодохранилище
		$args,
		$frag_id;
		
	public function progress()
	{
		die('PARSER ONLY');
	}
	
	public function run_step()
	{
		die('PARSER ONLY');
	}
}

class CodeFragment_expression extends CodeFragment
{
	const
		UNARY_OP	=0,
		BINARY_OP	=1,
		TERNARY_OP	=2;
		
	static
		$ex='[\(\s\-\+!]*([@\$\#]?[a-z\d]+|\d+|\d*\.\d+|\'\d+\'|\{\{)',
		$operandex=
		[
			/* скобки в выражении	*/ 'brackets'=>'/^\s*\(/',
			/* переменная на $		*/ 'var'=>'/^\s*\$[a-z\d_]/i',
			/* число				*/ 'number'=>'/^\s*\-?(\d{0,10}\.\d{1,10}|\d{1,10})/',
			/* строка на '			*/ 'string'=>'/^\s*\'(?<code>\d{0,4})\'/',
//			/* вызовы функции		*/ 'func'=>'/^\s*[a-z][a-z\d_]*\(/i',
			/* название шаблона		*/ 'keyword'=>'/^\s*\{\{[#\.]?[\.a-z\d_]+/i',
			/* значение среды		*/ 'value'=>'/^\s*@[#\.]?[\.a-z\d_]+/i',
			/* массив				*/ 'array'=>'/^\s*\[/',
			/* использовать буквально*/  'same'=>'/^\s*(null|true|false)/'
		],
		$simple_ops=
		[
			CodeFragment_expression::UNARY_OP=>['!'],
			CodeFragment_expression::BINARY_OP=>
				['<>', '!=', '===', '!==', '*', '/', ' xor', '%', '**', '>=', '<=', '=>', '=<', '>', '<', '&', '|', ' and ', ' or ',  '.', '==', '='],
		],
		$special_ops=
		[
			CodeFragment_expression::UNARY_OP=>['ariphmetic'=>'[\-\+]+'],
			CodeFragment_expression::BINARY_OP=>['ariphmetic'=>'[\-\+]+']
		],
		$op_convert=['='=>'==', '^'=>'**', '=>'=>'>=', '=<'=>'<=', 'and'=>'&&', '&'=>'&&', 'or'=>'||', '|'=>'||', '<>'=>'!='];
		
	public
		$operands=[];
		
	public static function ex()
	{
		return static::$ex;
	}
		
	public static function parse_instance($expression, $strings, &$next_codefrag_id, &$main_frag_id=null)
	{
		return static::make_instance($expression, $strings, $next_codefrag_id, $main_frag_id);
	}
	
	public static function make_instance($content, $strings, &$next_codefrag_id, &$main_frag_id=null)
	{
		$parsed=static::parse_expression($content, $strings, $next_codefrag_id);
		
		if ($parsed instanceof \Report) return $parsed;
		
		$codefrags=$parsed['codefrags'];
		$args=['expression'=>$parsed['expression'], 'precalc'=>$parsed['precalc']];
		
		$main_frag_id=$next_codefrag_id++;
		
		
		$result='$this->codefrag(\'expression\', '.$main_frag_id.', [\'expression\'=>'.var_export($parsed['expression'], true);
		if (!empty($parsed['precalc']))
		{
			$precalc=[];
			foreach ($parsed['precalc'] as $key=>$desc)
			{
				$precalc[]=$key.'=>'.$desc;
			}
			$result.=', \'precalc\'=>['.implode(', ', $precalc).']';
		}
		$result.=']);';
		$codefrags[$main_frag_id]=$result;
		return $codefrags;
	}
	
	public static function parse_expression($content, $strings, &$next_codefrag_id)
	{
		$offset=0;
		$content=trim($content);
		$result='';
		$constant=true;
		$codefrags=[];
		$precalc=[];
		$next_precalc_id=0;
		$start=true;
		static
			$unary_op_mode=0 /* возможен унарный оператор */,
			$operand_mode=1, /* ожидается операнд*/			
			$op_mode=2; /* ожидается операция или конец выражения */
			
		$mode=$unary_op_mode;
		
		while ($offset<mb_strlen($content))
		{
			$rest=mb_substr($content, $offset);
			// echo 'REST '; var_dump($rest); echo ', MODE '.$mode.'<br>';
			if (preg_match('/^\s*$/', $rest)) // если выражения не осталось...
			{
				if ($mode===$op_mode) break; // разобрали операнд, после него выражение может заканчиваться.
				die('BAD EXPRESSION'); // выражение не может заканчиваться сразу после начала или после знака.
			}
			
			if ($mode===$operand_mode) // ожидается операнд
			{			
				$operand_next_codefrag_id=$next_codefrag_id;
				$operand_next_precalc_id=$next_precalc_id; //чтобы не портить эти переменные в случае, если ошибка обнаружится в процессе выполнения.
				$operand=static::parse_operand($rest, $strings, $operand_next_codefrag_id, $operand_next_precalc_id);
				// echo 'OPERAND<br>'; var_dump($operand);
				
				if (!$operand['constant']) $constant=false;
				$codefrags+=$operand['codefrags'];
				$precalc+=$operand['precalc'];
				$next_codefrag_id=$operand_next_codefrag_id;
				$next_precalc_id=$operand_next_precalc_id;
				if ($operand['replace']!==null) $result.=$operand['replace'];
				else $result.=mb_substr($rest, 0, $operand['span']);
				$offset+=$operand['span'];
				$mode++;
				
				// echo 'RESULT '; var_dump($result);
			}
			else
			{
				$op=static::parse_op($rest, (($mode===$unary_op_mode)?(static::UNARY_OP):(static::BINARY_OP)) );
				if ($op===null)
				{
					if ($mode===$unary_op_mode) $mode++;
					else { vdump($mode); die ('BAD OP, expr: '.$content); }
				}
				else
				{
					$result.=$op['replace'];
					$offset+=$op['span'];
					if ($mode===$op_mode) $mode=$unary_op_mode;
					elseif ($mode===$unary_op_mode) $mode++;
					else die ('BAD MODE');
					
					// echo 'RESULT '; var_dump($result);					
				}
			}			
		}
		
		if ($constant) return new \Report_resolution(eval('return '.$result.';'));
		
		$return=
		[
			'expression'=>$result,
			'precalc'=>$precalc,
			'codefrags'=>$codefrags
		];
//		echo 'RETURN '; var_dump($return);
		return $return;
	}
	
	/*
	формат ответа об операторе:
	
	[
		'replace'	=> null (не менять) или строка (чем заменить операнд в выражении.
		'span'		=> длина изначального операнда в тексте, чтобы знать, откуда требуется дальнейшая обработка.
	];
	
	*/
	
	// STUB: пока не предусмотрены тринарные операторы (как a?b:c), а также перевод новых, не предусмотренных операторов операторов в функции.
	public static function parse_op($content, $type=CodeFragment_expression::BINARY_OP)
	{
		static $convert=[CodeFragment_expression::UNARY_OP=>'unary', CodeFragment_expression::BINARY_OP=>'binary'];
		if (!array_key_exists($type, $convert)) die ('BAD OP TYPE');
	
	
		foreach (static::$special_ops[$type] as $key=>$special_ex)
		{
			if (preg_match('/^\s*'.$special_ex.'/', $content))
			{
				$method='parse_special_op_'.$key;
				$result=static::$method($content, $type);
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
					'replace'	=>static::normalize_op($op),
					'span'		=>mb_strlen($m[0])
				];
			}
		}
	}
	
	public static function parse_special_op_ariphmetic($content, $type)
	{
		if (!preg_match('/^\s*'.static::$special_ops[$type]['ariphmetic'].'/', $content, $m)) die ('BAD ARIPHMETIC');
		$minusi=substr_count($content, '-');
		$span=mb_strlen($m[0]);
		if (($minusi % 2)==1) return ['replace'=>'-', 'span'=>$span];
		elseif ($type===static::BINARY_OP) return ['replace'=>'+', 'span'=>$span];
		else return ['replace'=>'', 'span'=>$span];
	}
	
	public static function normalize_op($op)
	{
		if (array_key_exists($op, static::$op_convert)) return static::$op_convert[$op];
		return $op;
	}
	
	/*
	формат ответа об операнде (все элементы должны присутствовать):
	
	[
		'replace'	=> null (не менять) или строка (чем заменить операнд в выражении.
		'codefrags'	=> массив (обязательно!) новых кодофрагментов.
		'span'		=> длина изначального операнда в тексте, чтобы знать, откуда требуется дальнейшая обработка.
		'constant'	=> если true, то значение этого операнда постоянно (не функция, не переменная и т.д.).
		'precalc'	=> массив предварительных задач в формате [айди=>'строковое описание'], например, [1=>'new Compacter_template_keyword(['pokemon','id'])']
	];
	
	*/
	
	public static function parse_operand($content, $strings, &$next_codefrag_id, &$next_precalc_id)
	{
		$codefrags=[];
		$next_operand_id=0;
		$precalc=[];
		$operand=null;

		$recognized=false;
		foreach (static::$operandex as $type=>$ex)
		{
			if (!preg_match($ex, $content)) continue;
			$func='parse_operand_'.$type;
			$operand_next_codefrag_id=$next_codefrag_id;
			$operand_next_precalc_id=$next_precalc_id;
			$operand=static::$func($content, $strings, $operand_next_codefrag_id, $operand_next_precalc_id);
			if ($operand===null) continue;
			
			$recognized=true;
			$next_codefrag_id=$operand_next_codefrag_id;
			$next_precalc_id=$operand_next_precalc_id;
			break;
		}
		if (!$recognized) die('BAD OPERAND: '.$content);
		return $operand;
	}
	
	public static function parse_operand_var($content, $strings, &$next_codefrag_id, &$next_precalc_id)
	{
		if (!preg_match('/^(?<base>\s*\$(?<var_name>[a-z\d_]+))(?<rest>.*)$/i', $content, $m)) return;
		
		$codefrags=[];
		$var_name=$m['var_name'];
		$replacement='$this->var(\''.$var_name.'\')';
		$operand_span=mb_strlen($m['base']);
		
		$keys=[];
		$codefrags=[];
		$rest=$m['rest'];
		while (mb_substr($rest, 0, 1)==='[')
		{
			die('UNIMPLEMENTED YET');
			$expression=brackets_content($rest, ['[', ']']);
			$next_expr_id=$next_codefrag_id;
			$codefrag=parse_instance($expression, $next_expr_id, $main_expr_id);
			
		}
		
		return
		[
			'replace'	=> $replacement,
			'span'		=> $operand_span,
			'constant'	=> false,
			'codefrags'	=> $codefrags,
			'precalc'	=> []
		];
	}
	
	public static function parse_operand_number($content, $strings, &$next_codefrag_id, &$next_precalc_id)
	{
		if (!preg_match(static::$operandex['number'], $content, $m)) return;
		return
		[
			'replace'	=> (float)$m[0],
			'span'		=> mb_strlen($m[0]),
			'constant'	=> true,
			'codefrags'	=> [],
			'precalc'	=> []
		];
	}
	
	public static function parse_operand_brackets($content, $strings, &$next_codefrag_id, &$next_precalc_id)
	{
		$brackets=['(', ')'];
		$offset=mb_strpos($content, $brackets[0]);
		$brackets_content=brackets_content($content, $brackets, $offset);
		
		$codefrags=[];
		$sub_next_codefrag_id=$next_codefrag_id;
		$subexpr=static::parse_instance($brackets_content, $strings, $sub_next_codefrag_id, $main_frag_id);
		if ($subexpr instanceof \Report_resolution)
			return
			[
				'replace'	=> $subexpr->resolution,
				'span'		=> mb_strlen($content)+mb_strlen($brackets[0])+mb_strlen($brackets[1]),
				'constant'	=> true,
				'codefrags'	=> [],
				'precalc'	=> []
			];
		
		if ($subexpr instanceof \Report_impossible) die ('BAD SUBEXPR');
		$next_codefrag_id=$sub_next_codefrag_id;		
		$subexpr_precalc_id=$next_precalc_id++;
		$codefrags+=$subexpr;
		return
		[
			'replace'	=> '$_PRECALC['.$subexpr_precalc_id.']',
			'span'		=> mb_strlen($brackets_content)+mb_strlen($brackets[0])+mb_strlen($brackets[1])+$offset,
			'constant'	=> false,
			'codefrags'	=> $codefrags,
			'precalc'	=> [$subexpr_precalc_id=>'new Compacter_codefrag_reference('.$main_frag_id.')']
		];
	}
	
	public static function parse_operand_array($content, $strings, &$next_codefrag_id, &$next_precalc_id)
	{
		$brackets=['[', ']'];
		$offset=mb_strpos($content, $brackets[0]);
		$brackets_content=brackets_content($content, $brackets, $offset);
		
		$elements=parse_commands($brackets_content, $breaker=',', $brackets=['[', ']']);
		
		$array=[];
		$codefrags=[];
		foreach ($elements as $element)
		{
			$sub_next_codefrag_id=$next_codefrag_id;
			if (preg_match('/^\s*(?<key>\'\d+\'|\d+)\s*=>(?<value>.+)$/', $element, $m))
			{
				$key=static::parse_operand($m['key'], $strings, $sub_next_codefrag_id, $next_precalc_id);
				if ($key['constant']!==true) die ('UNIMPLEMENTED YET: variable_array');
				
				$value=static::parse_instance($m['value'], $strings, $sub_next_codefrag_id, $main_frag_id);
				if (!($value instanceof \Report_resolution)) die ('UNIMPLEMENTED YET: variable_array');
				
				$array[]=$key['replace'].'=>'.var_export($value->resolution, true);
			}
			else
			{
				$value=static::parse_instance($element, $strings, $sub_next_codefrag_id, $main_frag_id);
				if ($value instanceof \Report_resolution) $array[]=var_export($value->resolution, true);
				else die ('UNIMPLEMENTED YET: variable_array');
			}
		}
		
		return
		[
			'replace'	=> '['.implode(', ', $array).']',
			'span'		=> mb_strlen($brackets_content)+mb_strlen($brackets[0])+mb_strlen($brackets[1])+$offset,
			'constant'	=> true,
			'codefrags'	=> [],
			'precalc'	=> []
		];
	}
	
	public static function parse_operand_string($content, $strings, &$next_codefrag_id, &$next_precalc_id)
	{
		if (!preg_match(static::$operandex['string'], $content, $m)) return;
		$code=$m['code'];
		if (!array_key_exists($code, $strings)) die ('UNKNOWN STRING');
		return
		[
			'replace'	=> var_export($strings[$code], true),
			'span'		=> mb_strlen($m[0]),
			'constant'	=> true,
			'codefrags'	=> [],
			'precalc'	=> []
		];
	}
	
	public static function parse_operand_keyword($content, $strings, &$next_codefrag_id, &$next_precalc_id)
	{
		$brackets=['{{', '}}'];
		$offset=mb_strpos($content, $brackets[0]);
		$brackets_content=brackets_content($content, $brackets, $offset);
		
		$keyword_next_codefrag_id=$next_codefrag_id;
		$keyword=parse_keyword($brackets_content, $strings, $keyword_next_codefrag_id);
		if ($keyword===null) die ('BAD KEYWORD 1: '.$content);
		$next_codefrag_id=$keyword_next_codefrag_id;
		
		$precalc=[];
		$codefrags=$keyword['codefrags'];
		
		$keyword_precalc_id=$next_precalc_id++;
		$precalc[$keyword_precalc_id]='new Compacter_template_keyword('.var_export($keyword['track'], true).(($keyword['line']!==null)?(', '.$keyword['line']):('')).')';
		
		return
		[
			'replace'	=> '$_PRECALC['.$keyword_precalc_id.']',
			'span'		=> mb_strlen($brackets_content)+$offset+mb_strlen($brackets[0])+mb_strlen($brackets[1]),
			'constant'	=> false,
			'codefrags'	=> $codefrags,
			'precalc'	=> $precalc
		];
	}
	
	public static function parse_operand_value($content, $strings, &$next_codefrag_id, &$next_precalc_id)
	{
		if (!preg_match('/^\s*@(?<track>[a-z\.\d_]+)/i', $content, $m)) die ('BAD VALUE');
		
		// STUB: нужны ещё конструкции типа @pokemon[id=100].levelable
		$track=explode('.', $m['track']);
		if (count($track)==1) $track=reset($track);
		
		$value_precalc_id=$next_precalc_id++;
		$precalc=[];
		$precalc[$value_precalc_id]='new Compacter_template_value('.var_export($track, true).')';
		
		return
		[
			'replace'	=> '$_PRECALC['.$value_precalc_id.']',
			'span'		=> mb_strlen($m[0]),
			'constant'	=> false,
			'codefrags'	=> [],
			'precalc'	=> $precalc
		];
	}
	
	public static function parse_operand_same($content, $strings, &$next_codefrag_id, &$next_precalc_id)
	{
		return
		[
			'replace'	=> trim($content),
			'span'		=> mb_strlen($content),
			'constant'	=> true,
			'codefrags'	=> [],
			'precalc'	=> []
		];
	}
}

abstract class CodeFragment_command extends CodeFragment
{
	use Task_steps;
	
	static
		$possible_commands=['if', 'else', 'elseif', 'echo'],
		$basic_commandex='replace_me';
	
	public static function basic_commandex()
	{
		return static::$basic_commandex;
	}
	
	// ищет в тексте шаблона инструкции и пред-компилирует их.
	public static function parse_instance($command, $strings, &$next_codefrag_id, &$main_frag_id=null)
	{
		die('BAD INSTANCE');
	}
	
}

class CodeFragment_sequence extends CodeFragment_command
{
	public
		$commands=null;
		
	public static function from_codefrag_ids($ids, $main_frag_id)
	{
		return '$this->codefrag(\'sequence\', '.$main_frag_id.', '.var_export($ids, true).');';
	}
}

class CodeFragment_echo extends CodeFragment_command
{
	static
		$basic_commandex=null,
		$full_commandex='/^\s*echo\s*(?<expression>.+?)\s*$/';

	public static function basic_commandex()
	{
		if (static::$basic_commandex===null)
		{
			static::$basic_commandex='/^\s*echo\s*('.CodeFragment_expression::$ex.')/';
		}
		return parent::basic_commandex();
	}

	public static function parse_instance($command, $strings, &$next_codefrag_id, &$main_frag_id=null)
	{
		if (!preg_match(static::$full_commandex, $command, $m)) return;
		return static::make_instance($m['expression'], $strings, $next_codefrag_id, $main_frag_id);
	}
	
	public static function make_instance($content, $strings, &$next_codefrag_id, &$main_frag_id=null)
	{
		$codefrags=[];
		$expr_next_codefrag_id=$next_codefrag_id;
		$expression=CodeFragment_expression::parse_instance($content, $strings, $expr_next_codefrag_id, $expression_frag_id);
		if ($expression instanceof \Report_impossible) return;
		if ($expression instanceof \Report_resolution) $content=var_export($expression->resolution, true);
		else
		{
			$next_codefrag_id=$expr_next_codefrag_id;
			$codefrags+=$expression;
			$content='new Compacter_codefrag_reference('.$expression_frag_id.')';
		}
		$main_frag_id=$next_codefrag_id++;
		$codefrags[$main_frag_id]='$this->codefrag(\'echo\', '.$main_frag_id.', [\'content\'=>'.$content.']);';
		return $codefrags;
	}
}

class CodeFragment_if extends CodeFragment_command
{
	static
		$basic_commandex=null,
		$command='if';
		
	public static function basic_commandex()
	{
		if (static::$basic_commandex===null)
		{
			static::$basic_commandex='/^\s*'.static::$command.'\s*\(('.CodeFragment_expression::$ex.')/';
		}
		return parent::basic_commandex();
	}

	public static function parse_instance($command, $strings, &$next_codefrag_id, &$main_frag_id=null)
	{
		return static::make_instance($command, $strings, $next_codefrag_id, $main_frag_id);
	}
	
	public static function make_instance($content, $strings, &$next_codefrag_id, &$main_frag_id=null)
	{
		$brackets=['(', ')'];
		$offset=mb_strpos($content, $brackets[0]);
//		echo 'CONTENT '; var_dump($content);
		$condition=brackets_content($content, $brackets, $offset);
		$on_true=mb_substr($content, $offset+mb_strlen($condition)+mb_strlen($brackets[0])+mb_strlen($brackets[1]));
		
		$codefrags=[];
		$expr_next_codefrag_id=$next_codefrag_id;
		$expression=CodeFragment_expression::parse_instance($condition, $strings, $expr_next_codefrag_id, $expression_frag_id);
		if ($expression instanceof \Report_impossible) return;
		if ($expression instanceof \Report_resolution) $condition=var_export($expression->resolution, true);
		else
		{
			$next_codefrag_id=$expr_next_codefrag_id;
			$codefrags+=$expression;
			$condition='new Compacter_codefrag_reference('.$expression_frag_id.')';
		}
		
		if (preg_match('/^(?<spaces>\s*)\{/', $on_true, $m)) $on_true=brackets_content($on_true, ['{', '}'], mb_strlen($m['spaces']));
		$block=parse_block_code($on_true, $strings, $next_codefrag_id);
		$codefrags+=$block['codefrags'];
		if (count($block['sequence'])>1)
		{
			$on_true_codefrag_id=$next_codefrag_id++;
			$codefrags[$on_true_codefrag_id]=CodeFragment_sequence::from_codefrag_ids($block['sequence'], $on_true_codefrag_id);
		}
		else
		{
			$on_true_codefrag_id=reset($block['sequence']);
		}
		
		$main_frag_id=$next_codefrag_id++;
		$codefrags[$main_frag_id]='$this->codefrag(\''.static::$command.'\', '.$main_frag_id.', [\'condition\'=>'.$condition.', \'on_true\'=>new Compacter_codefrag_reference('.$on_true_codefrag_id.')]);';
		return $codefrags;
	}
}

class CodeFragment_elseif extends CodeFragment_if
{
	static
		$basic_commandex=null,	
		$command='elseif';
}

class CodeFragment_else extends CodeFragment_command
{
	static
		$basic_commandex='/\s*else /';
		
	public static function parse_instance($command, $strings, &$next_codefrag_id, &$main_frag_id=null)
	{
		return static::make_instance($command, $strings, $next_codefrag_id, $main_frag_id);
	}
	
	public static function make_instance($content, $strings, &$next_codefrag_id, &$main_frag_id=null)
	{
		preg_match('/^(?<pre>\s*else\s*)/', $content, $m);
		$commands=mb_substr($content, mb_strlen($m['pre']));
		if (mb_substr($content, 0, 1)==='{') $commands=brackets_content($content, ['{', '}'], 1);
		
		$block=parse_block_code($commands, $strings, $next_codefrag_id);
		$codefrags=$block['codefrags'];
		if (count($block['sequence'])>1)
		{
			$commands_codefrag_id=$next_codefrag_id++;
			$codefrags[$sequence_codefrag_id]=CodeFragment_sequence::from_codefrag_ids($block['sequence'], $commands_codefrag_id);
		}
		else
		{
			$commands_codefrag_id=reset($block['sequence']);
		}
		
		$main_frag_id=$next_codefrag_id++;
		$codefrags[$main_frag_id]='$this->codefrag(\'else\', '.$main_frag_id.', [\'commands\'=>new Compacter_codefrag_reference('.$commands_codefrag_id.')]);';
		return $codefrags;
	}
}

?>