<?
namespace Pokeliga\User;

// это значение содержит набор ссылок (именно ссылок!) в сочетании с правилом о том, как следует их читать.
class Value_post_links extends Value_linkset
{
	const
		STANDARD_SELECTOR='Select_post_links';
		
	public
		$link_rule,		// сущность типа LinkRule
		$post,			// работа, с перспективы которого рассматриваются ссылки.
		$pov;	// одна из констант из LinkRule. указывает, является ли корневая сущность значения объектом или субъектом в данном LinkRule.
		
	public static function for_post($post, $link_rule, $pov=LinkRule::POV_OBJECT)
	{
		$value=static::standalone();
		$value->post=$post;
		$value->link_rule=$link_rule;
		$value->pov=$pov;
		return $value;
	}
}

class Select_post_links extends Select_by_single_request
{
	public function create_request()
	{
		
	}
}

/*
	содержит произвольные ссылки на работу. но в отличие от других значений, данное скорее является интерфейсом доступа, чем значением, потому что заполняется оно по мере необходимости. иначе говоря, если для этого потока работ существуют заданные в админке правила связи А, Б, В и Г, то после запроса связей А и Б именно они будут в данном значении. потом, после запроса связей Г, в значении будут связи А, Б и Г. описывать это состояниями "наполнено" или "не наполнено" не полне правильно. поэтому образаться к значению следует только со списком правил связей (LinkRule), связи согласно которым необходипо получить.
*/
class ValueType_post_linkset extends \Pokeliga\Data\Value implements \Pokeliga\Data\Pathway
{
	const
		DEFAULT_SUBVALUE_FACTORY='Value_post_links';
	
	public
		$values=[];
	
	public function post()
	{
		return $this->master->entity;
	}
	
	public function request($code=null)
	{
		if ($code===null) die('NOT IMPLEMENTED YET: complete post links'); // в будущем, может, будет иметь смысл запрашивать весь список связей согласно 'link_rules' соответствующего потока работ.
		return parent::request($code);
	}
	
	public function value($code=null)
	{
		if ($code===null) die('NOT IMPLEMENTED YET: complete post links'); // в будущем, может, будет иметь смысл запрашивать весь список связей согласно 'link_rules' соответствующего потока работ.
		return parent::value($code);
	}
	
	public function relate_to_subvalue($code, $pov=null, $call_again, $call_followup)
	{
		$link_rule=$this->get_LinkRule_by_code($code, &$pov);
		if ($link_rule instanceof \Report_impossible) return $link_rule;
		$report=$link_rule->verify(false);
		if ($report instanceof \Report_impossible) return $report;
		elseif ($report instanceof \Report_tasks)
		{
			$task=Task_delayed_call::with_call($call_again, $report);
			return $this->sign_report(new \Report_task($task));
		}
		
		// на этом этапе мы точно знаем, что LinkRule проверена и существует.
		$this->supply_element($link_rule, $pov);
		return $call_followup($this->values[$key]);
	}
	
	public function ValueHost_request($code, $pov=LinkRule::POV_OBJECT)
	{
		return $this->relate_to_subvalue
		(
			$code, $pov,
			new Call([$this, 'ValueHost_request'], [$code, $pov]),
			function($subvalue) { return $subvalue->request(); }
		);
	}
	
	// FIX! избавиться от копипасты.
	public function template($name, $line=[])
	{
		return $this->relate_to_subvalue
		(
			$code, $pov,
			new Call([$this, 'template'], [$code, $line]),
			function($subvalue) { return $subvalue->template(null, $line); }
		);
	}
	
	public function follow_track($track, $pov=null)
	{
		return $this->relate_to_subvalue
		(
			$code, null,
			new Call([$this, 'follow_track'], [$track]),
			function($subvalue) { return $subvalue; }
		);
	}
	
	public function generate_ord_key($link_rule, $pov)
	{
		return $link_rule->db_id.'-'.$pov;
	}
	
	public function supply_element($link_rule, $pov=LinkRule::POV_OBJECT)
	{
		$key=$this->generate_ord_key($link_rule, $pov);
		if (array_key_exists($key, $this->values)) return;
		
		$element=Value_post_links::for_post($this->post(), $link_rule, $pov);
		$this->values[$key]=$element;
	}
	
	public function get_LinkRule_by_code($code, &$pov=null)
	{
		if ($code instanceof \Pokeliga\Entity\Entity) return $code;
		if (is_numeric($code)) return $this->pool()->entity_from_db_id($code, 'LinkRule');
		if (is_string($code))
		{
			static $ex=null, $convert=['obj'=>LinkRule::POV_OBJECT, 'subj'=>LinkRule::POV_SUBJECT];
			if ($ex===null) $ex='/^(?<code>['.ValueType_keyword::GOOD_CHARACTERS.']+):(?<pov>obj|subj)$/';
			if (preg_match($ex, $code, $m))
			{
				$code=$m['code'];
				if ($pov===null) $pov=$convert[$m['pov']];
			}
			elseif ($pov===null) $pov=LinkRule::POV_OBJECT;
			return $this->pool()->entity_by_provider(['by_field', 'slug', $code], 'LinkRule');
		}
		return $this->sign_report(new \Report_impossible('bad_LinkRule_code');
	}
}
?>