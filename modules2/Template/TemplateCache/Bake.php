<?

namespace Pokeliga\Template;

/*
Это класс, отвечающий за создание "выпечки" - полуфабриката шаблона с заранее известным контекстом. Он оценивает целесообразность выпечки, подготавливает ключи, по котором этот кэш может быть сброшен, и разворачивает те элементы, которые могут быть развёрнуты.

Всё это можно оценить только когда шаблон будет готов.

Объекты с интерфейсом TemplateElement не только содержат данные для рендеринга, но и сведения о том, являются ли эти данные стабильными.
*/

class Bake
{
	const
		DEFAULT_LIFESPAN=86400,				// 1 день
		NEGLIGIBLE_LIFESPAN_SACRIFICE=0.5,	// доля времени жизни кэша, которым допустимо пожертвовать, чтобы побольше запечь.
		MIN_CACHE_LIFESPAN=60*60,			// минимальное время, на которое имеет смысл запекать кэш.
		MAX_KEYS=3,							// количество ячеек под ключи.
		SUBGROUPS_PER_KEY=2;				// количество ячеек при каждом ключе для подгрупп.
		
	protected
		$template,		// шаблон, который требутся запечь.
		
		$bake_keys=[],	// пары вида "ключ - [подгруппы]" или "ключ - null" (все подгруппы). первый по порядку ключ - основной.
		$time,			// зафиксированное время начала выпечки.
		$lifespan,		// планируемое время жизни кэша.
		$expiry;		// итого дата истечения срока годности.
	
	public function __construct($template)
	{
		$this->template=$template;
		$this->time=time();
		$this->lifespan=static::DEFAULT_LIFESPAN;
		$this->expiry=$this->time+$this->lifespan;
	}
	
	public function is_bakeable()
	{
		if ($this->template instanceof BakeHost) return $template->to_bake();
		return false;
	}
	
	
	public function bake()
	{
		if (!$this->is_bakeable()) return false;
		
		$baked_elements=0;
		$elements=$this->template->elements();
		$unbaked_elements=[];
		foreach ($elements as &$element)
		{
			if ($element instanceof TemplateElement)
			{
				$result=$element->bake_for($this, $elements_baked=0);
				if ($elements_baked>0)
				{
					$element=$result;
					$baked_elements+=$elements_baked;
				}
				else $unbaked_elements[]=&$element;
			}
		}
		if ($elements_baked>0)
		{
			foreach ($unbaked_elements as &$element)
			{
				$element=$element->precompile();
			}
			implode($elements);
		}
		else return false;
	}
	
	public function agree_to_conditions($stable_until, $resetable_only, $key, $subgroup, $expensive=false)
	{
		if ($stable_until!==null)
		{
			if ($stable_until<$this->time+static::MIN_CACHE_LIFESPAN) return false;
			if (!$expensive and $stable_until<$this->time+$this->lifespan*static::NEGLIGIBLE_LIFESPAN_SACRIFICE) return false;
		}
		if ($resetable_only!==null and !array_key_exists($key, $this->bake_keys) and count($this->bake_keys)>=static::MAX_KEYS) return false;
		
		// данные можно считать стабильными.
		if ($stable_until!==null and $stable_until<$this->expiry) $this->expiry=$stable_until;
		if ($key!==null)
		{
			if (!array_key_exists($key, $this->bake_keys)) $this->bake_keys[$key]=[];
			if ( ($subgroups=&$this->bake_keys][$key])!==null)
			{
				if ($subgroup===null) $subgroups=null;
				elseif (!in_array($subgroup, $subgroups, true) and count($subgroups)>=SUBGROUPS_PER_KEY) $subgroups=null;
				else $subgroups[]=$subgroup;
				unset($subgroups);
			}
		}
		return true;
	}
}



?>