<?

namespace Pokeliga\Task;

class Task_dice extends Task
{
	public function progress()
	{
		$this->finish_with_resolution(rand(1,6));
	}
}

class Task_fail extends Task
{
	public function progress()
	{
		$this->impossible('pre_failed');
	}
}

class Task_dice_coroutine extends Task
{
	use Task_coroutine;
	
	public static function simple_generator() // статическая, чтобы получать генератор для целей теста.
	{
		yield rand(1,6);
	}
	
	public static function complex_generator()
	{
		yield $need=(new Task_dice())->to_need();
		yield $need->resolution()*10;
	}
	
	public function coroutine()
	{
		return static::complex_generator();
	}
}

?>