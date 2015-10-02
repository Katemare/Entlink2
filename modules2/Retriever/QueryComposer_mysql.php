<?

namespace Pokeliga\Retriever;

class QueryComposer_mysql extends QueryComposer
{
	public
		$next_subquery_id=0;

	// эта функция создаёт из массива с данными о запросе непосредственно запрос SQL.
	// на выходе - строка.
	public function compose()
	{
		$method='compose_'.$this->query['action'];
		return $this->$method();
	}
	
	public function __call($method, $args)
	{
		if (method_exists($this->query_obj, $method)) return $this->query_obj->$method(...$args);
		die('UNKNOWN QUERY METHOD');
	}
	
	public function __get($name)
	{
		if (property_exists($this->query_obj, $name)) return $this->query_obj->$name;
		die('UNKNOWN QUERY PROPERTY');
	}
	
	public function compose_select()
	{
		$start='';
		if (array_key_exists('union', $this->query))
		{
			$use_brackets=array_key_exists('order', $this->query) || array_key_exists('limit', $this->query) || array_key_exists('group', $this->query);
			
			$union=[];
			foreach ($this->query['union'] as &$subquery)
			{
				$subquery=Query::from_array($subquery);
				$union[]=$subquery->compose($this->operator);
			}
			if ($use_brackets) $start="(".implode(")\n UNION\n (", $union).")";
			else $start=implode("\n UNION\n ", $union);
			
			if ( (array_key_exists('fields', $this->query)) && (is_array($this->query['fields'])) )
			{
				$fields=[];
				foreach ($this->query['fields'] as $field)
				{
					$fields[]=$this->compose_select_field($field);
				}
				$fields=implode(', ', $fields);
				$start="SELECT $fields FROM ($start) `".$this->alias_prefix.$this->primary_alias()."`";
			}
		}
		else
		{
			if (!array_key_exists('fields', $this->query)) $fields=$this->compose_select_field('*');
			elseif (empty($this->query['fields'])) die ('NO SELECT FIELDS');
			elseif (is_array($this->query['fields']))
			{
				$fields=[];
				foreach ($this->query['fields'] as $field)
				{
					$fields[]=$this->compose_select_field($field);
				}
				$fields=implode(', ', $fields);
			}
			else die('BAD SELECT FIELDS');
			
			$where='';
			if ( (!empty($this->query['where']))&&(is_array($this->query['where'])) )
			{
				$wop=null;
				if (!empty($this->query['where_operator'])) $wop=$this->query['where_operator'];
				$where='WHERE '.$this->compose_where($this->query['where'], $wop);
			}
			
			$start=trim("SELECT $fields FROM ".$this->tables()." $where");
		}
		
		$order=$this->compose_order();
		if (!empty($order)) $order="ORDER BY $order";
		
		$limit='';
		if (!empty($this->query['limit'])) $limit="LIMIT ".$this->query['limit'][0].", ".$this->query['limit'][1];
		
		$group='';
		if (!empty($this->query['group']))
		{
			$group=[];
			if (is_string($this->query['group'])) $this->query['group']=[$this->query['group']];
			foreach ($this->query['group'] as $gr)
			{
				$group[]=$this->field_name($gr);
			}
			$group="group BY ".implode(', ', $group);
		}
		
		$result=trim("$start $group $order $limit");
		return $result;
	}
	
	public function compose_delete()
	{
		$where='';
		if ( (!empty($this->query['where']))&&(is_array($this->query['where'])) )
		{
			$wop=null;
			if (!empty($this->query['where_operator'])) $wop=$this->query['where_operator'];
			$where='WHERE '.$this->compose_where($this->query['where'], $wop);
		}
		if (empty($where)) die('DELETE ALL');
		
		$result=trim("DELETE FROM ".$this->tables()." $where");
		return $result;
	}
	
	public function compose_replace()
	{
		return $this->compose_insert();
	}
	
	public function compose_insert()
	{
		$first=true;
		$fields=[];
		foreach ($this->query['values'] as &$valueset)
		{
			if ($first)
			{
				foreach ($valueset as $key=>$value)
				{
					if (is_numeric($key)) die('UNIMPLEMENTED YET: complex insert fields');
					$fields[]=$key;
				}
				$first=false;
			}
			$valueset=$this->parse_fields($valueset);
			$valueset='('.implode(', ', $valueset).')';
		}
		
		if (!empty($this->query['insert_ignore'])) $this->query['action']='insert ignore';
		$result=strtoupper($this->query['action'])." INTO ".$this->tables()." (`".implode("`,`", $fields).'`) VALUES '.implode(', ', $this->query['values']);
		return $result;
	}	
	
	// FIX: требуется возможность изменять поля и в дополнительных таблицах.
	public function compose_update()
	{
		$fields=$this->compose_set_fields($this->query['set']);

		if (!empty($this->query['where']))
		{
			$where=$this->compose_where($this->query['where']);
		}
		else $where=null;

		$result="UPDATE ".$this->tables()." SET $fields";
		if ($where!==null) $result.=" WHERE $where";
		return $result;
	}
	
	// эта функция делает из массива where строку, которую нужно вставить в финальный строковый запрос.
	public function compose_where($where, $operator=null)
	{
		if ($operator===null) $operator='AND';
		$where=$this->parse_fields($where); // делает значения полей применимыми в запросе-строке. не трогает готовые условия, у которых числовой ключ.
		$result=array();
		foreach ($where as $key=>$value)
		{
			if (is_numeric($key)) $result[]=$value; // готовое условие.
			elseif (is_array($value)) $result[]=$this->field_name($key)." IN (".implode(', ', $value).")"; // набор значений.
			elseif ($value==="NULL") $result[]=$this->field_name($key)." IS NULL";
			else $result[]=$this->field_name($key)."=$value"; // одно значение.
		}
		$result=implode(' '.$operator.' ', $result);
		return $result;
	}
	
	public function compose_set_fields($set)
	{
		$set=$this->parse_fields($set); // делает значения полей применимыми в запросе-строке. не трогает готовые условия, у которых числовой ключ.
		$result=array();
		foreach ($set as $key=>$value)
		{
			if (is_numeric($key)) $result[]=$value; // готовое условие.
			elseif (is_array($value)) $result[]=$this->field_name($key)." IN (".implode(', ', $value).")"; // набор значений.
			elseif ($value==="NULL") $result[]=$this->field_name($key)."=NULL";
			else $result[]=$this->field_name($key)."=$value"; // одно значение.
		}
		$result=implode(', ', $result);
		return $result;
	}
	
	public function compose_select_field($field)
	{
		if ($field==='*')
		{
			$primary_alias=$this->primary_alias();
			if (empty($primary_alias)) return '*';
			else return "`".$this->alias_prefix.$primary_alias."`.*";
		}
		elseif (is_array($field))
		{
			if (array_key_exists('expression', $field)) $result=$this->prepare_expression($field['expression']);
			else
			{
				if (array_key_exists('table', $field)) $result=$this->field_name($field['field'], $field['table']);
				else $result=$this->field_name($field['field']);
				if (array_key_exists('function', $field))
					$result=strtoupper($field['function'])."(".((!empty($field['distinct']))?('DISTINCT '):(''))."$result)";
			}
			if (array_key_exists('alias', $field)) $result.=" `$field[alias]`";
			return $result;
		}
		else return $this->field_name($field);
	}
	
	public function prepare_expression($expression)
	{
		return preg_replace_callback
		(
			'/\{\{((?<table_alias>[^}]+?)\.)?(?<field>[^}]+?)\}\}/',
			function($m)
			{
				if (!empty($m['table_alias'])) return $this->field_name([$m['table_alias'], $m['field']]);
				else return $this->field_name($m['field']);
			},
			$expression
		);
	}
	
	public function compose_order()
	{
		if (empty($this->query['order'])) return '';
		$order=[];
		foreach ($this->query['order'] as $ord)
		{
			if ( (is_array($ord)) && (array_key_exists('expression', $ord)) ) $temp=$this->prepare_expression($ord['expression']);
			else $temp=$this->field_name($ord);
			if ( (is_array($ord)) && (array_key_exists('dir', $ord)) && (strtolower($ord['dir'])!=='asc') ) $temp.=" ".strtoupper($ord['dir']);
			$order[]=$temp;
		}
		return implode(', ', $order);
	}
	

	
	// эта функция подготавливает данные о полях, превращая их из голых значений и массивов в строки, которые можно включить в запрос. обрабатывает массивы fields и where запросов в стандартной форме, описанной выше.
	// эта функция должна выполняться непосредственно перед запросом, а не заранее! потому что иначе не удастся получить правильный insert_id();
	// $data содержит массив полей или условий согласно стандартному виду запроса (см. в начале класса).
	public function parse_fields($data)
	{
		foreach ($data as $field=>&$value)
		{
			if (is_numeric($field)) $value=$this->prepare_complex_field($value);
			else $value=$this->prepare_value($value);
		}
		return $data;
	}
	
	public function prepare_value($value)
	{
		if (is_array($value)) // если в поле массив значений для операций вроде id IN (2, 4, 10)
		{
			foreach ($value as &$val)
			{
				$val=$this->prepare_value($val);
			}
			if (count($value)==1) $value=reset($value); // массив из одного элемента преобразуется в банальное значение.
		}
		else
		{
			$value=$this->sql_value($value);
		}
		return $value;
	}
	
	// получает массив, описывающий условие. обязательные элементы:
	// 'field' - название поля. может быть массив [таблица, название].
	// другие элементы:
	// 'op' - операция. по умолчанию = , оно же при необходимости IN (значение, значение...). помимо очевидных, есть операция empty (0 или NULL) и not_empty (наоборот).
	// 'value' - с чем сравнивается, может быть массивом значений; или 'value_field' - с каким полем сравнивается (может быть массивом [таблица, название]. только для бинарных операций; 'value_expression' - готовое SQL-выражение или массив таковых; или 'value_fields' - массив таковых. если присутствуют нескольких этих полей, то по возможности оператора используются все. 'value_time' - функция модуля Cron.
	public function prepare_complex_field($data)
	{
		if
		(
			(array_key_exists('op', $data)) &&
			(in_array($data['op'], ['BETWEEN', 'NOT BETWEEN'])) &&
			(array_key_exists('include', $data)) &&
			($data['include']!==[true, true]) )
		{
			if (!array_key_exists('value', $data)) { vdump($data); die('UNIMPLEMENTED YET: advanced BETWEENs'); }
			if ((!is_array($data['value'])) || (count($data['value'])!==2)) die('BAD BETWEEN');
			
			if ($data['op']==='BETWEEN') $ops=['first'=>['>=', '>'], 'second'=>['<=', '<']];
			else $ops=['first'=>['<', '<='], 'second'=>['>', '>=']];
			$brackets=
			[
				['field'=>$data['field'], 'op'=>(($data['include'][0])?($ops['first'][0]):($ops['first'][1])), 'value'=>$data['value'][0]],
				['field'=>$data['field'], 'op'=>(($data['include'][1])?($ops['second'][0]):($ops['second'][1])), 'value'=>$data['value'][1]]
			];
			$subcondition=['brackets'=>$brackets, 'op'=>'AND'];
			return $this->prepare_complex_field($subcondition);
		}
	
		if (array_key_exists('expression', $data)) return $this->prepare_expression($data['expression']);
	
		if (array_key_exists('brackets', $data))
		{
			if (array_key_exists('op', $data)) $op=$data['op'];
			else $op='OR'; // иначе зачем были скобки?
			$inside=$this->compose_where($data['brackets'], $op);
			return "($inside)";
		}
	
		if (!array_key_exists('op', $data)) $data['op']='=';
		
		if (array_key_exists('subquery', $data))
		{
			$subquery=Query::from_array($data['subquery']);
			$subquery->alias_prefix=$this->alias_prefix.'subq'.($this->next_subquery_id++).'_';
			// по умолчанию - EXISTS
			if ( (!array_key_exists('exists', $data)) || ($data['exists']===true) ) return 'EXISTS ('.$subquery->compose($this->operator).')';
			else return 'NOT EXISTS ('.$subquery->compose($this->operator).')';
		}
		
		if ($this->unary_op($data['op'])) return $this->compose_unary_op($data['field'], $data['op']);
		
		$values=[];
		if (array_key_exists('value_subquery', $data))
		{
			$values=Query::from_array($data['value_subquery']);
			$values->alias_prefix=$this->alias_prefix.'subq'.($this->next_subquery_id++).'_';
		}
		else
		{
			if (array_key_exists('value', $data))
			{
				if (is_array($data['value']))
				{
					foreach ($data['value'] as $val)
					{
						$values[]=$this->sql_value($val);
					}
				}
				else $values[]=$this->sql_value($data['value']);
			}
			if (array_key_exists('value_field', $data))
			{
				$values[]=$this->field_name($data['value_field']);
			}
			if (array_key_exists('value_time', $data))
			{
				if (is_numeric($data['value_time'])) $values[]=time()+$data['value_time'];
				elseif (is_array($data['value_time']))
				{
					$copy=$data['value_time'];
					$code=array_shift($copy);
					$values[]=Engine()->module('Cron')->template($code, $copy);
				}
				else $values[]=Engine()->module('Cron')->template($data['value_time']);
			}
			if (array_key_exists('value_fields', $data))
			{
				foreach ($data['value_fields'] as $field_data)
				{
					$values[]=$this->field_name($field_data);
				}
			}
			if (array_key_exists('value_expression', $data)) // FIX: не поддерживает выражений, включающих подзапросы. ну, как не поддерживает: есть риск ошибок из-за совпадения названий полей.
			{
				if (!is_array($data['value_expression'])) $data['value_expression']=[$data['value_expression']];
				foreach ($data['value_expression'] as $expr)
				{
					$values[]=$this->prepare_expression($expr);
				}
			}
		}
		
		$value=null;
		if ($values instanceof Query)
		{
			// нет проверки на множественное значение, потому что подзапрос может вернуть и единственное значение, тогда операторы типа >= и * сработают.
			if ($data['op']==='=') $data['op']='IN';
			elseif ($data['op']==='!=') $data['op']='NOT IN';
			else die ('BAD SUBQUERY OP');
			$value="(".$values->compose($this->operator).")";
		}
		elseif (count($values)>1)
		{
			$multivalue=$this->op_accepts_multivalue($data['op']);
			if (!$multivalue) die('NO MULTIVALUE');
			if ($data['op']==='=') $data['op']='IN';
			elseif ($data['op']==='!=') $data['op']='NOT IN';
			elseif ( ($data['op']==='BETWEEN') || ($data['op']==='NOT_BETWEEN') )
			{
				if (count($values)!=2) die('BAD BETWEEN OP');
				$value=implode(' AND ', $values);
			}
			else die ('BAD MULTIVALUE OP');
			if ($value===null) $value='('.implode(', ', $values).')';
		}
		else $value=reset($values);
		
		$field_name=$this->field_name($data['field']);
		if ( ($value==="NULL") && ($data['op']==='=') ) $result=$field_name." IS NULL";
		elseif ( ($value==="NULL") && ($data['op']==='!=') ) $result=$field_name." IS NOT NULL";
		elseif ($data['op']==='find_in_set') $result="FIND_IN_SET($value, $field_name)";
		else $result=$field_name." ".$data['op']." ".$value;
		return $result;
	}
	
	public function op_accepts_multivalue($op)
	{
		return in_array($op, ['=', '!=', 'BETWEEN', 'NOT BETWEEN']);
	}
	
	public function unary_op($op)
	{
		return in_array($op, ['empty', 'not_empty', 'string_empty', 'string_not_empty', 'not_null']);
	}
	
	public function compose_unary_op($field, $op)
	{
		$field=$this->field_name($field);
		if ($op==='empty') return "( ($field IS NULL) OR ($field=0) )";
		if ($op==='not_empty') return "$field>0";
		if ($op==='string_empty') return "( ($field IS NULL) OR ($field='') )";
		if ($op==='string_not_empty') return "$field!=''";
		if ($op==='not_null') return "$field IS NOT NULL";
		die('BAD UNARY OP');
	}
	
	// предполагается, что если в метод поставляется строка, то первый элемент массива - это псевдоним таблицы без префикса.
	public function field_name($data, $table=null)
	{
		if ($table!==null) $data=[$table, $data];
		if (is_string($data))
		{
			$primary_alias=$this->primary_alias();
			if (empty($primary_alias)) return $this->wrap_title($data);
			return "`".$this->alias_prefix."$primary_alias`.".$this->wrap_title($data);
		}
		if (is_array($data))
		{
			if (array_key_exists(1, $data))
			{
				if ($data[0][0]==='<') return "`".substr($data[0], 1)."`.".$this->wrap_title($data[1]);
				// FIX: вероятно, обращение к старшему запросу в подзапросе должно быть выполнено иначе.
				return "`".$this->alias_prefix.$data[0]."`.".$this->wrap_title($data[1]);
			}
			return $this->field_name($data[0]);
		}
		vdump($data);
		vdump($this);
		die('BAD FIELD NAME');
	}
	
	public function wrap_title($title)
	{
		if ($title==='*') return $title;
		return "`$title`";
	}
	
	public function table_name($table, $alias)
	{
		$table_name=$this->db_prefix.$table;
		$alias=$this->alias_prefix.$alias;
		if ( ($alias===$table_name)||(empty($alias)) ) return "`$table_name`";
		return "`$table_name` `$alias`";
	}
	
	// подготавливает значение к тому, чтобы вставить его в запрос SQL.
	public function sql_value($value)
	{
		if (is_string($value)) $res="'".$this->operator->safe_text($value)."'";
		elseif ($value===null) $res="NULL";
		elseif (is_numeric($value)) $res=$value;
		elseif ($value===true) $res=1;
		elseif ($value===false) $res=0;
		else { debug_dump(); vdump($value); die ('INVALID DB VALUE'); }
		return $res;
	}
	
	public function tables()
	{
		$tables=[];
		$first=true;
		foreach ($this->query['table_alias'] as $alias=>$table)
		{
			if ($first)
			{
				$tables[]=$this->table_name($table, $this->primary_alias());
				$first=false;
			}
			else $tables[]=$this->table_name($table, $alias);
		}
		return implode(', ', $tables);
	}
}

?>