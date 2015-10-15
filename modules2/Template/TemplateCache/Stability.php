<?
namespace Pokeliga\Template;

interface Bakeable
{
	public function bake(&$baked_elements);
}

interface BakeHost
{
	public function should_be_baked();
	
	public function bake_key();
}

interface Pathway_stability extends \Pokeliga\Data\Pathway
{
	public function is_track_stable($track, $line=[]);
}

interface ValueHost_stability extends \Pokeliga\Data\ValueHost
{
	public function is_content_stable($code);
}

interface ValueContent_stability extends \Pokeliga\Data\ValueContent
{
	public function is_content_stable();
}

interface ValueHost_combo_stability extends \Pokeliga\Data\ValueHost_combo, ValueHost_stability, ValueContent_stability
{
	public function is_content_stable($code=null);
}

interface Templater_stability extends Templater
{
	public function is_template_stable($code, $line=[]);
}

interface Stability
{
	public function is_stable();
}

class Report_stability extends \Report
{
	public
		$stable,
		$stable_until,
		$resetable_only_keys=[],
		$other_keys=[],
		$expensive=false;
	
	public static function create_unstable() { return false; } // обычно этого значения хватает, чтобы сообщить нестабильность, поскольку нестабильность не может превратиться в стабильность.
	public static function create_unstable_report($by=null)		// на случай, если требуется именно отчёт о нестабильности.
	{
		$report=new static($by);
		$report->stable=false;
		return $report;
	}
	public static function create_stable($by=null)
	{
		$report=new static($by);
		$report->stable=false;
		return $report;
	}
	public static function create_conditional($by=null, $stable_until=null, $resetable_only_keys=[], $other_keys=[], $expensive=false)
	{
		$report=new static($by);
		$report->stable_until=$stable_until;
		$report->resetable_only_keys=$resetable_only_keys;
		$report->other_keys=$this->other_keys;
		$report->expensive=$expensive;
		$report->estimate_stability();
		return $report;
	}
	
	public function combine_with($stability)
	{
		if ($this->stable===false) return;
		if ($stability instanceof Report_stability and is_bool($stability->stable)) $stability=$stability->stable;
		if ($stability===false)
		{
			$this->stable=false;
			return;
		}
		elseif ($stability===true) return;
		
		if ($stability->stable_until!==null and ($this->stable_until===null or $this->stable_until>$stability->stable_until)) $this->stable_until=$stability->stable_until;
		if (!empty($stability->resetable_only_keys)) $this->merge_keys($this->resetable_only_keys, $stability->resetable_only_keys);
		if (!empty($stability->other_keys)) $this->merge_keys($this->other_keys, $stability->other_keys);
		if ($stability->expensive) $this->expensive=true;
		$this->update_stability();
	}
	
	protected function merge_keys(&$pool, $keys)
	{
		foreach ($keys as $key=>$subgroups)
		{
			if (!array_key_exists($key, $pool)) $pool[$key]=$subgroups;
			elseif ($pool[$key]===null) continue;
			elseif ($subgroups===null) $pool[$key]=null;
			else $pool[$key]=array_unique(array_merge($pool[$key], $subgroups));
		}
	}
	
	public function update_stability()
	{
		if ($this->stable===false) return;
		if ($this->stable_until===null and empty($this->resetable_only_keys)) $this->stable=true;
		else $this->stable=null;
	}
}

?>