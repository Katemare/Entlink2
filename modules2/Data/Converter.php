<?
namespace Pokeliga\Data;

abstract class Convert extends Task_for_value
{
	use \Pokeliga\Entlink\Shorthand;

	const
		DIR_FORWARD=1,
		DIR_BACKWARD=2;
		
	public
		$source,
		$dir=Convert::DIR_FORWARD;
	
	public static function with_args($keyword, $source, $args)
	{
		$converter=static::from_shorthand($keyword);
		$converter->source=$source;
		$converter->save_arguments($args);
		return $converter;
	}
	
	public function save_arguments($args)
	{
		if (array_key_exists('dir', $args)) $this->dir=$args['dir'];
	}
	
	public function progress()
	{
		if ($this->dir===static::DIR_FORWARD) $this->convert_forward();
		elseif ($this->dir===static::DIR_BACKWARD) $this->convert_backward();
	}
	
	public abstract function convert_forward();
	
	public abstract function convert_backward();
}

?>