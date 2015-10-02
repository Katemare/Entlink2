<?
namespace Pokeliga\File;
// FIXME: в будущем подобные специфические правила страницы должны быть сделаны неким общим механизмом.

class Page_file_from_db extends Page_view_from_db
{
	public
		$db_key_base='file',
		$template_class='Template_page_pokeliga', // FIXME: это должно неким образом ссылаться на общее текущее оформление сайта или же доставать откуда-то подходящее для случая.
		$master_db_key='standard.page',
		$content_class='Template_file_related_from_db';
	
	public function pgtitle()
	{
		return 'Файлохранилище';
	}
	
	// FIXME! страница должна как-то иначе получать сведения о стандартном оформлении. может быть, это не исключительная ситуация, когда страница одного модуля должна быть заключена в оформление другого.
	public function create_content()
	{
		$this->register_requirement('css', Router()->module_url('AdoptsGame', 'adopts.css'));
		return parent::create_content();
	}
}

// Страницы про файлы

interface Page_file
{
	const
		// в качестве кодов ошибок придётся использовать строки, чтобы избежать пересечения с другими классами и модулями.
		ERROR_NO_FILE='no_file',
		ERROR_BAD_FILE='bad_file';

	public function file();
}

trait Page_file_specific
{
	public $file=null;
	
	public function analyze_input()
	{
		$value=$this->input->produce_value('file');
		if (!$value->has_state(Value::STATE_FAILED)) $file=$value->get_entity();
		
		if (empty($file)) return $this->record_error(Page_file::ERROR_NO_FILE);
		
		if (!$file->exists()) return $this->record_error(Page_file::ERROR_BAD_FILE);
		
		if ( ($validate=$this->valid_file($file))===true) $this->file=$file;
		else return $this->record_error($validate);
		return $this->advance_step();
	}
	
	public function file()
	{
		return $this->file;
	}
	
	public function entity_id()
	{
		return $this->file()->db_id;
	}
	
	public function valid_file($file)
	{
		return true;
	}
}

class Page_file_view extends Page_file_from_db implements Page_file
{
	use Page_file_specific;
	
	public function setup_content($template)
	{
		parent::setup_content($template);
		if ($template->context===null) $template->context=$this->file();
	}
}

// страницы про локации и пункты
interface Page_image_location
{
	const
		// в качестве кодов ошибок придётся использовать строки, чтобы избежать пересечения с другими классами и модулями.
		ERROR_NO_LOCATION='no_image_location',
		ERROR_BAD_LOCATION='bad_image_location';

	public function image_location();
}

trait Page_image_location_specific
{
	public $location=null;
	
	public function analyze_input()
	{
		$value=$this->input->produce_value('location');
		if (!$value->has_state(Value::STATE_FAILED)) $location=$value->get_entity();
		
		if (empty($location)) return $this->record_error(Page_image_location::ERROR_NO_LOCATION);
		
		if (!$location->exists()) return $this->record_error(Page_image_location::ERROR_BAD_LOCATION);
		
		if ( ($validate=$this->valid_image_location($location))===true) $this->location=$location;
		else return $this->record_error($validate);
		return $this->advance_step();
	}
	
	public function image_location()
	{
		return $this->location;
	}
	
	public function entity_id()
	{
		return $this->image_location()->db_id;
	}
	
	public function valid_image_location($location)
	{
		return true;
	}
}

class Page_image_location_view extends Page_file_from_db implements Page_image_location
{
	use Page_image_location_specific;
	
	public function setup_content($template)
	{
		parent::setup_content($template);
		if ($template->context===null) $template->context=$this->image_location();
	}
}

class Page_file_new_image_point extends Page_file_view
{
	public
		$db_key='new_image_point',
		$input_model=
		[
			'file'=>
			[
				'type'=>'entity',
				'id_group'=>'Image' // FIXME: это сработает даже если файл не является картинкой, потому что пока динамического определения типа нет.
			]
		];
		
	public function pgtitle()
	{	
		return 'Новый пункт,<a href="gallery.php">Галерея</a>,'.parent::pgtitle();
	}
}

class Page_file_new_image_fragment extends Page_file_view
{
	public
		$db_key='new_image_fragment',
		$input_model=
		[
			'file'=>
			[
				'type'=>'entity',
				'id_group'=>'Image' // FIXME: это сработает даже если файл не является картинкой, потому что пока динамического определения типа нет.
			]
		];
		
	public function pgtitle()
	{	
		return 'Новый фрагмент,<a href="gallery.php">Галерея</a>,'.parent::pgtitle();
	}
}

class Page_image_location_edit extends Page_image_location_view
{
	public
		$db_key='edit_image_location',
		$input_model=
		[
			'location'=>
			[
				'type'=>'entity',
				'id_group'=>'ImageLocation'
			]
		],
		$mode;
	
	public function template($code, $line=[])
	{
		if ($code==='location_type')
		{
			if ($this->mode===Image::LOCATION_POINT) return 'пункт';
			elseif ($this->mode===Image::LOCATION_FRAGMENT) return 'фрагмент';
			else return '???';
		}
	}
	
	public function analyze_content()
	{
		$result=parent::analyze_content();
		if ($this->error===null)
		{
			$this->mode=$this->image_location()->value('location_type');
		}
		return $result;
	}
		
	public function pgtitle()
	{
		$target=$this->template('location_type');
		return 'Редактировать '.$target.',<a href="gallery.php">Галерея</a>,'.parent::pgtitle();
	}
}

class Page_file_image_locations extends Page_file_view
{
	public
		$db_key='image_locations',
		$input_model=
		[
			'file'=>
			[
				'type'=>'entity',
				'id_group'=>'Image' // FIXME: это сработает даже если файл не является картинкой, потому что пока динамического определения типа нет.
			]
		];
		
	public function pgtitle()
	{	
		return 'Пункты и фрагменты,Изображение,<a href="gallery.php">Галерея</a>,'.parent::pgtitle();
	}
	
	// STUB
	public function rightful()
	{
		return Module_AdoptsGame::instance()->admin();
	}
	
	public function create_content()
	{
		$this->register_requirement('css', Router()->module_url('File', 'image_location.css'));
		$this->register_requirement('js', Router()->module_url('File', 'image_location.js'));
		return parent::create_content();
	}
}
?>