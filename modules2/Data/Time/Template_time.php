<?
namespace Pokeliga\Data;

class Template_period extends \Pokeliga\Template\Template_from_db
{
	public
		$start,
		$end,
		$elements=['start', 'end'];
		
	public static function from_period($start, $end, $line=[])
	{
		$template=static::with_line($line);
		$template->start=$start;
		$template->end=$end;
		return $template;
	}
	
	public function initialized()
	{
		parent::initialized();
		$this->setup_period_from_line();
	}
	
	public function setup_period_from_line()
	{
		if (array_key_exists('start', $this->line)) $this->start=(int)$this->line['start'];
		if (array_key_exists('end', $this->line)) $this->end=(int)$this->line['end'];
	}
	
	public function get_db_key($now=true)
	{
		$start_decode=getdate($this->start);
		$end_decode=getdate($this->end);
		if ($start_decode['year']!==$end_decode['year']) $key='standard.period';
		elseif ($start_decode['mon']!==$end_decode['mon']) $key='standard.period_same_year';
		elseif ($start_decode['mday']!==$end_decode['mday']) $key='standard.period_same_month';
		else $key='standard.period_same_day';
		if ( ($start_decode['hours']==$end_decode['hours']) && ($start_decode['minutes']==$end_decode['minutes']) ) $key.='_same_time';
		return $key;
	}
	
	public function make_template($code, $line=[])
	{
		if ($code==='start') return $this->date_template($this->start, $line);
		if ($code==='end') return $this->date_template($this->end, $line);
	}
	
	public function date_template($date, $line=[])
	{
		$value=Value_time::from_content($date);
		return $value->default_template($line);
	}
}

?>