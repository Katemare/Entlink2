<?

namespace Pokeliga\Data;

class DataFront extends \Pokeliga\Entlink\ModuleFront implements Pathway
{
	public function follow_track($track)
	{
		if ($track==='temporal') return $this->get_time_track();
	}
	
	public function get_time_track()
	{
		if (empty($this->time_track)) $this->time_track=new TimeTrack($this);
		return $this->time_track;
	}
}

?>