<?

namespace Pokeliga\Data;

/**
* Интерфейс объекта, состоящего в структурном графе.
* Вся структура данных, сущностей и прочего представляет собой граф, по которому можно перемещаться запросами follow_track(). Трек (след) должен представлять собой строку и, возможно, массив дополнительных параметров. Путь (стек следов) представляет собой массив следов, а в текстовом виде - следы, разделённые точкой, например:

owner.nickname (из шаблона с контекстом-покемоном, показывает имя тренера)
page.input.pokemon (из шаблона, показывающегося в странице, показывает ссылку на покемона из ввода)
adopts.pokemon[id=5].level (показывает уровень покемона № 5)
adopts.pokemon[species.title=Бульбазавр].portrait (показывает портреты покемонов, имя вида которых "Бульбазавр")
adopts.pokemon[species.title=Бульбазавр; random 5].portrait (показывает портреты случайных пяти покемонов, имя вида которых "Бульбазавр")
users.user[birthday.month:=data.today.month; birthday.day:=data.today.day; order by nickname].portrait (показывает портреты пользователей, у которых сегодня ДР)
adopts.pokemon[no_misison_cooldown; mission=1; player:=adopts.current_player; order by level desc].portraits (показывает портреты покемонов данного тренера, не имеющих кулдауна в данной миссии)

Не всё это уже реализовано! Но лучше всего, чтобы тексты шаблонов могли принимать и интерпретировать такой ввод.

Путь делится на три части: начало (startpoint), промежуточные точки (waypoints) и конец (endpoint).

Начало определяет точку отсчёта и может быть "текущим объектом" (например, шаблон); "контекстом" (например, объект-контекст этого шаблона); или одним из "якорей" (например, старшая страница шаблона или подключённые модули движка).
Промежуточные точки достигаются через результат работы метода follow_track() на последовательных объектах.
Конечная точка не обязана отвечать на follow_track(), но должна быть подходящим ответчиком для типа запроса: например, шаблонизатором, если мы хотим шаблон; хранилищем значений, если требуется значение.

Следует заметить, что описанная структура - абстрактная. шаблоны вызываются синтаксисом {{<путь>|параметр|параметр...}}. значения - синтаксисом @<путь>. шаблон может иметь форму {{#<код>}},  относящуюся не к поиску значения, а создающую шаблон с соответствующим текстовым ключом. В зависимости от всего этого, по-разному обрабатывается первая точка и последняя. но подразумевается, что follow_track() не зависим от ситуации, всегда актуален и всегда действует по одной логике.

*/

interface Pathway
{
	/**
	* Этот метод принимает обращение, взятое из стёка, и пытается получить следующий шаг на пути обращения.
	* @param string $track "След", по которому нужно найти следующую локацию.
	* @param array $line Вспомогательные данные, поясняющие след.
	* @return \Pokeliga\Entlink\FinalPromise|Object|null Если обещание, то результат является следующей локацией; невозможность получить незультат - невозможностью двинуться дальше. То же значение имеет null. Если возвращается другой объект, то он должен быть следующей локацией.
	*/
	public function follow_track($track, $line=[]);
}

abstract class Task_resolve_track extends \Pokeliga\Task\Task implements \Pokeliga\Task\Task_proxy
{
	use \Pokeliga\Task\Task_coroutine;
	
	const
		ANCHOR_ROOT='*';
	
	protected
		$track,
		$last_iteration,
		$location,
		$iteration=0;
		
	public function __construct($track, $origin)
	{
		$this->track=$track;
		$this->last_iteration=count($track)-1; // принимает только стандартно пронумерованные массивы!
		$this->location=$origin;
		parent::__construct();
	}
	
	protected function compacter_host()
	{
		return $this->location;
	}
	
	protected function coroutine()
	{
		while (true)
		{
			$track=&$this->track[$this->iteration];
			if (is_array($track))
			{
				yield $track=new Need_commandline($track, $this->compacter_host());
				$track=$track->resolution();
			}
			if (is_array($track)) $arg_track=array_shift($line=$track);
			else { $arg_track=$track; $line=[]; }
			
			$current_iteration=$this->iteration;
			while ($current_iteration===$this->iteration)
			{
				if ($this->iteration==$this->last_iteration) $routine=$this->resolve_endpoint($arg_track, $line);
				elseif ($this->iteration==0) $routine=$this->resolve_startpoint($arg_track, $line);
				else $routine=$this->resolve_waypoint($arg_track, $line);
				
				if ($routine instanceof \Generator) yield new \Pokeliga\Task\Need_subroutine($routine);
				elseif ($routine!==true) throw new \Exception('unexpected track');
			}
		}
	}
	
	protected function advance_track($location)
	{
		$this->location=$location;
		$this->iteration++;
	}
	
	protected function resolve_startpoint($track, $line)
	{
		if ($this->is_anchor($track)) return $this->resolve_anchor($track, $line);
		else return $this->resolve_waypoint($track, $line);
	}
	
	protected function is_anchor($track)
	{
		return $track===static::ANCHOR_ROOT;
	}
	
	protected function resolve_anchor($anchor, $line)
	{
		if ($anchor===static::ANCHOR_ROOT) $this->advance_track($this->get_root());
		throw new \Exception('bad anchor');
	}
	
	protected function get_root()
	{
		return Engine(); // FIXME: потом нужна локальная ссылка на движок.
	}
	
	protected function locations()
	{
		yield $this->location;
		if ($this->iteration>0) return;
		
		if ($this->location instanceof HasContext and !empty($context=$this->location->get_context()))
		{
			yield $context;
			foreach ($root->gateways() as $waypoint) yield($waypoint);
		}
		
		yield $root=$this->get_root(); // FIXME: не проверяет, является ли рут Контекстом, поскольку сейчас объект Engine создаётся до понятия о контекстах. В будущем первичную загрузку должен делать прелоадер.
		foreach ($root->gateways() as $waypoint) yield($waypoint);
	}
	
	private function normalize_locations($locations)
	{
		if ($locations===null) $locations=$this->locations();
		if (!($locations instanceof \Traversable)) $locations=[$locations];
		return $locations;
	}
	
	private function resolve_waypoint($track, $line, $locations=null)
	{
		$locations=$this->normalize_locations($locations);
		foreach ($locations as $location)
		{
			if (! duck_instanceof($location, '\Pokeliga\Data\Pathway') ) continue;
			$call=function() use ($track, $line, $location) { return $location->follow_track($track, $line); };
			yield $need=new \Pokeliga\Task\Need_call($call, false);
			$result=$need->resolution();
			if (empty($result) or $result instanceof \Pokeliga\Entlink\Promise and $result->failed()) continue;
			/*
			vdump(get_class($this));
			vdump('ORIGINAL: '.implode('.', $this->track));
			vdump('TRACK: '.$track);
			vdump('LOC: '.get_class($location));
			if ($location instanceof \Pokeliga\Template\Template)
			{
				vdump('CONTEXT: '.get_class($this->location->context));
				if ($location->context instanceof \Pokeliga\Entity\Entity) vdump('TYPE '.$location->context->id_group);
			}
			vdump('RESULT: '.get_class($result));
			vdump($result);
			vdump('---');
			*/
			
			$this->advance_track($result);
			return;
		}
		
		$this->impossible('bad_waypoint: '.$track);
	}
	
	private function resolve_endpoint($track, $line, $locations=null)
	{
		$locations=$this->normalize_locations($locations);
		foreach ($locations as $location)
		{
			if (!$this->good_endpoint($location)) continue;
			$call=function() use ($track, $line, $location) { return $this->ask_endpoint($track, $line, $location); };
			yield $need=new \Pokeliga\Task\Need_call($call, false);
			$result=$need->resolution();
			if ($result instanceof \Report_impossible or $this->good_resolution($result)) $this->finish_with_resolution($result);
			else $this->impossible('bad_resolution');
			$this->make_calls('proxy_resolved', $result);
			return;
		}
		$this->impossible('bad_endpoint');
	}
	
	protected abstract function good_endpoint($location);
	
	protected abstract function ask_endpoint($track, $line, $location);
	
	protected abstract function good_resolution($result);
	
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
	protected function good_endpoint($location) { return duck_instanceof($location, '\Pokeliga\Data\ValueHost'); }
	
	protected function ask_endpoint($track, $line, $location) { return $location->request($track); }
	
	protected function good_resolution($result) { return true; }
}
?>