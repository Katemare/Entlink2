<?
namespace Pokeliga\Data;

abstract class Convert_time extends Convert
{
	const
		FRACTION_ROUND=1,
		FRACTION_FLOOR=2,
		FRACTION_CEIL=3,
		FRACTION_DEFAULT=self::FRACTION_FLOOR,
		
		SECONDS='replace_me';
	
	public
		$fraction=self::FRACTION_DEFAULT;

	public function save_arguments($args)
	{
		if (array_key_exists('fraction', $args)) $this->fraction=$args['fraction'];
		parent::save_arguments($args);
	}
		
	public function convert_forward()
	{
		$hours=$this->source/static::SECONDS;
		if ($this->fraction===static::FRACTION_ROUND) $hours=round($hours);
		elseif ($this->fraction===static::FRACTION_FLOOR) $hours=floor($hours);
		elseif ($this->fraction===static::FRACTION_CEIL) $hours=ceil($hours);
		else die ('BAD FRACTION');
		$hours=(int)$hours; // иначе получается float, который не везде правильно понимается.
		$this->finish_with_resolution($hours);
	}
	
	public function convert_backward()
	{
		$this->finish_with_resolution($this->source*static::SECONDS);
	}
}

class Convert_time_to_hours extends Convert_time
{
	const
		SECONDS=3600;
}

class Convert_time_to_days extends Convert_time
{
	const
		SECONDS=86400;
}

?>