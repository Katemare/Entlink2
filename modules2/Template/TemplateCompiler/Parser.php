<?

namespace Pokeliga\Template;

/**
* Преображает текстовый фрагмент из неоткомпилированного шаблона и преображает его во фрагмент откомпилированного кода, который в итоге пойдут в eval().
* Это должен быть фрагмент со строками, свёрнутыми в форму '1', '2' и так далее! Возвращается значение с развёрными строками.
* Содержит статические (потому что stateless) методы, использующиеся большинством классов-парсеров.
*/
abstract class Parser
{
	protected
		$content,
		$common;
	
	protected function __construct($content, ParserCommon $common)
	{
		$this->content=$content;
		$this->common=$common;
	}
	
	/**
	* Принимает содержимое неоткомпилированную строку, а возвращает - откомпилированную.
	* @param string $content
	* @param \Pokeliga\Template\ParserCommon $common Контейнер общих данных компиляции.
	* @return string
	*/
	public static function compile($content, ParserCommon $common)
	{
		$parser=new static($content, $common);
		return $parser->parse();
	}
	
	/**
	* Разбирает неоткомпилированную строку и возвращает разбор этой строки.
	* @return string|array
	*/
	protected abstract function parse();
	
	/**
	* Получает содержимое скобок, учитывая, что в этом содержимом также могут быть открывающеся и закрывающиеся скобки.
	* Эта фунцкия рассчитывает на то, что строки уже были запакованы, так что все скобки - это действительно технические конструкции.
	
	* @param string $text Строка, откуда получить содержимое скобок.
	* @param array $brackets Массив из открывающей и закрывающей скобок (строки).
	* @param int $start Символ, с которого начинать раскрытие. Должен быть позицией открывающей скобки.
	* @param bool $include_brackets Включать ли скобки в возвращаемом значении.
	
	* @return string Содержимое скобок (со скобками, если указан параметр $include_brackets).
	*/
	protected static function brackets_content($text, $brackets=['{', '}'], $start=0, $include_brackets=false)
	{
		if ($start>0) $text=mb_substr($text, $start);
		if (mb_substr($text, 0, mb_strlen($brackets[0]))!==$brackets[0]) throw new \Exception('BAD BRACKETS BLOCK');
		if (substr_count($text, $brackets[0])!==substr_count($text, $brackets[1])) throw new \Exception('BAD BRACKETS 3: '.$text);
		
		$offset=1;
		$depth=1;
		$next_opening_bracket=null;
		$next_closing_bracket=null;
		
		while ($offset<mb_strlen($text))
		{
			if ($next_opening_bracket===null) $next_opening_bracket=mb_strpos($text, $brackets[0], $offset);
			if ($next_closing_bracket===null) $next_closing_bracket=mb_strpos($text, $brackets[1], $offset);
			
			if ($next_opening_bracket!==false and $next_closing_bracket!==false and $next_opening_bracket<$next_closing_bracket)
			// есть открывающая скобка и она раньше следующей закрывающей (которая тоже есть).
			{
				$depth++;
				$offset=$next_opening_bracket+mb_strlen($brackets[0]);
				$next_opening_bracket=null;
			}
			elseif ($next_closing_bracket!==false and ($next_opening_bracket===false or $next_closing_bracket<$next_opening_bracket))
			// есть закрывающая скобка, и следующей открывающей либо нет, либо она позже.
			{
				if ($depth==0) throw new \Exception('BAD BRACKETS 4'); // лишняя закрывающая скобка; хотя вроде бы это невозможно.
				$depth--;
				if ($depth==0) // все скобки закрыты.
				{
					$result=mb_substr($text, mb_strlen($brackets[0]), $next_closing_bracket-mb_strlen($brackets[0]));
					if ($include_brackets) $result=$brackets[0].$result.$brackets[1];
					return $result;
				}
				
				$offset=$next_closing_bracket+mb_strlen($brackets[1]);
				$next_closing_bracket=null;
			}
			else throw new \Exception('BAD BRACKETS 5'); // все прочие ситуации - это плохо расставленые скобки.
		}
		
		throw new \Exception('BAD BRACKETS 6'); // строка кончилась, а скобки остались незакрытыми.
	}
	
	/**
	* Разбирает строку на последовательность команд. Команды, объединённые в блок, остаются в составе включающего их элементаю.
	* "Командой" в данном методе называется утверждение (statement), отделённое от других разделителем и допустимое к объединению в блоки.
	* Предполагает, что строки уже заменены на конструкции '1', '2'...
	
	* @param string $block Строка, которую необходимо обработать.
	* @param string $breaker Строка, использующаяся для разделения команд.
	* @param array $brackets Массив из двух строк-скобок, использующихся для создания блока команд.
	
	* @return array Массив с командами.
	*/
	protected static function parse_commands($block, $breaker=';', $brackets=['{', '}'])
	{
		$block=trim($block);
		
		$next_breaker=mb_strpos($block, $breaker);
		if ($next_breaker===false) return static::tidy_commands([$block]); // если разделителей нет, то строка - одна команда.
		
		if (mb_substr($block, -mb_strlen($breaker))!==$breaker) $block.=$breaker; // если в конце строки нет разделителя, то он подразумевается.
		if ( ($brackets_count=substr_count($block, $brackets[0]))!==substr_count($block, $brackets[1])) throw new \Exception('BAD BRACKETS 7'); // число открывающих и закрывающих скобок не совпадает.
		if ( $brackets_count==0 ) return static::tidy_commands(array_slice(explode($breaker, $block), 0, -1)); // скобок нет, можно просто разбить строку по разделителям.
		
		$offset=0;
		$next_opening_bracket=null;
		$next_closing_bracket=null;
		$command_start=0;

		$commands=[];
		$tries=100;
		$try=0;
		while ($offset<mb_strlen($block) and ++$try<=$tries)
		{
			if ($next_breaker===null or $next_breaker<$offset)  $next_breaker=mb_strpos($block, $breaker, $offset);
			if ($next_opening_bracket===null) $next_opening_bracket=mb_strpos($block, $brackets[0], $offset);
			if ($next_closing_bracket===null) $next_closing_bracket=mb_strpos($block, $brackets[1], $offset);
		
			if ($next_closing_bracket!==false and $next_closing_bracket<$next_opening_bracket and $next_closing_bracket<$next_breaker)
			// открывающая скобка, если находится внутри текущей команды, не может быть до закрывающей.
				throw new \Exception('BAD BRACKETS 8');
			elseif ($next_opening_bracket===false or $next_breaker<$next_opening_bracket)
			// если открывающая скобка не найдена или находится за пределами текущей команды.
			{
				$commands[]=mb_substr($block, $command_start, $next_breaker-$command_start);
				$offset=$next_breaker+mb_strlen($breaker);
				$command_start=$next_breaker+1;
				$next_breaker=null;
			}
			else // открывающая скобка присутствует и находится в текущей команде
			{
				$brackets_block=static::brackets_content($block, $brackets, $next_opening_bracket, true);
				// фигурные скобки обладают тем свойством, что после них не нужен разделитель - он подразумевается.
				if ($brackets[1]!=='}' or mb_substr($brackets_block, -2*mb_strlen($brackets[1]), mb_strlen($brackets[1]))===$brackets[1])
				// если скобка не фигурная или если две фигурные скобки подряд... FIXME: две фигурные скобки подряд?...
				{
					// продолжить запись команды.
					$offset=$next_opening_bracket+mb_strlen($brackets_block);
					$next_closing_bracket=null;
					$next_opening_bracket=null;
					continue;
				}
				
				// скобка фигурная, после неё команда считается записанной.
				$commands[]=mb_substr($block, $command_start, $next_opening_bracket-$command_start).$brackets_block;
				$command_start=$next_opening_bracket+mb_strlen($brackets_block);
				if (mb_substr($block, $command_start, mb_strlen($breaker))===$breaker) $command_start++; // если после фигурной скобки разделитель, он пропускается.
				$offset=$command_start;
				$next_closing_bracket=null;
				$next_opening_bracket=null;
			}
		}
		
		if ($try>$tries) throw new \Exception('ENDLESS LOOP');
		
		return static::tidy_commands($commands);
	}

	/**
	* Подчищает последовательность команд - убирает пустые, убирает лишние пробелы.
	* @param array $commands Массив команд.
	* @return array Те же команды, минус пустые.
	*/
	protected static function tidy_commands($commands)
	{
		foreach ($commands as $key=>&$command)
		{
			$command=trim($command);
			if (empty($command)) unset($commands[$key]);
		}
		return $commands;
	}
}

/**
	Парсер, который может возвращать как откомпилированный текст, так и данные разбора.
*/
abstract class Subparser extends Parser
{
	/**
	* @param string $content Неоткомпилированная строка с уже запакованными значениями в кавычках.
	*/
	public static function compile($content, ParserCommon $common)
	{
		$data=static::process($content, $common);
		return static::compile_processed($data, $common);
	}
	
	/**
	* Превращает разбор строки в откомпилированный текст.
	*/
	public static function compile_processed($data, ParserCommon $common)
	{
		if (is_string($data)) return $data;
		throw new \Exception('uncompileable parser data');
	}
	
	/**
	* Принимает неоткомпилированную строку и возвращает её разбор.
	* @param string $content Неоткомпилированная строка с уже запакованными значениями в кавычках.
	* @param \Pokeliga\Template\ParserCommon $common Контейнер общих данных компиляции.
	* @return string|array1
	*/
	public static function process($content, ParserCommon $common)
	{
		return parent::compile($content, $common);
	}
}

/*
* Класс для содержания общих данных парсера. Создаётся верхним парсером.
*/
class ParserCommon
{
	public
		$master,
		$strings=[],
		$codefrags=[];
	
	public static function create_and_absorb($source)
	{
		$common=new static();
		$common->absorb($source);
		return $common;
	}
	
	public function eval_once()
	{
		if (empty($this->codefrags)) return;
		
		return implode(' ', $this->codefrags);
	}
	
	public function absorb($common)
	{
		$this->strings+=$common->strings;
		$this->codefrags+=$common->codefrags;
	}
	
	protected function free_id($array)
	{
		if (empty($array)) return 0;
		else return max(array_keys($array))+1;
	}
	
	public function add_string($string)
	{
		$key=$this->free_id($this->strings);
		$this->strings[$key]=$string;
		return $key;
	}
	
	public function add_codefrag($type, $args)
	{
		$key=$this->free_id($this->codefrags);
		$this->codefrags[$key]='$this->codefrag(\''.$type.'\', '.$key.', '.$args.');';
		return $key;
	}
	
	public function create_sub($class=null)
	{
		if ($class===null) $class=get_class($this);
		$sub=$class::create_and_absorb($this);
		$sub->master=$this;
		return $sub;
	}
	
	public function dissolve()
	{
		if (empty($this->master)) return;
		$this->master->absorb($this);
	}
}

?>