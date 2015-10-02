<?
namespace Pokeliga\File;

class Template_img extends Template_from_db
{
	public
		$file=null,
		$db_key='standard.img',
		$elements=['src', 'class', 'title'],
		$modulate=false,
		$modulation_ops=null,
		$modulation_code=null,
		$brightness=100,
		$saturation=100,
		$hue=100;
	
	public function make_template($code, $line=[])
	{
		if ($code==='src')
		{
			if ( ($this->modulate) && (!empty($this->modulation_ops)) && (!empty(Engine()->config['imagemagick'])) )
			{
				$template=Template_img_modulated_src::with_line($line);
				$template->file=$this->file();
				$template->modulation_ops=$this->modulation_ops;
				$template->modulation_code=$this->modulation_code;
				return $template;
			}
			return $this->file()->template('url', $line);
		}
		if ($code==='class')
		{
			if (array_key_exists('class', $this->line)) return $this->line['class'];
			return '';
		}
		if ($code==='title')
		{
			if (array_key_exists('title', $this->line)) return $this->line['title'];
			return '';
		}
	}
	
	public function file()
	{
		return $this->context;
	}
	
	public function initiated()
	{
		parent::initiated();
		$this->modulate_from_line();
	}
	
	public function modulate_from_line()
	{
		if (empty(Engine()->config['imagemagick'])) return;
		if (!array_key_exists('modulate', $this->line)) return;
		if (!array_key_exists('modulation_code', $this->line)) return; // необходим для кэширования.
		
		$ops=explode(';', $this->line['modulate']);
		$good_ops=[];
		foreach ($ops as $key=>&$op)
		{
			if (!preg_match('/^\s*(?<code>[A-Z]{1,2})(?<params>\d{1,3}(,\d{1,3}){0,2})?\s*$/', $op, $m)) continue;
			$good_ops[$key]=[$m['code']];
			if ($m['params']!='') $good_ops[$key]=array_merge($good_ops[$key], explode(',', $m['params']));
		}
		if (empty($good_ops)) return;
		
		$this->modulation_code=$this->line['modulation_code'];
		$this->modulate=true;
		$this->modulation_ops=$good_ops;
	}
}

class Template_img_modulated_src extends Template
{
	use Task_steps;
	
	const
		STEP_PRE_REQUEST=0,
		STEP_COMPOSE_MODULATED_ADDRESS=1,
		STEP_MODULATE=2,
		STEP_FINISH=3;
	
	public
		$file,
		$modulated_path,
		$modulated_url,
		$modulation_ops=null,
		$modulation_code=null;
	
	public function run_step()
	{
		if ($this->step===static::STEP_PRE_REQUEST)
		{
			$tasks=[];
			$result=$this->file->request('path');
			if ($result instanceof \Report_tasks) $tasks=array_merge($tasks, $result->tasks);
			elseif ($result instanceof \Report_impossible) return $this->sign_report(new \Report_impossible('no_path'));
			
			$result=$this->file->request('name');
			if ($result instanceof \Report_tasks) $tasks=array_merge($tasks, $result->tasks);
			elseif ($result instanceof \Report_impossible) return $this->sign_report(new \Report_impossible('no_name'));
			if (empty($tasks)) return $this->advance_step();
			else return $this->sign_report(new \Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_COMPOSE_MODULATED_ADDRESS)
		{
			$path=$this->file->value('path');
			$name=$this->file->value('name');
			$this->modulated_path=$target=Engine()->server_address($path.'/modulated/'.$this->modulation_code.'.'.$name);
			$this->modulated_url=$target=Router()->url($path.'/modulated/'.$this->modulation_code.'.'.$name);
			if (file_exists($this->modulated_path)) return $this->advance_step(static::STEP_FINISH);
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_MODULATE)
		{
			$source_file=$this->file->value('server_address');			
			if (!is_array($this->modulation_ops)) $this->modulation_ops=unserialize($this->modulation_ops);
			static::modulate($source_file, $this->modulated_path, $this->modulation_ops, $this->modulation_code);
			return $this->advance_step();
		}
		elseif ($this->step===static::STEP_FINISH)
		{
			return $this->sign_report(new \Report_resolution($this->modulated_url));
		}
	}
	
	static function modulate($source_file, $target_file, $ops, $modulation_code)
	{
		copy ($source_file, $target_file);
		$pointer_moved=false;
		for ($op=reset($ops); $op!==false; $op=(($pointer_moved)?(current($ops)):(next($ops))) )
		{
			$command=null;
			$pointer_moved=false;
			$op_code=array_shift($op);
			if ($op_code==='M') // modulate
			{
				$command="convert $target_file \( +clone -fill black +opaque white \) \( -clone 0 -modulate ".implode(',', $op)." \) -swap 0,2 -delete 2 -compose lighten -composite $target_file";
			}
			elseif ($op_code==='B') // brightness
			{
				$command="convert $target_file -modulate $op[0] $target_file";
			}
			elseif ($op_code==='H') // hue
			{
				$command="convert $target_file -modulate 100,100,$op[0] $target_file";
			}
			elseif ($op_code==='C') // color (set hue)
			{
				$command="convert $target_file -colorspace HSL -channel Hue -evaluate set $op[0]% $target_file";
			}
			elseif ($op_code==='SP')
			{
				$spots=[$op];
				for ($op=next($ops); $op[0]==='SP'; $op=next($ops))
				{
					array_shift($op);
					$spots[]=$op;
				}
				$pointer_moved=true;

				static $radius=5;				
				foreach ($spots as &$spot)
				{
					$spot="circle $spot[0],$spot[1] $spot[0],".($spot[1]+$radius);
				}
				$full=Engine()->server_address('files/phenotypes', (substr($modulation_code, -3)==='_sh') ? 'spinda_full_shiny.png' : 'spinda_full.png' );
				$mask=Engine()->server_address('files/phenotypes', 'spinda_mask.png');
				$command="convert $target_file $full $mask \( -size 96x96 xc:black -fill white -draw '".implode(' ', $spots)."' \) \( -clone 2,3 -compose darken -composite \) -delete 2-3 -composite $target_file";
			}
			if ($command!==null) exec($command);
		}
	}
}

trait Template_file_related
{
	// FIX: нужно как-то иначе определять, что по умолчанию следует искать в том или ином модуле, поскольку разные части модуля могут создавать страницы непосредственного класса Template_from_db или других, не приспособленных специально под модуль. Возможно, в этом как-то должен быть задействован контекст.
	public function find_module_template($code, $line=[])
	{
		$adopts=Engine()->module('File');
		if (!is_null($element=$adopts->template($code, $line))) return $element;
		return parent::find_module_template($code, $line);
	}
	
	public function file()
	{
		// FIX! не работает, но этот код скоро будет удалён.
		if ($this->context instanceof File) return $this->context();
		die ('NO FILE');
	}
}

class Template_file_related_from_db extends Template_from_db
{
	use Template_file_related;
}

class Template_file_image_location_select extends Template_field_select
{
	use Template_file_related;
	
	public function make_options()
	{
		$locations=$this->file()->request('locations');
		if ( ($locations instanceof \Report_tasks) || ($locations instanceof \Report_impossible) ) return $locations;
		
		// значит, \Report_resolution.
		$locations=$locations->resolution;
		
	}
}
?>