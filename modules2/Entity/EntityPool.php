<?

namespace Pokeliga\Entity;

class EntityPool
{
	use \Pokeliga\Entlink\Object_id;
	
	const
		MODE_READ_ONLY=1,	// если значение стало FILLED, оно уже не меняется. не требуются действия, срабатывающие после изменения сущностей, и можно активно кэшировать значения.
		MODE_VIRTUAL=2,		// значения могут меняться, но данные не будут сохранены и поэтому не требуются действия, обеспечивающие сохранения.
		MODE_OPERATION=3;	// проводятся операции, изменяющие значения и предполагающие запись в БД.
	
	static
		$default_pool=null;

	public
		$parent_pool=null,
		$entities_by_db_id=[],
		$entities_by_entity_id=[],
		$mode,
		$changed_from_db=false;
		// если истина, значит, в пуле содержатся сущности, чьи данные были измененны по сравнению с БД. этот параметр должен поменяться сразу у всех сущностей, составляющих пул, поэтому при добавлении сущности в пул он связывается.
	
	public function __construct($mode=EntityPool::MODE_READ_ONLY, $parent_pool=null)
	{
		$this->mode=$mode;
		if (!is_null($parent_pool))
		{
			if ($parent_pool!==static::$default_pool) die ('NON-DEFAULT PARENT POOL'); // STUB: пока множественная вложенность пулов не реализована.
			if ($mode!==EntityPool::MODE_VIRTUAL) die ('NON-VIRTUAL SUBPOOL'); // STUB: не реализованы никакие другие режимы под-пулов.
		}
		$this->parent_pool=$parent_pool;
		$this->generate_object_id();
	}
	
	public function read_only()
	{
		return $this->mode===static::MODE_READ_ONLY;
	}
	
	public function saveable()
	{
		return $this->mode===static::MODE_OPERATION;
	}
	
	public static function default_pool($create=EntityPool::MODE_READ_ONLY)
	{
		if (static::$default_pool!==null) return static::$default_pool;
		if ($create===false) return false;
		$default_pool=new EntityPool($create); // STUB! необходимо предположение о том, должен ли пул быть только для чтения.
		static::$default_pool=$default_pool;
		return $default_pool;
	}
	
	public function be_default_pool()
	{
		static::$default_pool=$this;
	}
	
	public function entity_from_db_id($id, $id_group=null)
	{
		$key=(string)$id_group;
		if ( (array_key_exists($key, $this->entities_by_db_id)) && (array_key_exists($id, $this->entities_by_db_id[$key])) )
			return $this->entities_by_db_id[$key][$id];
		
		if (!empty($this->parent_pool))
		{
			$entity=$this->parent_pool->entity_from_db_id($id, $id_group);
			if (!empty($entity)) return $this->clone_entity($entity);
		}
		
		$entity=Entity::from_db_id($id, $id_group, $this);
		return $entity;
	}
	
	public function entity_from_provider($provider_data, $id_group=null)
	{
		$entity=Entity::from_provider($provider_data, $id_group, $this);
		// регистрация сущности в пуле осуществляется в рамках метода setup() этой сущности, после того, как данные сформировались.
		return $entity;
	}
	
	public function new_entity($id_group=null)
	{
		$entity=Entity::create_new($id_group, $this);
		return $entity;
	}
	
	public function virtual_entity($id_group=null)
	{
		$entity=Entity::create_new($id_group, $this);
		return $entity;
	}
	
	public function register_entity($entity)
	{
		if ($entity->pool!==$this) die ('WRONG POOL 1');
		if (array_key_exists($entity->object_id, $this->entities_by_entity_id)) die ('DOUBLE IN POOL');
		
		if ($entity->changed_from_db) $this->changed_from_db=true;
		$entity->changed_from_db=&$this->changed_from_db;
		
		$this->entities_by_entity_id[$entity->object_id]=$entity;
		if ($entity->has_db_id()) $this->register_entity_id($entity);
		elseif ($entity->expects_db_id())
		{
			$entity->add_call
			(
				function($entity) // предполагает черту Caller_backreference
				{
					$this->register_entity_id($entity);
				},
				'received_id'
			);			
		}
	}
	
	public function register_entity_id($entity)
	{
		$key=(string)$entity->id_group;
		if (!array_key_exists($key, $this->entities_by_db_id)) $this->entities_by_db_id[$key]=[];
		
		if (array_key_exists($entity->db_id, $this->entities_by_db_id[$key]))
		{
			$existing_entity=$this->entities_by_db_id[$key][$entity->db_id];
			$entity->equalize($existing_entity);
		}
		else $this->entities_by_db_id[$key][$entity->db_id]=$entity;
	}
	
	// WIP! метод нуждается в обновлении.
	public function save($entities=null, $if_all_valid=true)
	{
		if ($this->mode!==static::MODE_OPERATION) die ('BAD POOL MODE 2');
		if ($entities===null) $entitues=$this->entities_by_entity_id;
		
		$validators=[];
		foreach ($entities as $entity)
		{
			if ($entity->pool!==$this) die ('ENTITY FROM WRONG POOL');
			$result=$entity->validate_request();
			if ( ($if_all_valid) && ($result instanceof \Report_impossible) ) return $this->sign_report(new \Report_impossible('not_all_valid')); // этот отчёт может придти по разным причинам - потому что сущность находится в состоянии FAILED, потому что по тем или иным причинам даже процесс валидации начать невозможно, потому что валидатор признал данные неверными... главное, что сущность не подлежит сохранению в БД в таком случае.
			if ($result instanceof \Report_tasks) $validators[$entity->object_id]=$result;
		}
	
		foreach ($validators as $entity_id=>$report)
		{
			$process=$report->create_process();
			$process->complete();
			if ($process->failed())
			{
				if ($if_all_valid) return $this->sign_report(new \Report_impossible('not_all_valid'));
				unset($entities[$entity_id]);
			}
		}
		
		$result=[];
		foreach ($entities as $entity)
		{
			$result[$entity->object_id]=$entity->save();
		}
		return $result;
	}
	
	public function clone_entity($entity)
	{
		if ($entity->pool===$this) die ('WRONG POOL 2');
		$new_entity=clone $entity;
		$new_entity->pool=$this;
		$new_entity->cloned_from_pool($this);
		$this->register_entity($new_entity);
		$new_entity->make_calls('cloned_from_pool');
		return $new_entity;
	}
}

?>