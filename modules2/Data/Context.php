<?

interface HasContext
{
	public function get_context();
}

// объекты с этим интерфейсом предоставляют список шаблонизаторов (именно шаблонизаторов, не ValueHost'ов), к которым можно обращаться для расшифровки ключевых слов.
interface Context extends Pathway
{
	public function gateways();
}

trait Context_self
{
	public function gateways() { return; yield; } // создаёт пустой генератор
}

// класс для особого контекста шаблонов, предполагающих много 
class SpecialContext implements Context
{
	const
		DEFAULT_CODE='context';

	public
		$master,
		$gateways=[];
		
	public static function for_master($master)
	{
		$context=new static();
		$context->master=$master;
		$context->setup();
		return $context;
	}
	
	public function setup() { }
	
	// элементом может быть Templater, ValueHost и/или Pathway.
	public function append($element, $code=Context::DEFAULT_CODE)
	{
		if (is_array($element))
		{
			foreach ($element as $code=>$elem) $this->append($elem, $code);
		}
		else $this->gateways[$code]=$element;
	}
	
	public function clone_and_append($element, $code=Context::DEFAULT_CODE)
	{
		$new_context=clone $this;
		$new_context->append($element, $code);
		return $new_context;
	}
	
	public function follow_track($track, $line=[])
	{
		if (empty($this->gateways)) return;
		if (array_key_exists($track, $this->gateways)) return $this->gateways[$track];
	}
	
	public function gateways()
	{
		if (empty($this->gateways)) return;
		foreach ($this->gateways as $gateway) yield $gateway;
	}
}

?>