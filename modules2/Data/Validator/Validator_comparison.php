<?

namespace Pokeliga\Data;

abstract class Validator_comparison extends Validator
{
	use \Pokeliga\Task\Task_coroutine;

	public
		$reference_code='compare_to',
		$gap_code='comparison_gap',
		$references=null;
		
	public function gap()
	{
		if ($this->in_value_model($this->gap_code)) return $this->value_model($this->gap_code);
		else return 0;
	}
	
	public function references()
	{
		$references=$this->value_model($this->reference_code);
		if (empty($references))
		{
			$this->success();
			return;
		}
		elseif ($references instanceof \Report_impossible) return $references;
		$references=(array)$references;
		foreach ($references as &$reference)
		{
			$reference=$this->resolve_reference($reference);
		}
		return $references;
	}
	
	public function resolve_reference($reference)
	{
		if ($reference instanceof Compacter) $reference=$reference->extract_for($this);
		return $reference;	
	}
	
	public function coroutine()
	{
		yield $need=new \Pokeliga\Task\Need_all(['references'=>new \Pokeliga\Task\Need_all($this->references()), 'gap'=>$this->gap()]);
		$gap=$need['gap'];
		foreach ($need['references'] as $reference)
		{
			if ($this->compare_to($this->value->content(), $reference, $gap)===false) yield new \Report_impossible('doesnt_compare', $this);
		}
	}
	
	public abstract function compare_to($content, $reference, $gap=0);
}

trait Validator_comparison_to_sibling
{
	public function resolve_reference($reference)
	{
		$reference=parent::resolve_reference($reference);
		if ($reference instanceof \Pokeliga\Task\Task) die ('BAD SIBLING CODE');
		return $this->value->master->valid_content($reference, false);
	}
}

// строго больше.
class Validator_greater extends Validator_comparison
{
	public
		$reference_code='greater_than',
		$gap_code='greater_gap';
	
	public function compare_to($content, $reference, $gap=0)
	{
		return $content>$reference+$gap;
	}
}
class Validator_greater_than_sibling extends Validator_greater
{
	use Validator_comparison_to_sibling;
}

// больше либо равно.
class Validator_greater_or_equal extends Validator_comparison
{
	public
		$reference_code='greater_or_equal',
		$gap_code='greater_or_equal_gap';
	
	public function compare_to($content, $reference, $gap=0)
	{
		return $content>=$reference+$gap;
	}
}
class Validator_greater_or_equal_to_sibling extends Validator_greater_or_equal
{
	use Validator_comparison_to_sibling;
}

// строго меньше.
class Validator_less extends Validator_comparison
{
	public
		$reference_code='less_than',
		$gap_code='less_gap';
	
	public function compare_to($content, $reference, $gap=0)
	{
		return $content<$reference-$gap;
	}
}
class Validator_less_than_sibling extends Validator_less
{
	use Validator_comparison_to_sibling;
}

// меньше либо равно.
class Validator_less_or_equal extends Validator_comparison
{
	public
		$reference_code='less_or_equal',
		$gap_code='less_gap';
	
	public function compare_to($content, $reference, $gap=0)
	{
		return $content<=$reference-$gap;
	}
}
class Validator_less_or_equal_to_sibling extends Validator_less_or_equal
{
	use Validator_comparison_to_sibling;
}

// не равно.
class Validator_not_equal extends Validator_comparison
{
	public
		$reference_code='not_equal';
	
	public function compare_to($content, $reference, $gap=0 /* не используется */ )
	{
		return $content!=$reference;
	}
}
class Validator_not_equal_to_sibling extends Validator_not_equal
{
	use Validator_comparison_to_sibling;
}

class Validator_not_equal_strict extends Validator_not_equal
{
	public
		$reference_code='not_equal_strict';
	
	public function compare_to($content, $reference, $gap=0 /* не используется */ )
	{
		return $content!==$reference;
	}
}
class Validator_not_equal_to_sibling_strict extends Validator_not_equal_strict
{
	use Validator_comparison_to_sibling;
}

?>