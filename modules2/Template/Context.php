<?

namespace Pokeliga\Template;

// объекты с этим интерфейсом предоставляют список шаблонизаторов (именно шаблонизаторов, не ValueHost'ов), к которым можно обращаться для расшифровки ключевых слов.
interface Template_context
{
	public function templaters();
}

// черта для контекстов, которые включают в список шаблонизаторов только себя.
trait Context_self
{
	public function templaters()
	{
		return [$this];
	}
}

// класс для особого контекста шаблонов, предполагающих много 
class Context implements \Pokeliga\Data\Pathway, Template_context
{
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
	
	public function follow_track($track, $line=[])
	{
		if (empty($this->elements)) return $this->sign_report(new \Report_impossible('empty_context'));
		if ($track==='') return end($this->elements);
		if (array_key_exists($track, $this->elements)) return $this->elements[$track];
		return $this->sign_report(new \Report_impossible('no_track'));
	}
	
	public function templaters()
	{
		return $this->templaters;
	}
}

?>