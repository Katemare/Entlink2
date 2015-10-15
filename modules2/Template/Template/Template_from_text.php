<?
namespace Pokeliga\Template;

/**
* Этот шаблон генерирует свой результат на основе заранее скомпилированного текста.
*/
class Template_from_text extends Template implements Templater, \Pokeliga\Data\ValueHost, \Pokeliga\Data\Pathway
{
	use \Pokeliga\Task\Task_coroutine, \Pokeliga\Data\ValueHost_standard;
	
	const
		TRACK_PAGE='page',
		TRACK_CONTEXT='context',
		
		STEP_GET_TEXT=0,
		STEP_EVAL=1,
		STEP_COMPOSE=2;
	
	public
		$elements=[],
		$text=null,
		$plain=false,		
		$buffer=[];
	
	public static function with_text($text, $line=[])
	{
		$template=static::with_line($line);
		$template->text=$text;
		return $template;
	}
	
	public function coroutine()
	{
		yield $need=new \Pokeliga\Task\Need_call([$this, 'get_text']);
		$this->text=$need->resolution();
		
		$this->eval_text();
		
		if ($this->completed()) return;
		yield $need=new \Pokeliga\Task\Need_all($this->buffer, false);
		$this->buffer=$need->resolution();
		$this->finish_with_resolution($this->compose());
	}
	
	public function eval_text()
	{
		if ($this->plain)
		{
			$this->finish_with_resolution($this->text);
			return;
		}
		ob_start();
		eval ($this->text);		
		$this->buffer_store_output(); // после последнего ключевого слова мог остаться какой-нибудь вывод, сохраняем его.
		ob_end_clean();
	}
	
	public function compose()
	{
		return implode($this->buffer);
	}
	
	public function on_finish()
	{
		$this->buffer=[];
	}
	
	public function get_text()
	{
		if ($this->text!==null) return $this->text;
		return $this->produce_text();
	}
	
	public function produce_text()
	{
		throw new \Exception('NO TEXT RETRIEVAL');
	}
		
	public function template($code, $line=[])
	{
		if ($this->recognize_element($code, $line))
		{
			// иногда make_template может возвращать отчёт о задаче, содержащий внутри собственно шаблон.
			$template=$this->make_template($code, $line);
			if ($template instanceof \Pokeliga\Task\Promise and !$template->completed()) $template=$template->to_task();
			if ($template instanceof Template and empty($template->line)) $template->line=$line;
			return $template;
		}
	}
	
	// для соответствия интерфейсу ValueHost
	public function request($code)
	{
		return $this->ValueHost_request($code);
	}
	
	public function value($code)
	{
		return $this->ValueHost_value($code);
	}
	
	public function recognize_element($code, $line=[])
	{
		return in_array($code, $this->elements);
	}
	
	public function follow_track($track, $line=[])
	{
		if ($track===static::TRACK_PAGE) return $this->page;
		if (empty($this->context)) return;
		if ($track===static::TRACK_CONTEXT) return $this->context;
		return $this->context->follow_track($track, $line);
	}

	public function keyword($track, $line=[])
	{
		$this->buffer_store_output();
		$result=$this->keyword_task($track, $line);
		
		if ($result instanceof \Pokeliga\Task\Promise)
		{
			if ($result->failed()) $this->buffer_store_string('UNKNOWN TEMPLATE: '.((is_array($track))?(implode('.', $track)):($track)));
			else $this->buffer_store_promise($result);
		}
		elseif ($result instanceof \Pokeliga\Task\TasksContainer) $this->buffer_store_tasks($result);
		elseif (is_object($result)) throw new \Exception('bad Template element');
		else $this->buffer_store_string($result);
	}
	
/*
	может вернуть один из следующих ответов:
	
	1. задачу Task_resolve_keyword_track или Task_delayed_keyword, обе из которых реализуют интерфейс Task_proxy, то есть по выполнении имеют результат идентичный шаблону, который пытаются получить.
	2. Другой Task, обычно являющийся шаблоном.
	3. \Report_tasks, содержащий несколько шаблонов, которые предлагается зарегистрировать в буфере подряд - пока не реализовано.
	4. \Report_impossible, если шаблон по такому коду не найден.
	5. Просто значение.
*/
	public function keyword_task($track, $line=[])
	{
		if ( (is_array($track)) && (reset($track)==='@') )
		{
			array_shift($track);
			if (count($track)==1) $track=reset($track);
			return $this->value_task($track);
		}
		
		$line_has_tasks=false;
		foreach ($line as &$arg)
		{
			if ($arg instanceof \Pokeliga\Data\Compacter) $arg=$arg->extract_for($this);
			if ($arg instanceof \Pokeliga\Task\Task) $line_has_tasks=true;
		}
		
		if ( ($line_has_tasks) || (array_key_exists('cache_key', $line)) )
		{
			return new \Pokeliga\Task\Task_delayed_keyword($track, $line, $this);
		}
		
		if (is_array($track)) return new Task_resolve_keyword_track($track, $line, $this);
		
		$element=$this->find_template($track, $line);
		if ($element instanceof \Report_impossible) return $element;
		if ($element instanceof \Report_task) return $element->task;
		if ($element instanceof \Report_tasks) return $element; // FIXME: нужно предусмотреть вариант, когда ряд задач будет возвращён одному из Task_proxy, которые уже добавлены в буфер в виде единсвтенного шаблона.
		
		if ($element instanceof \Report_resolution) $element=$element->resolution;
		
		if ($element===null) return new \Report_impossible('no_keyword_resolution', $this);
		if ($element instanceof \Pokeliga\Task\Task) return $element;
		if (is_object($element)) { vdump($element); vdump($this); die ('BAD REPORT 2'); }
		
		return $element;
	}
	
	// возвращает либо искомое значение, либо задачу, разрешением которой станет искомое значение.
	// такой запрос записывается не {{pokemon.id}}, а @pokemon.id и возвращает не шаблон - отображение для пользователя; а собственно значение, поэтому командной строки не нужно (она обычно уточняет параметры отображения). В выражениях следует всегда обращаться за значениями, а не шаблонами, потому что хотя вместо шаблона иногда может вернуться значение (например, числовые значения обычно возвращают просто себя), лучше наверняка получить значение.
	public function value_task($track)
	{
		if (is_array($track)) return new \Pokeliga\Data\Task_resolve_value_track($track, $this);
		
		$element=$this->ValueHost_request($track);
		if ($element===null) return new \Report_impossible('no_value_resolution', $this);
		if ($element instanceof \Report_resolution) return $element->resolution;
		if ($element instanceof \Report_impossible) return $element;
		if ($element instanceof \Report_task) return $element->task;
		if ($element instanceof \Report_tasks) die ('BAD REPORT');
		if ($element instanceof \Pokeliga\Task\Task) return $element;
		
		if (is_object($element)) { vdump($element); die ('BAD REPORT 2'); }
		return $element;
	}
	
	public function buffer_store_string($s)
	{
		if ($s==='') return;
		$this->buffer[]=$s;
	}
	
	// скидывает в буфер вывод, накопившийся с прошлого обращения к буферу.
	public function buffer_store_output()
	{
		$output=ob_get_contents();
		if ($output!=='') $this->buffer[]=$output;
		ob_clean();
	}
	
	// добавляет в буфер ссылку на переменную.
	public function buffer_store_reference(&$target)
	{
		$this->buffer[]=&$target;
	}
	
	public function buffer_store_error($error)
	{
		if ($error instanceof \Pokeliga\Entlink\ErrorsContainer) $error=$error->get_errors();
		if (is_array($error)) $error=implode(', ', $error);
		$this->buffer_store_string('BAD TEMPLATE: '.$error); // STUB
	}
	
	public function buffer_store_promise($promise)
	{
		if ($promise->failed()) $this->buffer_store_error($promise);
		elseif ($promise->successful()) $this->buffer_store_value($promise->resolution());
		else
		{
			$task=$promise->to_task();
			$this->setup_subtemplate($task);
			$this->buffer[]=$task;
		}
	}
	
	public function buffer_store_tasks($tasks)
	{
		if ($tasks instanceof \Pokeliga\Task\TasksContainer) $tasks=$tasks->get_tasks();
		foreach ($tasks as $task) $this->buffer_store_promise($task);
	}
	
	// добавляет в буфер информацию для вызова функции (в данной модели не используется).
	/*
	// пока нет функционала, чтобы это использовать, и случая, когда бы это пригождалось.
	public function buffer_store_call($call)
	{
		if (! ($call instanceof \Pokeliga\Entlink\Call)) $call=new Call($call);
		$this->buffer[]=$call;
	}
	*/
}

class Track_template extends \Pokeliga\Data\Tracker
{
	public
		$line=[];
		
	public function __construct($track, $line, $origin)
	{
		$this->line=$line;
		parent::__construct($track, $origin);
	}
	
	public function good_endpoint($location)
	{
		return duck_instanceof($location, '\Pokeliga\Template\Templater');
	}
	
	public function ask_endpoint($track, $track_line, $location)
	{
		return $location->template($track, $this->line);
	}
	
	public function good_resolution($result)
	{
		return !is_object($result);
	}
	
	public function finish_by_bool($success=true)
	{
		if ($success!==true) $this->resolution='MISSING TEMPLATE: '.$this->human_readable_track(); // FIXME! чтобы что-то поместить в текст в качестве дебага... а вообще-то проваленные задачи не должны иметь разрешения.
		parent::finish_by_bool(true);
	}
}

/*
												bakes to:		keys and subgroups					special expiry		reset req?
@meow											'meow'			-									-					-
@user.login										'EvilCat'		Player4: basic, User99: basic		-					-
@pokemon[id=5].nickname							'007'			Pokemon5: basic						-					y
@pokemon[provide=random].owner.is_activated		-
@pokemon[id:=@page.poke_id].owner.login			-
@pokemon[id:=@daily_poke_id].genes.serialized	'[i:2;i:3]'		Pokemon99: breedable				cron_daily			?
@pokemon[provide=starter; owner:=@spotlight_user.player].is_egg
												false			User99: adopts;						cron_daily			y

if (@meow) echo 'woof';							'woof'			-									-					-
if (@meow) @pokemon[id=5].nickname;				'007'			Player5: basic, User99: basic		-					-

if (@is_egg) @pokemon[id=5].nickname;			''				Pokemon99: basic					-					y

if (!@is_egg) @pokemon[id=5].nickname;			'007'			Pokemon99: basic, Pokemon5: basic	-					y

if (@is_egg) @pokemon[id=5].nickname;			'not an egg'	Pokemon99: basic					-					y
else echo 'not an egg';

if (@is_egg) @pokemon[id=5].nickname;			if (@d6==6) 'you won!';		Pokemon99: basic		-					y
elseif (@d6==6) 'you won!';						else echo 'not an egg';
else echo 'not an egg';

if (@d6==6) 'you won!';							-				Нельзя предположить о дальнейшем условии, поскоьку сама действительность
elseif (@is_egg) @pokemon[id=5].nickname;						дальнейшей логики может зависеть от проверки выше (например, залогинен ли пользователь).
else echo 'not an egg';

@meow.@woof										'meowwoof'		-
@two+@two										4
'Dear '.@user.login								'Dear EvilCat'	Player4: basic, User99: basic
@pokemon[provide=random].owner.login.' is cool'	-
@pokemon[provide=random].owner.login.@meow		@pokemon[provide=random].owner.login.'meow'	-
@pokemon[provide=random].nickname.' may be a '.@species[id=1].title
												@pokemon[provide=random].nickname.' may be a Bulbasaur'
																Species1: contribution				-					-
*/

// WIP!!!
abstract class ValueElement extends \Pokeliga\Data\Track_value implements TemplateElement
{
	protected
		$stable_ref,
		$stable=null;
		
	public function __construct($track, $origin)
	{
		parent::__construct($track, $origin);
		if ($this->possible_stability($origin)) $this->stable_ref=$origin->get_context();
		else $this->stable=false;
	}
	
	public function possible_stability($origin)
	{
		return ($context=$origin->get_context()) instanceof StableKey and $context->get_stability_key()!==false;
	}

	public function precompile()
	{
		return '$this->value_element('.var_export($this>track, true).');';
	}
	
	protected function advance_track($location, $point)
	{
		if ($this->iteration===0)
		{
			if ($point!==static::LOC_CONTEXT) $this->bakeable=false;
			elseif (!($location instanceof BakeKey)) $this->bakeable=false;
			elseif (empty($location->get_base_key())) $this->bakeable=false;
		}
		parent::advance_track($location);
	}
	
	protected function estimate_stable_path($location)
	{
	}
	
	public function is_stable(&$group=null)
	{
		if (!$this->completed()) throw new \Exception('premature stability request');
		if ($this->failed()) return false;
		return $this->stable;
	}
}

?>