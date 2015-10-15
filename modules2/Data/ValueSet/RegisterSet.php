<?

namespace Pokeliga\Data;

/*
У данного есть содержимое и могут быть регистры. регистры - данные, относящиеся к содержимому и целиком зависимые от него. например, если данное хранит сущность, то регистрами могут быть её айди, группа и уникальные поля. теоретически, регистрами могут быть и любые поля, но это непрактично: лучше получать их из самой сущности. уникальные поля же могут использоваться для однозначного определения содержимого.

Задача регистров: убрать необходимость в дополнительно создаваемых объектах вроде 'owner_entity' типа ValueType_entity к полю 'owner' типа ValueType_id; упростить сохранение ссылок на сущности не по айди, а по другим уникальным полям; упростить работу с данными с комплексными значениями, как ValueType_linkset (не писать всё время value('missions')->values).

Регистры выполняют следующие функции:
- Быстрый доступ к важным параметрам, касающимся содержимого данного (айди сущности, длина списка...)
- Перезапись или корректировка содержимого через перезапись регистра. В том числе - при сохранении в БД и получении из неё.

По смыслу регистры бывают слелующие:
- Определяющие: данных из одного этого регистра достаточно, чтобы определить содержимое. Пример: год (в таймстемпе). обратное не обязательно верно: если у даты сохраняется только поле года, то часть информации (месяц, день...) может потеряться. но если мы так задали сохранение, то нам так и надо.
- Корректирующие: данных из этого регистра в сочетании с определёнными другими достаточно, чтобы определить содержимое. Самостоятельно он может только корректировать содержимое. Пример: айди сущности (нужна также группа).
- Для чтения: не позволяет уточнять содержимое.

Также, в зависимости от настроек модели, регистр может быть постоянным (в особенности группа сущности).

Когда меняется содержимое, могут поменяться и регистры. Регистры получаются лениво: если содержимое изменилось, то ставшие неизвестными регистры заполняются только по запросу. Это может потребовать выполнения задачи. поэтому регистры не следует использовать как содержимое ValueType_reference!

Состав регистров может меняться. Например, если внутрь попала сущность с другими уникальными полями, то становятся доступны другие регистры, соответствующие таковым полям.

Состав регистров у данных и их модели:

- ValueType_entity: id, id_group, уникальные поля.
	[
		Corr 'id'=>			'extract',
		Corr 'id_group'=>	'extract' или 'const' (в зависимости от модели данного),
		Corr <код_поля>=>	'extract_field'
	]
- ValueType_linkset: array, links, count.
	[
		RO 'array'=>		'extract', // Linkset->values
		RO 'count'=>		'generate' // нужно ли? как обращение данное.счёт как регистр будет отличаться от данное.счёт как счёт у содержимого? или для сохранения?
		RO 'links'=>		'generate' // LinkSet с сущностями GenericLink или иными, использовавшимися для нахождения свящанных сущностей.
	]
- ValueType_timestamp: day, year, month, hour, minutes, seconds.
	[
		Corr 'day'=>'extract',
		...
	]
	
*/

// применяется к ValueType. ValueType может быть ValueHost'ом и помимо своих регистров - например, давать доступ к под-данным, не являющимся регистрами.
interface Value_has_registers extends ValueHost, Pathway
{
	const
		REGSET_TRACK='_reg';
		
	// список всех регистров.
	public function list_regs($now=true);
	
	// массив со значениями всех регистров.
	public function get_regs($now=true);
	
	// установка регистра (только корректирующие)
	public function set_reg($register, $content, $source=Value::BY_OPERATION);
	
	// установка содержимого целиком по набору регистров.
	public function set_by_regs($data, $source=Value::BY_OPERATION);
	
	// установка содержимого по одному регистру.
	public function set_by_reg($register, $content, $source=Value::BY_OPERATION);
	
	// значение регистра, получить сейчас.
	public function value_reg($register);
	
	// запрос значения регистра.
	public function request_reg($register);
}

trait Value_registers
{
	protected
		// $reg_model=null,
		$RegSet=null;
	
	public function RegSet()
	{
		if ($this->RegSet===null) $this->RegSet=RegisterSet::for_value($this);
		return $this->RegSet;
	}
	
	public function set_reg($register, $content, $source=Value::BY_OPERATION)
	{
		if (!$this->has_state(Value::STATE_FILLED)) die('Unimplemented yet: SETTING REGISTER TO UNFILLED');
		$result=$this->compose_change_by_reg($register, $content);
		if ($result instanceof \Report_impossible) $this->set_failed($result, $source);
		else $this->set($result, $source);
	}
	
	public function set_by_regs($data, $source=Value::BY_OPERATION)
	{
		$result=$this->compose_from_regs($data);
		if ($result instanceof \Report_impossible) $this->set_failed($result, $source);
		else $this->set($result, $source);
	}
	
	public function set_by_reg($register, $content, $source=Value::BY_OPERATION)
	{
		$this->set_by_regs([$register=>$content], $source);
	}
	
	public function value_reg($register)
	{
		return $this->RegSet()->value($register);
	}
	
	public function request_reg($register)
	{
		return $this->RegSet()->request($register);
	}
	
	public function list_regs($now=true)
	{
		$model=$this->RegSet()->get_complete_model($now);
		if ($model instanceof \Report) return $model;
		return array_keys($model);
	}
	
	public function get_regs($now=true)
	{
		return $this->RegSet()->to_array(null, $now);
	}
	
	public function content_changed($source)
	{
		$this->reset_regs();
		parent::content_changed($source);
	}
	
	public function reset_regs()
	{
		// судя по всему, быстрее обновлять все объекты, раздавая им команду на сброс, чем забывать кусок структуры для его последующего пересоздания и полагаться на сборщик мусора.
		if (!empty($this->RegSet)) $this->RegSet->reset();
	}
	
	public function compose_change_by_reg($register, $content)
	{
		return $this->compose_change_by_test_RegSet($register, $content);
	}
	
	protected function compose_change_by_test_RegSet($register, $content, $reg_keys=null)
	{
		$test_RegSet=clone $this->RegSet();
		$test_RegSet->set_value($register, $content);
		if ( ($report=$test_RegSet->request($register)) instanceof \Report_impossible) return $report;
		return $this->compose_from_regs($test_RegSet->to_array($reg_keys));
	}
	
	public function compose_from_regs($data)
	{
		return $this->sign_report(new \Report_impossible('read_only_regs'));
	}
	
	// реализация ValueHost
	public function value($code)
	{
		return $this->value_reg($code);
	}
	
	public function request($code)
	{
		return $this->request_reg($code);
	}
	
	// реализация Pathway
	public function follow_track($code, $line=[])
	{
		$result=$this->follow_reg_track($code, $line);
		if (empty($result)) return new \Report_unknown_track($code, $this);
		return $result;
	}
	
	public function follow_reg_track($code, $line=[])
	{
		if ($code===static::REGSET_TRACK) return $this->RegSet();
		if (array_key_exists($code, $this->reg_model)) return $this->RegSet()->produce_value($code);
	}
}

class RegisterSet extends ValueSet
{
	public static function for_value($value_type, $model=null)
	{
		// WIP: динамические регистры (с запросом по необходимости у старшего значения) пока не поддерживаются.
		if ($model===null) $model=$value_type->reg_model;
		$regset=static::from_model($model);
		$regset->master=$value_type->value;
		return $regset;
	}
	
	public function fill_value($value)
	{
		$master_content=$this->master->request();
		if ($master_content instanceof \Report_impossible)
		{
			$value->set_failed($master_content);
			return;
		}
		elseif ($master_content instanceof \Report_tasks)
		{
			// FIXME: это единственное применение Filler_delay. если удастся обойтись без него, класс следует удалить.
			$filler=Filler_delay::with_call( new \Pokeliga\Entlink\Call([$this, 'fill_value'], $value), $master_content->force_again());
			$filler->master_fill();
			return;
		}
		
		$result=null;
		if ($value->in_value_model('extract_call'))
		{
			$call_data=$value->value_model_now('extract_call');
			if (is_array($call_data))
			{
				$method=array_shift($call_data);
				$args=$call_data;
			}
			else
			{
				$method=$call_data;
				$args=[];
			}
			$callback=[$this->master, $method];
			$result=$callback(...$args);
		}
		else die('NO REGISTER GENERATOR');

		if ($result instanceof \Report_impossible)
		{
			$value->set_failed($result);
			return;
		}
		elseif (\is_mediator($result)) throw new \Pokeliga\Entlink\UnknownMediatorException();
		else $value->set($result, $this->master->last_source);
	}
}
?>