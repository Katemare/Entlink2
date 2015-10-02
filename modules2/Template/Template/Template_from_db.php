<?
namespace Pokeliga\Template;

class Template_from_db extends Template_from_text implements CodeHost
{
	const
		STEP_GET_KEY=0,
		STEP_GET_TEXT=1,
		STEP_EVAL_ONCE=2,
		STEP_EVAL=3,
		STEP_COMPOSE=4,
		
		TEMPLATE_TABLE='info_templates',
		TEMPLATE_FIELD='template',
		KEY_FIELD='code',
		COMPILED_FIELD='compiled2',
		EVAL_ONCE_FIELD='eval_once';
	
	static
		$eval_once=[],
		$codefrag_cache=[];
	
	public
		$db_key=null;
	
	public static function with_db_key($key, $line=[])
	{
		$template=static::with_line($line);
		$template->db_key=$key;
		return $template;
	}
	
	public function codefrag($type, $id)
	{
		if (array_key_exists($id, static::$codefrag_cache)) die ('CODEFRAG DOUBLE');
		$args=func_get_args();
		static::$codefrag_cache[$this->db_key][$id]=$args;
	}
	
	public $codefrags=[];
	public function get_codefrag($id)
	{
		if (!array_key_exists($id, $this->codefrags))
		{
			if (!array_key_exists($this->db_key, static::$codefrag_cache)) die ('UNKNOWN CODEFRAG');
			if (!array_key_exists($id, static::$codefrag_cache[$this->db_key])) die ('UNKNOWN CODEFRAG');
			$frag=static::$codefrag_cache[$this->db_key][$id];
			if (!is_object($frag))
			{
				$frag=CodeFragment::create_unattached(...$frag);
				static::$codefrag_cache[$this->db_key][$id]=$frag;
			}
			$this->codefrags[$id]=$frag->clone_for_host($this);
		}
		return $this->codefrags[$id];
	}
	
	public $previous_codefrag=null;
	public function command($id)
	{
		$this->buffer_store_output();
		
		$codefrag=$this->get_codefrag($id);
		if ($this->previous_codefrag!==null) $codefrag->previous=$this->previous_codefrag;
		$this->previous_codefrag=$codefrag;
		
		$this->buffer_store_subtemplate($codefrag);
	}
	
	public function run_step()
	{
		if ($this->step===static::STEP_GET_KEY)
		{
			$result=$this->db_key(false);
			if ($result instanceof \Report) return $result;
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_EVAL_ONCE)
		{
			$this->text(); // заставляет получить и проанализировать данные из БД, в том числе дописать в static::$eval_once.
			$db_key=$this->db_key();
			if ( (array_key_exists($db_key, static::$eval_once)) && (static::$eval_once[$db_key]!==null) )
			{
				eval(static::$eval_once[$db_key]);
				static::$eval_once[$db_key]=null;
			}
			return $this->advance_step();
		}
		else return parent::run_step();
	}
	
	public function db_key($now=true)
	{
		if ($this->db_key!==null) return $this->db_key;
		$result=$this->get_db_key($now);
		if ($result instanceof \Report) return $result;
		$this->db_key=$result;
		return $this->db_key;
	}
	
	public function get_db_key($now=true)
	{
		vdump($this);
		die('NO DB KEY RETRIEVAL');	
	}
	
	public function get_text($now=true)
	{
		if ($now) $mode=Request::GET_DATA_NOW;
		else $mode=Request::GET_DATA_SET;
		
		$data=$this->get_request()->get_data($db_key=$this->db_key(), $mode);
		if ($data instanceof \Report) return $data;
		if (!array_key_exists($db_key, static::$eval_once)) static::$eval_once[$db_key]=$data[static::EVAL_ONCE_FIELD];
		if ($data['plain']) $this->plain=true;
		return $data[static::COMPILED_FIELD];
	}
	
	public $request=null;
	public function get_request()
	{
		if ($this->request===null)
		{
			$this->request=Request_by_unique_field::instance(static::TEMPLATE_TABLE, static::KEY_FIELD);
		}
		return $this->request;
	}
	
	public function impossible($errors=null)
	{
		if ($errors===null) $details='unknown error';
		elseif (is_array($errors)) $details=implode(', ', $errors);
		else $details=$errors;
		
		$this->resolution='NO TEMPLATE: '.$this->db_key.' ('.$details.')';
		parent::impossible();
	}
	
	public function human_readable()
	{
		return get_class($this).'['.$this->object_id.','.$this->db_key.'] ('.$this->report()->human_readable().')';
	}
}

?>