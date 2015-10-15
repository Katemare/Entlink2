<?

namespace Pokeliga\Template;

abstract class CodeParser extends Subparser
{
	const
		COMMAND='replace_me',
		RECOGNIZE_EX='/replace_me/';
	
	public static function compile_processed($data, ParserCommon $common)
	{
		return '$this->command('.$data['codefrag_id'].');';
	}
}

class CodeParser_echo extends CodeParser
{
	const
		COMMAND='echo',
		RECOGNIZE_EX='/^\s*echo(\s|\()/',
		FULL_EX='/^\s*echo\s*(?<expression>.+?)\s*$/';
	
	protected function parse()
	{
		if (!preg_match(static::FULL_EX, $this->content, $m)) throw new \Exception('bad echo command');
		
		$expression=ExpressionParser::compile($m['expression'], $this->common);
		return
		[
			'codefrag_id'=>$this->common->add_codefrag('echo', '[\'content\'=>'.$expression.']);')
		];
	}
}

class CodeParser_if extends CodeParser
{
	const
		COMMAND='if',
		RECOGNIZE_EX='/^\s*if\s*\(/';
	
	protected function parse()
	{
		$brackets=['(', ')'];
		$offset=mb_strpos($this->content, $brackets[0]);
		$condition=static::brackets_content($this->content, $brackets, $offset);
		$on_true=mb_substr($this->content, $offset+mb_strlen($condition)+mb_strlen($brackets[0])+mb_strlen($brackets[1]));
		
		$condition=ExpressionParser::compile($condition, $this->common);
		
		if (preg_match('/^(?<spaces>\s*)\{/', $on_true, $m)) $on_true=static::brackets_content($on_true, ['{', '}'], mb_strlen($m['spaces']));
		$block=BlockParser_code::process($on_true, $this->common);
		if (count($block['sequence'])>1) $on_true_codefrag_id=$this->common->add_codefrag('sequence', var_export($block['sequence'], true));
		else $on_true_codefrag_id=reset($block['sequence']);
		
		return
		[
			'codefrag_id'=>$this->common->add_codefrag(static::COMMAND, '[\'condition\'=>'.$condition.', \'on_true\'=>new Compacter_codefrag_reference('.$on_true_codefrag_id.')]);')
		];
	}
}

class CodeParser_elseif extends CodeParser_if
{
	const
		COMMAND='elseif',
		RECOGNIZE_EX='/^\s*elseif\s*\(/';
}

class CodeParser_else extends CodeParser
{
	const
		COMMAND='else',
		RECOGNIZE_EX='/^\s*else\s+/',
		FULL_EX='/^(?<pre>\s*else\s*)/';
		
	protected function parse()
	{
		preg_match(static::FULL_EX, $this->content, $m);
		$commands=mb_substr($this->content, mb_strlen($m['pre']));
		if (mb_substr($this->content, 0, 1)==='{') $commands=static::brackets_content($this->content, ['{', '}'], 1);
		
		$block=BlockParser_code::process($commands, $this->common);
		if (count($block['sequence'])>1) $commands_codefrag_id=$this->common->add_codefrag('sequence', var_export($block['sequence'], true));
		else $commands_codefrag_id=reset($block['sequence']);
		
		return
		[
			'codefrag_id'=>$this->common->add_codefrag(static::COMMAND, '[\'commands\'=>new Compacter_codefrag_reference('.$commands_codefrag_id.')]);')
		];
	}
}

?>