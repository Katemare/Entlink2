<?
interface Pathway
{
	// этот метод принимает обращение, взятое из стёка, и пытается получить шаблонизатор, к которому относится обращение.
	// итого результатом рабты может быть следующие ответы: объект Templater; отчёт Report_tasks, в результате работы получающий Templater; Report_impossible.
	
	public function follow_track($track);
}

interface Template_context
{
	public function templaters();
}

trait Context_self
{
	public function templaters()
	{
		return [$this];
	}
}

class Context implements Pathway, Template_context
{
	use Report_spawner;
	
	const
		DEFAULT_CODE='context';

	public
		$elements=[],
		$templaters=[];
		
	public static function for_template($template)
	{
		$context=new static();
		$context->setup($template);
		return $context;
	}
	
	public function setup($master)
	{
	}
	
	// элементом может быть Templater, ValueHost и/или Pathway.
	public function append($element, $code=Context::DEFAULT_CODE)
	{
		if (is_array($element))
		{
			$entries=$element;
			foreach ($entries as $code=>$element)
			{
				$this->append($element, $code);
			}		
		}
		else
		{
			unset($this->templaters[$code]);
			$this->elements[$code]=$element;
			if ($element instanceof Templater) $this->templaters[$code]=$element;
		}
	}
	
	public function spawn_and_append($element, $code=Context::DEFAULT_CODE)
	{
		$new_context=clone $this;
		$new_context->append($element, $code);
		return $new_context;
	}
	
	public function follow_track($track)
	{
		if (empty($this->elements)) return $this->sign_report(new Report_impossible('empty_context'));
		if ($track==='') return end($this->elements);
		if (array_key_exists($track, $this->elements)) return $this->elements[$track];
		return $this->sign_report(new Report_impossible('no_track'));
	}
	
	public function templaters()
	{
		return $this->templaters;
	}
}

// WIP
class Context_pokemon extends Context
{
	public
		$basic_code='Pokemon',
		$codes=
		[
			'owner'=>'owner_entity',
			'first_owner'=>'first_owner_entity',
			'species'=>'species_entity'
		];

	public static function from_pokemon($entity)
	{
		
	}
}
?>