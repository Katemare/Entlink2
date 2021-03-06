<?
namespace Pokeliga\Entity;

// отличается запоминанием того, изменилось ли что-нибудь по сравнению с БД, знанием о присвоении Keeper'ом и так далее. кроме того, только у этих значений есть понятие устаревания (irrelevant).
class Value_of_entity extends \Pokeliga\Data\Value implements \Pokeliga\Template\Bakeable, \Pokeliga\Template\ValueContent_stability
{
	const
		STATE_IRRELEVANT	=self::STATE_FAILED+1, // когда значение задано, а затем изменено, то если у значения есть зависимые значения - то они теряют актуальность, и далее по цепочке. неактуальные значения вычисляются заново при обращении к ним.
		BY_KEEPER			=self::BY_INPUT+1; // значение пришло из БД.
	
	public function before_set($content, $source)
	{
		$result=parent::before_set($content, $source);
		if (!$this->master->changed_from_db) $this->valid=true;
		// данные набора, не имеющего изменений по сравнению с БД (потому что данные не менялись), заведомо правильные при условии, что они нормализованы. значение changed_from_db меняется в вызовах before_set заинтересованными объектами. причём эта переменная у всего пула и его сущностей общая.
		else // эта ветка отвечает за случай, когда данные изменились по сравнению с БД. потому что если нет, то зависимые значения не требуют пересчёта. может, мы просто получили и присвоили значение из БД или же получили авто-значение на основе данных из БД.
		{
			$this->dependants_are_irrelevant();
		}
		// в противном случае содержимое считается изменившимся. константа в изменённой среде всё равно делает зависимые значения недействительными, даже если она не нуждается в проверке. может, мы только что заменили модель значения и теперь к нему применима другая константа.
		return $result;
	}
	
	public function to_fill()
	{
		return parent::to_fill() or $this->has_state(static::STATE_IRRELEVANT);
	}
	
	public function dependants_are_irrelevant()
	{
		if ( (!$this->in_value_model('dependants')) || ( count($dependants=$this->value_model_now('dependants'))==0) ) return;
		
		foreach ($dependants as $value_code)
		{
			$value=$this->master->produce_value($value_code);
			// в этом случае создаётся значение, которое, может, и не пригодится. но иначе никак не запомнить, что если когда таковое создастся, то данные в нём уже не следует получать кипером, а вычислять заново.
			$value->irrelevant_by($this);
		}	
	}
	
	public function irrelevant_by($by)
	{
		if ($this->has_state(static::STATE_IRRELEVANT)) return; // если значение устарело, то эта операция уже проводилась.
		
		if (!$this->has_state(static::STATE_FILLING))
		{
			if ($this->mode!==Value::MODE_SET) $this->set_state(static::STATE_IRRELEVANT);
			// OPTIM: сейчас если задаваемое значение несколько раз получает сигнал "irrelevant", то она не помнит, что уже получила его, и повторяет всё это каждый раз.
			
			if (!is_object($this->valid)) $this->valid=null;
			// даже если значение константно, нужно перепроверить: а что если изменилась модель?
			
			$this->save_changes=true;
			// FIXME: вообще значение пока не должно знать об этом параметре.
		}
		$this->dependants_are_irrelevant();
	}
	
	public function change_model($new_model)
	{
		$result=parent::change_model($new_model);
		if ($result===true) $this->irrelevant_by($new_model);
	}
	
	public function keeper_code()
	{
		if ($this->in_value_model('keeper')) return $this->value_model_now('keeper');
		if (!empty($keeper_code=$this->acting_type()->default_keeper)) return $keeper_code;
		return $this->master->default_keeper;
	}
	
	public function get_keeper_task()
	{
		$keeper_code=$this->keeper_code();
		if (empty($keeper_code)) return false;
		$keeper=\Pokeliga\Entity\Keeper::for_value($this, $keeper_code);
		return $keeper;
	}
	
	public function entity()
	{
		return $this->master->entity;
	}
	
	public function aspect_code()
	{
		if (!$this->in_value_model('aspect_code'))
		{
			$type=$this->entity()->type;
			$this->model['aspect_code']=$type::locate_name($this->code);
		}
		return $this->value_model_now('aspect_code');
	}
	
	public function get_aspect($now=true)
	{
		$aspect_code=$this->aspect_code();
		return $this->entity()->get_aspect($aspect_code, $now);
	}
	
	public function is_content_stable()
	{
		if ($this->value_model_or_default('stable', true)!==true) return false;
		
		$report=new \Pokeliga\Template\Report_stability($this);
		$report->expensive=$this->value_model_or_default('expensive', false);
		$report->stable_until=$this->value_model_or_default('stable_until', null);
		
		$keys=[$this->entity()->bake_key()=>$this->aspect_code()];
		
		if ($this->value_model_or_default('stable_if_resetable', false)===true) $report->resetable_only_keys=$keys;
		else $report->other_keys=$keys;
		$report->update_stability();
		return $report;
	}
	
	public function bake(&$elements_baked=0)
	{
		if ($this->is_failed()) return $this->report->bake($elements_baked);
		elseif ($this->is_filled()) return $this->bake_filled($elements_baked);
		else throw new \Exception('unexpected bake');
	}
	
	protected function bake_filled(&$elements_baked=0)
	{
		$baked_elements=1;
		return var_export($this->content, true); // FIXME: возможно, для некоторых случаев следует использовать специфичные для типа методы, например, для запекания объектов. но пока не ясно, насколько это нужно.
	}
}

?>