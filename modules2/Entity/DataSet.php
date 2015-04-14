<?
load_debug_concern('Entity', 'DataSet');

class DataSet extends ValueSet implements Templater
{
	use Logger_DataSet;

	public
		$entity,
		$default_keeper='db';
	
	public static function for_entity($entity)
	{
		$dataset=new DataSet();
		$dataset->entity=$entity;
		return $dataset;
		// модель задаётся позднее типизатором
	}
	
	public function pool()
	{
		return $this->entity->pool;
	}
	
	public function create_value($code)
	{
		$value=parent::create_value($code);
		$this->log('create_value', ['code'=>$code]);
		
		if ($this->entity->state===Entity::STATE_NEW) $value->save_changes=true;
		else $value->save_changes=false;
		// этот параметр отсутствует у обычных значений, которые далеко не всегда хранятся в БД, поэтому при создании их для аспекта параметр добавляется. Он должен стать true в следующих случаях:
		// 1. Содержимое значение было получено из БД и изменено. При команде сохранения сущности изменения должны быть записаны в БД.
		// 2. Значение, которое при взялось бы из БД, потеряло актуальность (потому что изменилось нечто, от чего оно зависит) ещё до того, как его извлекли из БД. Аналогично, при команде сохранения сущности новые данные должны быть записаны в БД.
		// этот параметр отслеживается даже у значений, у которых нет кипера, чтобы не заморачиваться проверками.
		
		return $value;
	}
	
	public function before_value_set($value, $content, $source)
	{
		parent::before_value_set($value, $content, $source);
		
		$entity=$value->master->entity;
		if ( ($value->has_state(Value::STATE_FILLED)) && ($entity->pool->read_only()) /* OPTIM: возможно, это нужно как-то сократить. */ )
			die ('SETTING READ ONLY');
		if ($entity->state===Entity::STATE_FAILED) die ('SETTING FOR FAILED ENTITY');
		// if ($entity->is_to_verify()) { vdump($entity); vdump($value); vdump($content); xdebug_print_function_stack(); die ('SETTING FOR UNVERIFIED ENTITY'); }
			
		$value->save_changes = $source!==Value::BY_KEEPER; // если изменённое значение переписывается из БД, то его всё же сохранять не надо.
	}
	
	// задача этого запроса - немедленно возвратить значение. Если для этого требуется завершить процесс (задачу), то она немедленно завершается.
	public function value($value)
	{
		$value=$this->produce_value($value); // на случай, если аргумент - код, а не готовое значение.
		return $value->value();
	}
	
	public function value_object($value)
	{
		return $this->produce_value($value);
	}
	
	// разница между этим и предыдущим методом в том, как его обрабатывает EntityType, а не этот класс. Перед выполнением предыдущего метода сущность подтверждается сразу, а перед выполнением этого может быть возвращён процесс.
	public function value_object_request($value)
	{
		return $this->produce_value($value);
	}
	
	// задача этого метода - перевести значение из состояния UNFILLED или IRRELEVANT в состояние FILLED (наполнено, готово), FAILED (невозможно наполнить) или FILLING (наполняется, создана задача, результатом которой является наполнение данного значения). Этот метод вызывается только самим значением, когда оно находится в состоянии UNFILLED или IRRELEVANT.
	public function fill_value($value)
	{
		$value=$this->produce_value($value); // на случай, если аргумент - код, а не готовое значение.
		// STUB: дальнейшее пока заглушка.
		$this->log('filling_value', ['value'=>$value]);
		$filler=Filler_for_entity_generic::for_value($value);
		$report=$filler->master_fill(); // филлер уже делает всё необходимое с состоянием и содержимым значения.
	}
	
	public function change_model($value_code, $new_model, $rewrite=true)
	{
		// STUB! получение таблицы вообще должно делаться иначе.
		if (!array_key_exists('table', $new_model))
		{
			$current_model=$this->model($value_code);
			if ( (!empty($current_model)) && (array_key_exists('table', $current_model)) ) $new_model['table']=$current_model['table'];
		}
		
		parent::change_model($value_code, $new_model, $rewrite);
	}
	
	public function cloned_from_pool($pool)
	{
		if (!empty($this->values))
		{
			$cloned_values=[];
			foreach ($this->values as $key=>$value)
			{
				if (!is_null($value->filler_task)) die ('CLONING VALUE IN PROCESS');
				$value=clone $value;
				if ($value instanceof Value_contains_pool_member) $value->reset();
				if ($valeu instanceof Value_handles_cloning) $value->cloned_from_pool($pool);
				$value->master=$this;
				$cloned_values[$key]=$value;
			}
			$this->values=$cloned_values;
		}
	}
	
	public function template($name, $line=[])
	{
		$value=$this->produce_value($name);
		$template=$value->template(null, $line);
		return $template;
	}
}

?>