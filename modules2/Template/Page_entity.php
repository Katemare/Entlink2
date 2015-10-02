<?
namespace Pokeliga\Template;

interface Page_entity
{	
	public function entity();
	
	public function entity_id();
}

trait Page_entity_specific
{
	public
		$entity=false,
		$entity_input_field='replace_me';
	
	public function analyze_entity()
	{
		$result=$this->input_entity();
		if ($result===true) return $this->advance_step;
		else return $this->record_error($result);
	}
	
	public function input_entity()
	{
		$this->entity=null;
		$value=$this->input->produce_value($this->entity_input_field);
		if ($value->has_state(Value::STATE_FAILED)) $entity=$this->entity_by_default();
		else $entity=$value->get_entity();
		
		if (empty($entity)) return Page_entity::ERROR_NO_ENTITY;
		if (!$entity->exists()) return Page_entity::ERROR_BAD_ENTITY;
		
		if ( ($validate=$this->valid_etity($entity))===true) $this->entity=$entity;
		else return $validate;
		return true;
	}
	
	public function entity()
	{
		if ($this->entity===false) $this->input_entity();
		if (empty($this->entity)) return $this->sign_report(new \Report_impossible('no_entity'));
		return $this->entity;
	}
	
	public function entity_id()
	{
		$entity=$this->entity();
		if ($entity instanceof \Report_impossible) return $entity;
		return $entity->db_id;
	}
	
	public function valid_entity($entity)
	{
		return true;
	}
}

class Page_entity_view extends Page_view_from_db implements Page_entity
{
	use Page_entity_specific;
	
	public function setup_content($template)
	{
		parent::setup_content($template);
		if (empty($template->context)) $template->context=$this->entity();
	}
}

class Page_entity_operation extends Page_operation implements Page_entity
{
	use Page_entity_specific;
}

?>