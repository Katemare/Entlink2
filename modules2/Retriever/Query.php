<?
namespace Pokeliga\Retriever;

class Query implements \ArrayAccess, \Pokeliga\Entlink\Multiton_argument
{
	public
		$query,
		$db_prefix='',
		$alias_prefix='';
	
	public static function from_array($arr)
	{
		if ($arr instanceof Query) return $arr;
		$query=new static();
		$query->set_query($arr);
		return $query;
	}
	
	public static function to_array($query)
	{
		if (is_array($query)) return $query;
		return $query->query;
	}
	
	public function __construct()
	{
		$this->db_prefix=Retriever()->db_prefix;
	}
	
	public function Multiton_argument()
	{
		return array_reduce($this->query, 'flatten_Multiton_args');
	}
	
	public function set_query($arr)
	{
		$this->query=$arr;
		$this->normalize();
	}
	
	public function normalize()
	{
		if ( (array_key_exists('table', $this->query)) && (!is_array($this->query['table'])) ) $this->query['table']=[$this->query['table']];
		if ( (array_key_exists('order', $this->query)) && (!is_array($this->query['order'])) ) $this->query['order']=[$this->query['order']];
		if ( (array_key_exists('group', $this->query)) && (!is_array($this->query['group'])) ) $this->query['group']=[$this->query['group']];
		if ( (array_key_exists('limit', $this->query)) && (!is_array($this->query['limit'])) ) $this->query['limit']=[0, $this->query['limit']];
		if ( (array_key_exists('fields', $this->query)) && (!is_array($this->query['fields'])) ) $this->query['fields']=[$this->query['fields']];
		
		if ( (array_key_exists('table', $this->query)) && (!array_key_exists('table_alias', $this->query)) )
		{
			$alias=[];
			foreach ($this->query['table'] as $key=>$table)
			{
				if ( (empty($alias)) && (count($this->query['table'])>1) ) $alias[]='primary';
				elseif (is_numeric($key)) $alias[]=$table;
				else $alias[]=$key;
			}
			$this->query['table_alias']=array_combine($alias, $this->query['table']);
		}
		
		if (in_array($this->query['action'], ['insert', 'replace']))
		{
			if (array_key_exists('value', $this->query))
			{
				$this->query['values']=[$this->query['value']];
				unset($this->query['value']);
			}
			if (!array_key_exists('fields', $this->query)) $this->query['fields']=array_keys(reset($this->query['values']));
		}
	}
	
	public function offsetExists($offset)
	{
		return array_key_exists($offset, $this->query);
	}
	// FIX! когда это используется в форме $query[ключ][ключ]=значение, то не запускается normalize()
	public function &offsetGet($offset)
	{
		if (!array_key_exists($offset, $this->query)) $this->query[$offset]=[];
		return $this->query[$offset];
	}
	public function offsetSet($offset, $value)
	{
		$this->query[$offset]=$value;
		$this->normalize();
	}
	public function offsetUnset($offset)
	{
		unset($this->query[$offset]);
	}
	
	public function primary_table()
	{
		if (array_key_exists('union', $this->query))
		{
			$primary_table=null;
			foreach ($this->query['union'] as &$subquery)
			{
				$subquery=Query::from_array($subquery);
				$subquery_primary_table=$subquery->primary_table();
				if ($primary_table===null) $primary_table=$subquery_primary_table;
				elseif ($primary_table!==$subquery_primary_table) return false;
			}
			return $primary_table;
		}
		elseif (is_string($this->query['table'])) return $this->query['table'];
		return reset($this->query['table']);
	}
	
	public function multitable()
	{
		return (array_key_exists('table', $this->query)) && (is_array($this->query['table'])) && (count($this->query['table'])>1);
	}
	
	public function add_table($table, $alias=null, ...$conditions)
	{
		if ($alias===null) $alias=$table;
		if (array_key_exists($alias, $this->query['table_alias']))
		{
			if ($this->query['table_alias'][$alias]!==$table) die('TABLE ALIAS DOUBLE');
			foreach ($conditions as $condition)
			{
				if (!$this->has_complex_condition($condition))  die('TABLE ALIAS DOUBLE');
			}
			return; // таблица с таким названием, псевдонимом и условиями уже добавлена, ничего делать не надо.
		}
		$was_single_table=!$this->multitable();
		$this->query['table'][]=$table;
		$this->query['table_alias'][$alias]=$table;
		if ($was_single_table)
		{
			$primary=$this->primary_table();
			unset($this->query['table_alias'][$primary]);
			$this->query['table_alias']=array_merge(['primary'=>$primary], $this->query['table_alias']);
		}
		foreach ($conditions as $condition)
		{
			$this->add_complex_condition($condition);
		}
	}
	
	// добавляет новую таблицу и ставит её на место первичной. модифицирует остальные части запроса, чтобы вместо первичной таблицы они указывали на новый псевдоним таковой.
	public function add_primary($new_primary, $new_primary_alias=null)
	{
		if ($new_primary_alias===null)
		{
			$new_primary_alias='former_primary';
			$x=0;
			while (array_key_exists($new_primary_alias.$x, $this->query['table_alias'])) $x++;
			$new_primary_alias.=$x;
		}
		elseif (array_key_exists($new_primary_alias, $this->query['table_alias'])) die ('DOUBLE ALIAS');
		
		$fix_expression=function($m) use ($new_primary_alias)
		{
			return '{{'.$new_primary_alias.'.'.$m['field'].'}}';
		};
		
		if ( (array_key_exists('fields', $this->query)) && (is_array($this->query['fields'])) )
		{
			foreach ($this->query['fields'] as &$select_field)
			{
				if (is_string($field=$select_field))
				{
					$select_field=[$new_primary_alias, $field];
					continue;
				}
				if ( (array_key_exists('field', $select_field)) && (is_string($select_field['field'])) )
					$select_field['field']=[$new_primary_alias, $select_field['field']];
				if (array_key_exists('expression', $select_field))
					$select_field['expression']=preg_replace_callback('/\{\{(?<field>[^\.}]+?)\}\}/', $fix_expression, $select_field['expression']);
			}
		}
		
		if ( (array_key_exists('order', $this->query)) && (is_array($this->query['order'])) )
		{
			foreach ($this->query['order'] as &$order)
			{
				if (is_string($field=$order))
				{
					$order=[$new_primary_alias, $field];
					continue;
				}
				elseif ( (array_key_exists(0, $order)) && (!array_key_exists(1, $order)) ) array_unshift($order, $new_primary_alias);
				if (array_key_exists('expression', $order))
					$order['expression']=preg_replace_callback('/\{\{(?<field>[^\.}]+?)\}\}/', $fix_expression, $order['expression']);
			}
		}
		
		if ( (array_key_exists('set', $this->query)) && (is_array($this->query['set'])) ) die ('UNIMPLEMENTED YET: chaning primary for update');
		
		if ( (array_key_exists('where', $this->query)) && (is_array($this->query['where'])) )
			$this->change_primary_in_conditions($this->query['where'], $new_primary_alias, $fix_expression);
		
		if ( (array_key_exists('group', $this->query)) && (is_array($this->query['group'])) )
		{
			foreach ($this->query['group'] as &$group)
			{
				if (is_string($field=$group)) $group=[$new_primary_alias, $field];
			}
		}
		
		$former_primary=$this->primary_table();
		array_unshift($this->query['table'], $new_primary);
		unset($this->query['table_alias'][$former_primary]);
		$this->query['table_alias']['primary']=$new_primary;
		$this->query['table_alias'][$new_primary_alias]=$former_primary;
	}
	
	public function change_primary_in_conditions(&$conditions, $new_primary_alias, $fix_expression_callback)
	{
		foreach ($conditions as $key=>&$condition)
		{
			if (!is_numeric($key))
			{
				$new_condition=['field'=>[$new_primary_alias, $key], 'value'=>$condition];
				unset($conditions[$key]);
				$conditions[]=$new_condition;
				continue;
			}
			
			if (array_key_exists('brackets', $condition))
			{
				$this->change_primary_in_conditions($condition['brackets'], $new_primary_alias, $fix_expression_callback);
				continue;
			}
			if ( (array_key_exists('field', $condition)) && (!is_array($condition['field'])) )
				$condition['field']=[$new_primary_alias, $condition['field']];
			if (array_key_exists('expression', $condition))
				$condition['expression']=preg_replace_callback('/\{\{((?<field>[^\.}]+?)\}\}/', $fix_expression_callback, $condition['expression']);
			if ( (array_key_exists('value_field', $condition)) && (!is_array($condition['value_field'])) )
				$condition['value_field']=[$new_primary_alias, $condition['value_field']];
			if (array_key_exists('value_expression', $condition))
			{
				if (is_array($condition['value_expression']))
					foreach ($condition['value_expression'] as &$expr) $expr=preg_replace_callback('/\{\{(?<field>[^\.}]+?)\}\}/', $fix_expression_callback, $expr);
				else
					$condition['value_expression']=preg_replace_callback('/\{\{(?<field>[^\.}]+?)\}\}/', $fix_expression_callback, $condition['value_expression']);
			}
		}
	}
	
	/* для запросов со WHERE: SELECT, UPDATE */
	public function add_simple_condition($field, $value, $op='=')
	{
		if ($op!=='=')
		{
			$this->add_complex_condition(['field'=>$field, 'op'=>$op, 'value'=>$value]);
			return;
		}
		if (!array_key_exists('where', $this->query)) $this->query['where']=[];
		if (array_key_exists($field, $this->query['where']))
		{
			if ($this->query['where'][$field]===$value) return; // работает также для совпадающих массивов значений, хотя это и не цель.
			if (!is_array($this->query['where'][$field])) $this->query['where'][$field]=[$this->query['where'][$field]];
			if (is_array($value)) $this->query['where'][$field]=array_merge($this->query['where'][$field], $value);
			else $this->query['where'][$field][]=$value;
		}
		else $this->query['where'][$field]=$value;
	}
	
	public function remove_simple_condition($field)
	{
		unset($this->query['where'][$field]);
	}
	
	public function add_complex_condition($data)
	{
		if (!array_key_exists('where', $this->query)) $this->query['where']=[];
		$this->query['where'][]=$data;
		end($this->query['where']);
		return key($this->query['where']);
	}
	
	public function has_complex_condition($condition)
	{
		if (!array_key_exists('where', $this->query)) return false;
		return in_array($condition, $this->query['where']); // не должен учитывать порядок аргументов, так как сравнение не строгое.
	}
	
	public function add_bracketed_conditions($conditions, $op='OR')
	{
		$condition=['brackets'=>$conditions, 'op'=>$op];
		return $this->add_complex_condition($condition);
	}
	
	/* для запроса SELECT */
	public function reset_fields()
	{
		$this->query['fields']=null; // при отсутствии ключа получает все поля в первичной таблице в запросе 'select'.
	}
	
	public function default_fields()
	{
		if (!array_key_exists('fields', $this->query)) $this->query['fields']=['*'];
	}
	
	public function default_fields_no_asterisk()
	{
		if (!array_key_exists('fields', $this->query)) $this->query['fields']=[];
	}
	
	public function add_simple_field($field, $table=null) // пока без пседонимов
	{
		$this->default_fields();
		if ( ($table!==null) && ($table!==$this->primary_table()) ) $this->query['fields'][]=['table'=>$table, 'field'=>$field];
		else $this->query['fields'][]=$field;
	}
	
	public function add_simple_field_no_asterisk($field, $table=null) // пока без пседонимов
	{
		$this->default_fields_no_asterisk();
		$this->add_simple_field($field, $table);
	}
	
	public function add_group_function($function, $field, $table=null) // пока без пседонимов и выражений.
	{
		$this->default_fields();
		$complex=['function'=>$function, 'field'=>$field];
		if ( ($table!==null) && ($table!==$this->primary_table()) ) $complex['table']=$table;
		$this->query['fields'][]=$complex;
	}
	
	public function add_group_function_no_asterisk($function, $field, $table=null) // пока без пседонимов
	{
		$this->default_fields_no_asterisk();
		$this->add_group_function($function, $field, $table);
	}
	
	public function storeable()
	{
		if (array_key_exists('store_to', $this->query)) return true;
		if ($this->query['action']!=='select') return null;
		if (array_key_exists('union', $this->query))
		{
			if (array_key_exists('fields', $this->query)) return false;
			if ($this->primary_table()===false) return false;
			foreach ($this->query['union'] as &$subquery)
			{
				$subquery=Query::from_array($subquery);
				if (!$subquery->storeable()) return false;
			}
			return true;
		}
		if (empty($this->query['fields'])) return true;
		return false;
	}
	
	public function store_to()
	{
		if (!$this->storeable()) return;
		if (array_key_exists('store_to', $this->query)) return $this->query['store'];
		return $this->primary_table();
	}
	
	/* для UPDATE и REPLACE */
	
	public function set_simple($field, $value) // пока не включает возможности обработки нескольких таблиц.
	{
		if (!array_key_exists('set', $this->query)) $this->query['set']=[];
		$this->query['set'][$field]=$value;
	}
	
	public function set_expression($field, $expr) // пока не включает возможности обработки нескольких таблиц.
	{
		if (!array_key_exists('set', $this->query)) $this->query['set']=[];
		$this->query['set'][$field]=$expr;
	}
	
	public function primary_alias()
	{
		if ( (!$this->multitable()) && (empty($this->alias_prefix)) && (!array_key_exists('union', $this->query)) ) return '';
		if ( (array_key_exists('union', $this->query)) && (!array_key_exists('fields', $this->query)) ) return '';
		return 'primary';
	}
	
	public function compose($operator=null)
	{
		if ($operator===null) $operator=Retriever()->operator;
		$composer=$operator->query_composer_for($this);
		return $composer->compose();
	}
}
?>