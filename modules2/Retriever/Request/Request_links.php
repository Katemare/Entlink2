<?
namespace Pokeliga\Retriever;

// class_alias('Request_by_field', 'Request_links'); // реализация запросов к таблицам вроде "эволюция покемонов" никак не отличается от обычного запроса к таблице по значениям полей.
// сама команда class_alias() находится в Request.php, чтобы не включать этот файл ради одной её.

// запрос к таблицам вроде adopts_logs, где кроме соответствия двух сущностей может быть указан тип их отношений.
// FIXME! непосредственно этот класс сейчас не используется - он только наслудеутеся классом Request_generic_links. значительная часть функционала в последнем должна перекочевать сюда.
class Request_links_with_relations extends Request_links
{
	use Request_get_data_two_args;
	
	static $instances=[];
	
	public
		$relations_irrelevant=false, // переключатель поведения: если true, то $relations всегда равны 'all'. для наследования.
		$relations=[],
		$retain_relations=false,
		$relation_field='relation',
		$by_relation=[],
		$by_id=[],
		$done=[];
	
	public function __construct($table=null, $field=null, $relation_field='relation')
	{
		$this->reset_relations();
		$this->relation_field=$relation_field;
		parent::__construct($table, $field);
	}
	
	// устанавливает начальное значение для требуемых отношений.
	public function reset_relations()
	{
		if ($this->relations_irrelevant) $this->relations='all';
		else $this->relations=[];
	}
	
	public function is_ready()
	{
		if (empty($this->relations)) return $this->sign_report(new \Report_impossible('no_relations'));
		return true;
	}
	
	public function create_query()
	{
		$query=parent::create_query();
		if ($this->relations!=='all') $query['where'][$this->relation_field]=$this->relations;
		return $query;
	}
	
	public function set_relations($relations)
	{
		if (empty($relations)) return $this->sign_report(new \Report_impossible('no_relations'));
		if ( (is_array($relations)) && (in_array('all', $relations)) ) $relations='all';
		if ($this->relations==='all') return;
		if ($relations==='all')
		{
			$this->relations='all';
			return;
		}
		if (!is_array($relations)) $relations=[$relations];
		$this->relations=array_unique(array_merge($this->relations, $relations));
	}
	
	public function normalize_keys(&$keys, &$relations)
	{
		if (!is_array($keys)) $keys=[$keys];
		if ( (is_array($relations)) && (in_array('all', $relations)) ) $relations='all';
		elseif ( ($relations!=='all') && (!is_array($relations)) ) $relations=[$relations];
	}
	
	public function set_data($keys=null, $relations=null)
	{
		$this->normalize_keys($keys, $relations);
		$this->filter_done_with_relations($keys, $relations);
		if ((empty($keys))&&(empty($relations))) return false;
		$this->add_keys_and_relations($keys, $relations);
		return true;
	}
	
	public function add_keys_and_relations($keys, $relations)
	{
		$this->add_keys($keys);
		$this->set_relations($relations);
	}
	
	public function filter_done_with_relations(&$ids, &$relations)
	{
		$uncompleted_ids=[];
		$uncompleted_relations=[];
		
		if (!is_array($relations)) $relations=[$relations];
		foreach ($ids as $id)
		{
			$key=$id.'-all';
			if (array_key_exists($key, $this->done)) continue;
			foreach ($relations as $relation)
			{
				$key=$id.'-'.$relation;
				if (!array_key_exists($key, $this->done))
				{
					$uncompleted_ids[]=$id;
					$uncompleted_relations[]=$relation;
				}
			}
		}
		$ids=$uncompleted_ids;
		$relations=$uncompleted_relations;
	}
	
	public function record_result($result)
	{
		parent::record_result($result);
		
		foreach ($result as $row)
		{
			$relation=$row[$this->relation_field];
			$key=$row[$this->field];
			$other=$row['id']; // строго говоря, это не айди сущности, с которой обнаружена связь, но пока это не требуется.
			
			if (!array_key_exists($relation, $this->by_relation)) $this->by_relation[$relation]=[];
			if (!array_key_exists($key, $this->by_relation[$relation])) $this->by_relation[$relation][$key]=[];
			$this->by_relation[$relation][$key][$other]=$row;
			
			if (!array_key_exists($key, $this->by_id)) $this->by_id[$key]=[];
			if (!array_key_exists($relation, $this->by_id[$key])) $this->by_id[$key][$relation]=[];
			$this->by_id[$key][$relation][$other]=$row;
		}
	}
	
	public function data_processed()
	{
		$relations=$this->relations;
		if (!is_array($relations)) $relations=[$relations];
		foreach ($this->requested as $id)
		{
			foreach ($relations as $relation)
			{
				$key=$id.'-'.$relation;
				$this->done[$key]=true;
			}
		}
		$this->requested=[];
		if (!$this->retain_relations) $this->reset_relations();
	}
	
	public function make_data_key($row)
	{
		return $row['id']; // по айди связи - она должны быть уникальной, в отличие от айди связанной сущности.
	}
	
	public function compose_data($value=null, $relations=null)
	{
		$this->normalize_keys($value, $relations);
		$all= $relations==='all';
		
		$result=[];
		foreach ($value as $val)
		{
			if (!array_key_exists($val, $this->by_id)) continue;
			if ($all) $relations=array_keys($this->by_id[$val]);
			foreach ($relations as $relation)
			{
				if (($all)||(array_key_exists($relation, $this->by_id[$val]))) $result=array_merge($result, $this->by_id[$val][$relation]);
			}
		}
		return $result;
	}
}

// запрос к общей таблице связей, где просто две сущности соответствуют друг другу неким отношением.
class Request_generic_links extends Request_links_with_relations 
{
	const
		FROM_OBJECT=0,
		FROM_SUBJECT=1;
		
	static $instances=[];
	
	public
		$entity_type=null,
		$entity2_type=null,
		$table='info_links',
		$type_field=null,
		$type2_field=null,
		$by_relation=[];
	
	public function create_query()
	{
		$query=parent::create_query();
		$query['where'][$this->type_field]=strtolower($this->entity_type);
		if ($this->entity2_type!==null) $query['where'][$this->type2_field]=strtolower($this->entity2_type);
		// COMP! в будущем strtolower не понадобится.
		
		return $query;
	}
	
	public function __construct($mode=Request_generic_links::FROM_OBJECT, $type=null, $entity2_type=null)
	{
		if ($mode===static::FROM_OBJECT)
		{
			$id_field='entity1_id';
			$this->type_field='entity1_type';
			$this->type2_field='entity2_type';
		}
		elseif ($mode===static::FROM_SUBJECT)
		{
			$id_field='entity2_id';
			$this->type_field='entity2_type';
			$this->type2_field='entity1_type';
		}
		else die ('UNKNOWN LINK MODE');
		parent::__construct($this->table, $id_field);		
		$this->entity_type=$type;
		$this->entity2_type=$entity2_type;
	}
}
?>