<?
namespace Pokeliga\Data;

// эти типы пытаются удалить из названий файлов недопустимые символы. правильно ли это? не должны ли они возвращать отчёт об ошибке, если есть хотя бы один плохой символ?
class ValueType_file_address_component extends ValueType_string
{
	const
		MIN=1,
		GOOD_CHARACTERS='a-zA-Z\d_\.'; // ОС, как правило, позволяет куда большее разнообразие папок, но хочется предотвратить появление папок с проблемными именами.
}

class ValueType_file_address_fragment extends ValueType_file_address_component
{
	const
		GOOD_CHARACTERS=ValueType_file_address_component::GOOD_CHARACTERS.'\/';
}

class ValueType_file_extension extends ValueType_file_address_component // для соблюдения смыслвого instanceof в случае необходимости, а так никаких особенностей родительского класса не используется.
{
	const
		MIN=0,
		MAX=16,
		GOOD_CHARACTERS='a-zA-Z\d_';
}

class_alias(__NAMESPACE__.'\ValueType_file_address_component', __NAMESPACE__.'\ValueType_dirname');

class ValueType_filename extends ValueType_file_address_component implements Value_has_registers
{
	use Value_registers;
	
	public
		$reg_model=
		[
			'base_name'=>
			[
				'type'=>'file_address_component',
				'extract_call'=>'extract_reg_base_name'
			],
			'ext'=>
			[
				'type'=>'file_extension',
				'extract_call'=>'extract_reg_extension'
			]
		];
	
	public function extract_reg_extension()
	{
		preg_match('/\.([^\.]+)$/', $this->content, $m);
		return (string)($m[1]);
	}
	
	public function extract_reg_base_name()
	{
		preg_match('/^(.+?)(\.[^\.]*)?$/', $this->content, $m);
		return (string)($m[1]);
	}	
}

class ValueType_file_address extends ValueType_array
{
	const
		IMPLODE_FOR_HUMAN_DIVIDER='/\//',
		IMPLODE_FOR_HUMAN_JOINER='/',
		IMPLODE_JOINER='/',
		DEFAULT_SCALAR_REGISTER='relative',
		EXISTS_BY_DEFAULT=true;
		
	public
		$public_regs=true,
		$reg_model=
		[
			'composed'=>
			[
				'type'=>'string',
				'extract_call'=>'extract_reg_composed'
			],
			'relative'=>
			[
				'type'=>'file_address_fragment',
				'extract_call'=>'extract_reg_imploded'
			],
			'path'=>
			[
				'type'=>'file_address_fragment',
				'extract_call'=>'extract_reg_path'
			],
			'base_name'=>
			[
				'type'=>'file_address_component',
				'extract_call'=>['extract_reg_from_filename', 'base_name']
			],
			'ext'=>
			[
				'type'=>'file_extension',
				'extract_call'=>['extract_reg_from_filename', 'ext']
			]
		],
		$generic_element_model='dirname',
		$elements_model=
		[
			'filename'=>
			[
				'type'=>'filename'
			]
		];
	
	public function extract_reg_composed()
	{
		return Engine()->docroot.'/'.$this->value_reg('relative');
	}
	
	public function extract_reg_path()
	{
		$content=$this->content;
		unset($content['filename']);
		return implode(static::IMPLODE_JOINER, $content);
	}
	
	public function extract_reg_from_filename($subreg)
	{
		return $this->subvalue('filename')->value_reg($subreg);
	}
	
	public function compose_change_by_reg($register, $content)
	{
		if ($register==='ext' or $register==='base_name' or $register==='path') return $this->compose_change_by_test_RegSet($register, $content, ['path', 'base_name', 'ext']);
		else return parent::compose_change_by_reg($register, $content);
	}	
	
	public function compose_from_regs($data)
	{
		if (array_key_exists('composed', $data)) return $data['composed'];
		if (array_key_exists('base_name', $data) and array_key_exists('ext', $data)) $data['name']=$data['base_name'].'.'.$data['ext'];
		if (array_key_exists('path', $data) and array_key_exists('name', $data)) return '/'.$data['path'].'/'.$data['name'];
		return parent::compose_from_regs($data);
	}
	
	public static function type_conversion($content)
	{
		if (is_string($content))
		{
			$content=str_replace('\\', static::IMPLODE_JOINER, $content);
			$content=explode(static::IMPLODE_JOINER, $content);
		}
		$content=(array)$content;
		end($content);
		if (key($content)!=='filename') $content['filename']=array_pop($content); // переименовывает последний ключ в 'filename'.
		return $content;
	}
	
	public function list_validators()
	{
		if ($this->in_value_model('exists')) $exists=$this->value_model_now('exists');
		else $exists=static::EXISTS_BY_DEFAULT;
		if ($exists===true) return ['file_by_address_exists'];
		elseif ($exists===false) return ['file_by_address_doesnt_exist'];
	}
}

class Validator_file_by_address_exists extends Validator
{
	public function progress()
	{
		if (file_exists($this->value->value_reg('composed'))) $this->finish();
		else $this->impossible();
	}
}

class Validator_file_by_address_doesnt_exist extends Validator
{
	public function progress()
	{
		if (file_exists($this->value->value_reg('composed'))) $this->impossible();
		else $this->finish();
	}
}
?>