<?
namespace Pokeliga\Nav;

interface Page_entity
{
	public function set_entity($entity);
	
	public function entity();
}

trait Page_entity_specific
{
	public
		$entity,
		$entity_input_field='replace_me';
		
	public function set_entity($entity)
	{
		if ( ($validate=$this->valid_entity($entity))!==true) $entity=
		$this->entity=$entity;
		
		if (empty($this->entity_input_field)) return;
		$inputset=$this->get_inputset();
		if (empty($inputset)) return;
		$inputset->set_value($this->entity_input_field, $entity, Value::BY_INPUT);
	}
	
	public function entity()
	{
		if (empty($this->entity)) $this->entity=$this->input_entity();
		return $this->entity;
	}
	
	public function entity_id()
	{
		$entity=$this->entity();
		if ($entity instanceof \Report_impossible) return $entity;
		return $entity->db_id;
	}
	
	public function input_entity()
	{
		$value=$this->input->produce_value($this->entity_input_field);
		if ($value->has_state(Value::STATE_FAILED)) $entity=$this->entity_by_default();
		else $entity=$value->get_entity();
		if (empty($entity)) return $this->sign_report(new \Report_impossible(static::ERROR_NO_ENTITY));
		if (!$entity->exists()) return $this->sign_report(new \Report_impossible(static::ERROR_BAD_ENTITY));
		return $entity;
	}
	
	public function analyze_input()
	{
		$entity=$this->entity();
		if ($entity instanceof \Report_impossible) return $this->record_error(reset($entity));
		if ( ($validate=$this->valid_entity($entity))!==true) return $this->record_error($validate);
		return $this->advance_step();
	}
	
	public function valid_entity($entity)
	{
		return true;
	}

	public function relevant_module()
	{
		if (($result=parent::relevant_module()) instanceof \Pokeliga\Entlink\Module) return $result;
		$entity=$this->entity();
		if (empty($entity)) return;
		$type=$entity->type;
		if (!empty($type::$module_slug)) return Engine()->module_by_slug($type::$module_slug);
	}
	
	public function setup_content($template)
	{
		parent::setup_content($template);
		if (empty($template->context)) $template->context=$this->entity();
	}
}

class Page_entity_view extends Page_view_from_db implements Page_entity
{
	use Page_entity_specific;
}

class Page_entity_operation extends Page_operation implements Page_entity
{
	use Page_entity_specific;
}

?>