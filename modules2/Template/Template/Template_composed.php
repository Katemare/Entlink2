<?
namespace Pokeliga\Template;

abstract class Template_composed extends Template
{
	public
		$subtemplates=null,
		$default_on_empty='-';
	
	public function progress()
	{
		if ($this->subtemplates===null)
		{
			$this->subtemplates=[];
			$list=$this->spawn_subtasks();
			if ($list instanceof \Report_impossible)
			{
				$this->impossible($list);
				return;
			}
			elseif (empty($list))
			{
				if (array_key_exists('on_empty', $this->line)) $this->finish_with_resolution($this->line['on_empty']);
				elseif (array_key_exists('on_empty_template', $this->line)) die ('UNIMPLEMENTED YET: template on empty');
				else $this->finish_with_resolution($this->default_on_empty);
				return;
			}
			$this->process_subtasks($list);
			return;
		}
		$this->resolve();
	}
	
	public function process_subtasks($list)
	{
		foreach ($list as $subtask)
		{
			$this->setup_subtemplate($subtask);
			if ($subtask instanceof \Pokeliga\Task\Task)
			{
				$this->register_dependancy($subtask);
				$this->subtemplates[]=&$subtask->resolution;
			}
			elseif (!is_object($subtask)) $this->subtemplates[]=$subtask;
			else die ('BAD SUBTASK');
		}
	}
	
	public abstract function spawn_subtasks();
	
	public function resolve()
	{
		$this->finish_with_resolution($this->compose());
	}
	
	public function compose()
	{
		$before=''; $between=''; $after='';
		if (array_key_exists('preset', $this->line))
		{
			$preset=$this->preset_by_code($this->line['preset']);
			extract($preset);
		}
		elseif (array_key_exists('glue', $this->line)) $between=$this->line['glue']; else $between='';
		if (array_key_exists('before', $this->line)) $before=$this->line['before'].$before;
		if (array_key_exists('after', $this->line)) $after.=$this->line['after'];
		$result=$before.implode($between, $this->subtemplates).$after;
		return $result;
	}
	
	public function preset_by_code($code)
	{
		if ($code==='ol') return $this->preset_by_tags('ol', 'li');
		if ($code==='ul') return $this->preset_by_tags('ul', 'li');
		if ($code==='div') return $this->preset_by_tags(null, 'div');
		if ($code==='b') return $this->preset_by_tags(null, 'b', ', ');
	}
	
	public function preset_by_tags($master_tag, $element_tag, $glue=null)
	{
		$before=''; $between=''; $after='';
		if (!empty($element_tag))
		{
			$before='<'.$element_tag.'>';
			$after='</'.$element_tag.'>';
		}
		if (!empty($before_element)) $before=$before_element.$before;
		if (!empty($after_element)) $after.=$after_element;
		if (!empty($glue)) $between=$glue;
		$between=$after.$between.$before;
		if (!empty($master_tag))
		{
			$before='<'.$master_tag.'>'.$before;
			$after=$after.'</'.$master_tag.'>';
		}
		if (!empty($before_list)) $before=$before_list.$before;
		if (!empty($after_list)) $after=$after.$after_list;
		
		return ['before'=>$before, 'between'=>$between, 'after'=>$after];
	}
}

class Template_composed_preset extends Template_composed
{
	public
		$preset_list;
	
	public static function with_list($list, $line=[])
	{
		$template=static::with_line($line);
		$template->preset_list=$list;
		return $template;
	}
	
	public function spawn_subtasks()
	{
		return $this->preset_list;
	}
}

class Template_composed_call extends Template_composed
{
	public
		$call=null;
		
	public static function with_call($call, $line=[])
	{
		$template=static::with_line($line);
		$template->call=$call;
		return $template;
	}
	
	public function spawn_subtasks()
	{
		$call=$this->call;
		return $call($this->line);
	}
}

?>