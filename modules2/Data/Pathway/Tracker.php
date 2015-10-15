<?

namespace Pokeliga\Data;

abstract class Tracker extends \Pokeliga\Task\Task implements \Pokeliga\Task\Task_proxy
{
	use \Pokeliga\Task\Task_coroutine;
	
	const
		ANCHOR_ROOT='*',
		
		LOC_LOCAL			=0,
		LOC_CONTEXT			=1,
		LOC_CONTEXT_GATEWAY	=2,
		LOC_ROOT			=3,
		LOC_ROOT_GATEWAY	=4,
		LOC_ANCHOR			=5;
	
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
	
	protected function advance_track($location, $point)
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
		if ($anchor===static::ANCHOR_ROOT) $this->advance_track($this->get_root(), static::LOC_ANCHOR);
		throw new \Exception('bad anchor');
	}
	
	protected function get_root()
	{
		return Engine(); // FIXME: потом нужна локальная ссылка на движок.
	}
	
	protected function branch_at_startpoint() { return false; }
	
	protected function branching_locations()
	{
		yield $this->location;
		if ($this->iteration>0) return;
		
		if ($this->location instanceof HasContext and !empty($context=$this->location->get_context()))
		{
			yield static::LOC_CONTEXT=>$context;
			foreach ($root->gateways() as $waypoint) yield static::LOC_CONTEXT_GATEWAY=>$waypoint;
		}
		
		$root=$this->get_root();
		yield static::LOC_ROOT=>$root; // FIXME: не проверяет, является ли рут Контекстом, поскольку сейчас объект Engine создаётся до понятия о контекстах. В будущем первичную загрузку должен делать прелоадер.
		foreach ($root->gateways() as $waypoint) yield($waypoint);
	}
	
	private function get_locations()
	{
		if ($this->iteration===0 and $this->branch_at_startpoint()) return $this->branching_locations();
		else return [static::LOC_LOCAL=>$this->location];
	}
	
	private function resolve_waypoint($track, $line)
	{
		$locations=$this->get_locations();
		foreach ($locations as $point=>$location)
		{
			if (! duck_instanceof($location, '\Pokeliga\Data\Pathway') ) continue;
			$call=function() use ($track, $line, $location) { return $location->follow_track($track, $line); };
			yield $need=new \Pokeliga\Task\Need_call($call, false);
			$result=$need->resolution();
			if ($result instanceof \Report_unknown) continue;
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
			
			$this->advance_track($result, $point);
			return;
		}
		
		$this->impossible('bad_waypoint: '.$track);
	}
	
	private function resolve_endpoint($track, $line)
	{
		$locations=$this->get_locations();
		foreach ($locations as $point=>$location)
		{
			if (!$this->good_endpoint($location)) continue;
			$call=function() use ($track, $line, $location) { return $this->ask_endpoint($track, $line, $location); };
			yield $need=new \Pokeliga\Task\Need_call($call, false);
			$result=$need->resolution();
			if ($this->iteration===0 and $result instanceof \Report_unknown) continue;
			elseif ($result instanceof \Report_impossible or $this->good_resolution($result)) $this->finish_with_resolution($result);
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

class Track_value extends Tracker
{
	protected function good_endpoint($location) { return duck_instanceof($location, '\Pokeliga\Data\ValueHost'); }
	
	protected function ask_endpoint($track, $line, $location) { return $location->request($track); }
	
	protected function good_resolution($result) { return true; }
}