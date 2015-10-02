<?

namespace Pokeliga\Data;

/*

вся структура данных, сущностей и прочего представляет собой граф, по которому можно перемещаться запросами follow_track(). Трек (след) должен представлять собой строку и, возможно, массив дополнительных параметров. Путь (стек следов) представляет собой массив следов, а в текстовом виде - следы, разделённые точкой, например:

owner.nickname (из шаблона с контекстом-покемоном, показывает имя тренера)
page.input.pokemon (из шаблона, показывающегося в странице, показывает ссылку на покемона из ввода)
adopts.pokemon[id=5].level (показывает уровень покемона № 5)
adopts.pokemon[species.title=Бульбазавр].portrait (показывает портреты покемонов, имя вида которых "Бульбазавр")
adopts.pokemon[species.title=Бульбазавр; random 5].portrait (показывает портреты случайных пяти покемонов, имя вида которых "Бульбазавр")
users.user[birthday.month:=data.today.month; birthday.day:=data.today.day; order by nickname].portrait (показывает портреты пользователей, у которых сегодня ДР)
adopts.pokemon[no_misison_cooldown; mission=1; player:=adopts.current_player; order by level desc].portraits (показывает портреты покемонов данного тренера, не имеющих кулдауна в данной миссии)

не всё это уже реализовано! но лучше всего, чтобы тексты шаблонов могли принимать и интерпретировать такой ввод.

путь делится на три части: начало (startpoint), промежуточные точки (waypoints) и конец (endpoint).

Начало определяет точку отсчёта и может быть "текущим объектом" (например, шаблон); "контекстом" (например, объект-контекст этого шаблона); или одним из "якорей" (например, старшая страница шаблона или подключённые модули движка).
Промежуточные точки достигаются через результат работы метода follow_track() на последовательных объектах.
Конечная точка не обязана отвечать на follow_track(), но должна быть подходящим ответчиком для типа запроса: например, шаблонизатором, если мы хотим шаблон; хранилищем значений, если требуется значение.

Следует заметить, что описанная структура - абстрактная. шаблоны вызываются синтаксисом {{<путь>|параметр|параметр...}}. значения - синтаксисом @<путь>. шаблон может иметь форму {{#<код>}},  относящуюся не к поиску значения, а создающую шаблон с соответствующим текстовым ключом. В зависимости от всего этого, по-разному обрабатывается первая точка и последняя. но подразумевается, что follow_track() не зависим от ситуации, всегда актуален и всегда действует по одной логике.

*/

interface Pathway
{
	// этот метод принимает обращение, взятое из стёка, и пытается получить следующий шаг на пути обращения.
	// итого результатом работы может быть следующие ответы: объект; отчёт \Report_promise; отчёт \Report_dependant; \Report_impossible; или null (ленивый вариант \Report_impossible).
	
	public function follow_track($track, $line=[]);
}

abstract class Task_resolve_track extends \Pokeliga\Task\Task implements \Pokeliga\Task\Task_proxy
{
	use \Pokeliga\Task\Task_coroutine;
	
	public
		$original_track,
		$track,
		$last_iteration,
		$location,
		$iteration=0,
		
		$complex_checked=false;
		
	public function __construct($track, $origin)
	{
		$this->track=$track;
		$this->last_iteration=count($track)-1; // принимает только стандартно пронумерованные массивы!
		$this->location=$origin;
		parent::__construct();
	}
	
	public function compacter_host()
	{
		return $this->location;
	}
	
	public function coroutine()
	{
		while (true)
		{
			$track=&$this->track[$this->iteration];
			if (is_array($track) and !$this->complex_checked)
			{
				yield $track=new Need_commandline($track, $this->compacter_host());
				$track=$track->resolution();
				$this->complex_checked=true;
			}
			if (is_array($track)) $arg_track=array_shift($line=$track);
			else { $arg_track=$track; $line=[]; }
			
			if ($this->iteration==$this->last_iteration) $routine=$this->resolve_endpoint($arg_track, $line);
			elseif ($this->iteration==0) $routine=$this->resolve_startpoint($arg_track, $line);
			else $routine=$this->resolve_waypoint($arg_track, $line);
			
			yield new \Pokeliga\Task\Need_subroutine($routine);
		}
	}
	
	public function advance_track($location)
	{
		$this->location=$location;
		$this->iteration++;
		$this->complex_checked=false;
	}
	
	public function resolve_startpoint($track, $line)
	{
		// WIP!
		return $this->resolve_waypoint($track, $line);
	}
	
	public function resolve_waypoint($track, $line)
	{
		if (! duck_instanceof($this->location, '\Pokeliga\Data\Pathway') ) throw new \Exception('bad Pathway waypoint');
		$call=function() use ($track, $line) { return $this->location->follow_track($track, $line); };
		yield $need=new \Pokeliga\Task\Need_call($call);
		$result=$need->resolution();
		/*
		vdump(get_class($this));
		vdump('ORIGINAL: '.implode('.', $this->original_track));
		vdump('TRACK: '.$track);
		vdump('LOC: '.get_class($this->location));
		if ($this->location instanceof \Pokeliga\Template\Template)
		{
			vdump('CONTEXT: '.get_class($this->location->context));
			if ($this->location->context instanceof \Pokeliga\Entity\Entity) vdump('TYPE '.$this->location->context->id_group);
		}
		vdump('RESULT: '.get_class($result));
		vdump($result);
		vdump('---');
		*/
		
		$this->advance_track($result);
	}
	
	public function resolve_endpoint($track, $line)
	{
		$this->iteration=null; // чтобы поймать разрешение последней задачи - FIX! плохой метод, поскольку неясный.
		if (!$this->good_endpoint())
		{
			// vdump('BAD ENDPOINT: '.$this->track[$this->iteration]); 
			// vdump($this->location);
			$this->impossible('bad_endpoint');
			return;
		}
		$call=function() use ($track, $line) { return $this->ask_endpoint($track, $line); };
		yield $need=new \Pokeliga\Task\Need_call($call);
		$result=$need->resolution();
		if ($this->good_resolution($result)) $this->finish_with_resolution($result);
		else $this->impossible('bad_resolution');
		$this->make_calls('proxy_resolved', $result);
	}
	
	public abstract function good_endpoint();
	
	public abstract function ask_endpoint($track, $line);
	
	public abstract function good_resolution($result);
	
	public function human_readable_track()
	{
		$result=[];
		foreach ($this->track as $entry)
		{
			if (is_array($entry)) $result[]=reset($entry).'[]';
			else $result[]=$entry;
		}
		return implode('.', $result);
	}
}

class Task_resolve_value_track extends Task_resolve_track
{
	public function good_endpoint() { return duck_instanceof($this->location, '\Pokeliga\Data\ValueHost'); }
	
	public function ask_endpoint($track, $line) { return $this->location->request($track); }
	
	public function good_resolution($result) { return true; }
}
?>