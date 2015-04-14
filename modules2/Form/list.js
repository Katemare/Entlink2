/*

#######################################################
### Ёто набор функционала дл€ множественного выбора ###
### из списка и заполнени€ открытого списка полей.  ###
#######################################################

*/


var $lists=[];

/*
###################################################
###              abstract ListInput             ###
### —писок с открытым набором однородных полей. ###
###################################################
*/

function ListInput($list_id, $field_prefix, $max)
{
	$lists[$list_id]=this;
	
	this.list_id=$list_id;
	this.field_prefix=$field_prefix;
	if ($max===true)
	{
		this.fixed=true;
		this.max=null;
	}
	else
	{
		this.fixed=false;
		this.max=$max;
	}

	this.form=findForm(this.subfields_container());	
	this.count_object=document.getElementById('list_subfields_count'+this.list_id);
	this.subfields=[];
	this.populate_subfields();
	if (this.fixed) this.max=this.subfields.length;
}

has_custom_events(ListInput);

ListInput.prototype.subfields_container=function()
{
	var $container=document.getElementById('subfields_container'+this.list_id);
	if ($container) return $container;
	return this.fallback_subfields_container();
}
ListInput.prototype.add_subfield=function() { alert('inherit_me 1'); }
ListInput.prototype.fallback_subfields_container=function() { alert('inherit_me 2'); }
ListInput.prototype.populate_subfields=function() { alert('inherit_me 3'); }

/*
###################################################
###                  List_of_fields             ###
###    ѕол€ - сложные вводные конструкции.      ###
###################################################
*/

function setup_list_of_fields($list_id, $field_prefix, $max, $ord_placeholder, $empty_template)
{
	new List_of_fields($list_id, $field_prefix, $max, $ord_placeholder, $empty_template);
}

function setup_fixed_list_of_fields($list_id, $field_prefix, $ord_placeholder, $empty_template)
{
	new List_of_fields($list_id, $field_prefix, true, $ord_placeholder, $empty_template);
}

function List_of_fields($list_id, $field_prefix, $max, $ord_placeholder, $empty_template)
{
	this.empty_template=$empty_template;
	this.ord_placeholder=$ord_placeholder;
	ListInput.call(this, $list_id, $field_prefix, $max);
	
	if (!this.fixed)
	{
		this.plus=document.getElementById('list_subfields_plus'+this.list_id);
		if (!this.plus) this.fixed=true;
		else this.plus.addEventListener('click', this.add_subfield.bind(this));
	}
	if ( (!this.invisible) && (!this.fixed) ) this.attach_all_buttons();
}

List_of_fields.prototype = Object.create(ListInput.prototype);
List_of_fields.prototype.constructor = List_of_fields;

List_of_fields.prototype.populate_subfields=function()
{
	var $count=parseInt(this.count_object.value), $x, $subfield;
	for ($x=0; $x<$count; $x++)
	{
		$subfield=this.find_subfield($x);
		if (!$subfield) break;
		this.subfields[$x]=$subfield;
	}
	
	this.count_object.value=this.subfields.length;
}

List_of_fields.prototype.find_subfield=function($ord)
{
	return document.getElementById('list'+this.list_id+'_'+$ord);
}

List_of_fields.prototype.attach_all_buttons=function()
{
	var $x;
	for ($x in this.subfields)
	{
		this.attach_buttons($x);
	}
}

List_of_fields.prototype.attach_buttons=function($x)
{
	this.attach_up_button($x);
	this.attach_down_button($x);
	if (!this.fixed) this.attach_remover($x);
}

List_of_fields.prototype.attach_up_button=function($index)
{
	$index=parseInt($index);
	var $up_button=this.create_up_button($index);
	this.subfields[$index].insertBefore($up_button, this.subfields[$index].firstChild);
	this.subfields[$index].up_button=$up_button;
	if ($index==0) $up_button.disabled=true;
}

List_of_fields.prototype.attach_down_button=function($index)
{
	$index=parseInt($index);
	var $down_button=this.create_down_button($index);
	this.subfields[$index].insertBefore($down_button, this.subfields[$index].firstChild);
	this.subfields[$index].down_button=$down_button;
	if ($index==this.subfields.length-1) $down_button.disabled=true;
}

List_of_fields.prototype.attach_remover=function($index)
{
	if (this.fixed) return;
	$index=parseInt($index);
	var $remover=this.create_remover($index);
	this.subfields[$index].insertBefore($remover, this.subfields[$index].firstChild);
	this.subfields[$index].remover=$remover;
}

List_of_fields.prototype.create_remover=function($index)
{
	var $remover=document.createElement('button');
	$remover.className='close';
	$remover.appendChild(document.createTextNode('\u00D7'));
	$remover.index=$index;
	// $remover.id='test'+$index;
	$remover.god=this;
	$remover.type='button';
	$remover.onclick=function($e)
		{
			this.god.close_subfield(this.index);
			pauseEvent($e);
		};
	return $remover;
}

List_of_fields.prototype.create_up_button=function($index)
{
	var $up=document.createElement('button');
	$up.className='up_button';
	$up.appendChild(document.createTextNode('\u25B2'));
	$up.index=$index;
	// $up.id='test'+$index;
	$up.god=this;
	$up.type='button';
	$up.onclick=function($e)
		{
			this.god.up_subfield(this.index);
			pauseEvent($e);
		};
	return $up;
}

List_of_fields.prototype.create_down_button=function($index)
{
	var $down=document.createElement('button');
	$down.className='down_button';
	$down.appendChild(document.createTextNode('\u25BC'));
	$down.index=$index;
	// $down.id='test'+$index;
	$down.god=this;
	$down.type='button';
	$down.onclick=function($e)
		{
			this.god.down_subfield(this.index);
			pauseEvent($e);
		};
	return $down;
}

List_of_fields.prototype.close_subfield=function($index)
{	
	var $x;
	if ($index==this.subfields.length-1) this.remove_subfield($index);
	else
	{
		this.remove_subfield($index); // только убирает из документа. замену в массиве осуществит следующа€ операци€.
		for ($x=$index+1; $x<this.subfields.length; $x++)
		{
			this.renumerate($x, $x-1);
		}
	}
	this.subfields.length--;
	if (this.subfields.length>0) this.subfields[this.subfields.length-1].down_button.disabled=true;
	this.count_object.value--;
	this.plus.disabled=false;
}

List_of_fields.prototype.swap_subfields=function($index1, $index2)
{
	if ($index1==$index2) return;
	if ($index2<$index1)
	{
		this.swap_subfields($index2, $index1);
		return;
	}
	var $length=this.subfields.length, $temp_index=$length;
	this.renumerate($index1, $temp_index); // также увеличивает длину на 1.
	this.renumerate($index2, $index1);
	this.renumerate($temp_index, $index2);
	this.subfields.length=$length; // стирает лишний элемет.
	
	this.replace_subfield($index1);
	this.replace_subfield($index2);
	this.subfields[$index2].down_button.disabled= $index2==$length-1;
}

List_of_fields.prototype.up_subfield=function($index)
{	
	var $x;
	if ($index==0) return;
	this.swap_subfields($index, $index-1);
}

List_of_fields.prototype.down_subfield=function($index)
{	
	var $x;
	if ($index==this.subfields.length-1) return;
	this.swap_subfields($index, $index+1);
}

List_of_fields.prototype.renumerate=function($index, $new_index)
{
	var $subfield=this.subfields[$index];
	var $to_rename=['input', 'select', 'textarea', 'button'], $tag, $elements, $x, $y;
	var $ex=new RegExp('^'+this.field_prefix+$index);
	
	for ($x in $to_rename)
	{
		$tag=$to_rename[$x];
		$elements=$subfield.getElementsByTagName($tag);
		for ($y in $elements)
		{
			if (!$y.match(/^\d+$/)) break;
			$elements[$y].name=$elements[$y].name.replace($ex, this.field_prefix+$new_index);
		}
	}
	$subfield.remover.index=$new_index;
	$subfield.up_button.index=$new_index;
	$subfield.down_button.index=$new_index;
	$subfield.up_button.disabled=($new_index==0);
	$subfield.down_button.disabled=($new_index>=this.subfields.length-1);
	this.subfields[$new_index]=$subfield;
}

List_of_fields.prototype.remove_subfield=function($index)
{
	if (this.subfields[$index].parentNode) this.subfields[$index].parentNode.removeChild(this.subfields[$index]);
}

// удаляет поле (если требуется) и возвращает на нужное место.
List_of_fields.prototype.replace_subfield=function($index)
{
	this.remove_subfield($index);
	if ($index==0) this.subfields_container().insertBefore(this.subfields[$index], this.subfields_container().firstChild);
	else if ($index==this.subfields.length-1) this.subfields_container().appendChild(this.subfields[$index]);
	else this.subfields_container().insertBefore(this.subfields[$index], this.subfields[$index+1]);
}

List_of_fields.prototype.add_subfield=function()
{
	var $new_id=this.subfields.length, $new_list_id=$lists.length;
	var $template=this.empty_template.html.replace(new RegExp(escapeRegExp(this.ord_placeholder), 'g'), $new_id);
	$template=$template.replace(new RegExp('%list_id%', 'g'), $new_list_id);
	
	// будут оставатьс€ лишние элементы, ну и чЄрт с ними: век формы недолог.
	var $div=document.createElement('div');
	$div.innerHTML=$template;
	this.subfields[$new_id]=$div.firstChild;
	this.subfields_container().appendChild($div.firstChild);
	this.count_object.value++;
	
	if (this.empty_template.eval)
	{
		var $eval=this.empty_template.eval.replace(new RegExp(escapeRegExp(this.ord_placeholder), 'g'), $new_id);
		$eval=$eval.replace(new RegExp('%list_id%', 'g'), $new_list_id);
		eval($eval);
	}
	
	this.attach_buttons($new_id);
	if ($new_id>0) this.subfields[$new_id-1].down_button.disabled=false;
	if (this.subfields.length==this.max) this.plus.disabled=true;
	
	run_init_functions();
}

List_of_fields.prototype.fallback_subfields_container=function()
{
	return this.plus.parentNode;
}

/*
###################################################
###            abstract EnumInput               ###
###    ѕол€ - скрытые айди конечного списка.    ###
###################################################
*/

function EnumInput($list_id, $field_prefix, $max)
{
	this.selected=[];		// список выбранных значений.
	ListInput.call(this, $list_id, $field_prefix, $max);
}

EnumInput.prototype = Object.create(ListInput.prototype);
EnumInput.prototype.constructor = EnumInput;

EnumInput.prototype.populate_subfields=function()
{
	var $count=parseInt(this.count_object.value), $x, $subfield;
	for ($x=0; $x<$count; $x++)
	{
		$subfield=this.form[this.field_prefix+$x];
		if (!$subfield) break;
		this.subfields[$x]=this.form[this.field_prefix+$x];
		this.selected[$x]=this.subfields[$x].value;
	}
	this.count_object.value=this.subfields.length;
}

EnumInput.prototype.add_subfield = function()
{
	var $index=this.subfields.length;
	this.subfields[$index]=document.createElement('input');
	this.subfields[$index].type='hidden';
	this.subfields[$index].name=this.field_prefix+$index;
	this.subfields_container().appendChild(this.subfields[$index]);
}

EnumInput.prototype.set_subfield = function($index, $value)
{
	this.subfields[$index].value=$value;
}

// уничтожает пол€, начина€ с указанного индекса.
EnumInput.prototype.remove_subfields_from = function($index)
{
	for ($x=$index; $x<this.subfields.length; $x++)
	{
		this.subfields[$x].parentNode.removeChild(this.subfields[$x]);
	}
	this.subfields.length=$index;
}

EnumInput.prototype.unselect = function($index)
{
	if ($index.nodeType) $index=this.subfields.indexOf($index);
	var $new_selected=this.selected.concat(); // клонирование массива.
	$new_selected.splice($index, 1);
	this.update($new_selected);
}

// обновл€ет состав полей и плашек в соответствии с поданным списком. не оказывает вли€ни€ на элементы, которые использовались дл€ выбора.
EnumInput.prototype.update=function($new_selected)
{
	var $values_involved=$new_selected.concat(this.selected), $x;
	this.selected=$new_selected;
	
	for ($x in this.subfields)
	{
		if (typeof this.selected[$x] == 'undefined') // отсюда начинаютс€ лишние пол€, если значений выбрано меньше, чем раньше.
		{
			this.remove_subfields_from($x);
			break;
		}
		this.set_subfield($x, this.selected[$x]);
	}
	
	// дополнительные пол€, если выбрано больше, чем раньше.
	for ($x=this.subfields.length; $x<this.selected.length; $x++)
	{
		this.add_subfield();
		this.set_subfield($x, this.selected[$x]);
	}
	
	this.count_object.value=this.selected.length;
	this.fire_custom_event(new SimpleEvent('update', { list: this, values_involved: $values_involved }));
}

EnumInput.prototype.title_by_value=function($value) { return $value; }

/*
####################################################
###                 SelectInput                  ###
### ¬ыбираемые айди берутс€ из элемента <select> ###
####################################################
*/

function SelectInput($list_id, $field_prefix, $max)
{
	var $x;
	
	this.select=document.getElementById('select_input_'+$list_id);
	this.select.god=this;
	
	this.refresh_options();
	
	EnumInput.call(this, $list_id, $field_prefix, $max);
	
	this.multiple=this.select.getAttribute('multiple')!==null;
	
	if (this.multiple)
	{
		for ($x in this.selected)
		{
			this.options_by_value[this.selected[$x]].selected=true;
		}
	}
	else
	{
		this.dummy_option=new Option('¬ыбрать...', '');
		this.select.insertBefore(this.dummy_option, this.select.options[0]);
		this.select.selectedIndex=0;
	}
	
	this.select.onchange=this.select_changed.bind(this);
	this.select.onclick=this.select_clicked.bind(this);
}

SelectInput.prototype = Object.create(EnumInput.prototype);
SelectInput.prototype.constructor = SelectInput;

SelectInput.prototype.refresh_options=function()
{
	this.options_by_value={};
	for ($x in this.select.options)
	{
		this.options_by_value[this.select.options[$x].value]=this.select.options[$x];
	}
}

SelectInput.prototype.select_changed=function()
{
	var $new_selected;
	if (this.multiple) $new_selected=this.compose_multiselected();
	else $new_selected=this.compose_selected();
	if (typeof $new_selected=='undefined') return;
	this.update($new_selected);
}

SelectInput.prototype.reclick_single_option=false;
SelectInput.prototype.select_clicked=function()
{
	if ((this.reclick_single_option) && (this.select.options.length==1))
	{
		this.selectedIndex=-1;
		this.select_changed();
	}
}

SelectInput.prototype.compose_multiselected=function()
{
	var $chosen=[], $x;
	for ($x in this.select.options)
	{
		if (this.select.options[$x].selected) $chosen[$chosen.length]=this.select.options[$x].value;
	}
	
	// дл€ сохранени€ пор€дка выбора и сн€ти€ выделени€ лишних элементов.
	var $ordered_chosen=[];
	for ($x in this.selected)
	{
		if ( ($index=$chosen.indexOf(this.selected[$x]))!==-1) $ordered_chosen[$ordered_chosen.length]=this.selected[$x];
	}
	for ($x=0; $x<$chosen.length; $x++)
	{
		if ( ($index=$ordered_chosen.indexOf($chosen[$x]))===-1) $ordered_chosen[$ordered_chosen.length]=$chosen[$x];
	}
	
	if ($ordered_chosen.length>this.max)
	{
		var $extra=$ordered_chosen.length-this.max, $index;
		for ($x=0; $x<$extra; $x++)
		{
			this.options_by_value[$ordered_chosen[$x]].selected=false;
		}
		$ordered_chosen.splice(0, $extra);
	}
	
	return $ordered_chosen;
}

SelectInput.prototype.compose_selected=function()
{
	var $new_selected=this.select.selectedIndex;
	if ($new_selected==-1) return;
	if (this.select.options[$new_selected]===this.dummy_option) return;
	$new_selected=this.select.options[$new_selected].value;
	
	var $selected=this.selected.concat(); // клонирование массива.
	if ($selected.indexOf($new_selected)!=-1)
	{
		this.select.selectedIndex=0;
		return;
	}
	
	if ($selected.length==this.max) $selected.shift();
	$selected[$selected.length]=$new_selected;
	this.select.selectedIndex=0;
	return $selected;
}

SelectInput.prototype.unselect = function($index)
{
	if (this.multiple)
	{
		if ($index.nodeType) $index=this.subfields.indexOf($index);
		var $value=this.selected[$index];
		this.options_by_value[$value].selected=false;
	}
	EnumInput.prototype.unselect.call(this, $index);
}

SelectInput.prototype.title_by_value=function($value)
{
	if ( (!this.options_by_value[$value]) && ($value==='') ) return '';
	return this.options_by_value[$value].text;
}

SelectInput.prototype.fallback_subfields_container=function()
{
	return this.select.parentNode;
}

/*
####################################################
###              PolySelectInput                 ###
###   ¬ыбираемые айди из комбинации <select>'ов  ###
####################################################
*/
function PolySelectInput($list_id, $field_prefix, $max, $number)
{
	var $x, $y;
	this.number=$number;
	
	this.selects=[];
	this.options_by_value=[];
	for ($x=0; $x<$number; $x++)
	{
		this.selects[$x]=document.getElementById('select_input_'+$list_id+'_'+$x);
		this.selects[$x].god=this;
		
		this.options_by_value[$x]={};
		for ($y in this.selects[$x].options)
		{
			this.options_by_value[$x][this.selects[$x].options[$y].value]=this.selects[$x].options[$y];
		}
	}
	
	EnumInput.call(this, $list_id, $field_prefix, $max);
	
	this.combine_button=document.getElementById('combine_button_'+$list_id);
	this.combine_button.god=this;
	this.combine_button.onclick=function() { this.god.combine_clicked(); }
}

PolySelectInput.prototype = Object.create(EnumInput.prototype);
PolySelectInput.prototype.constructor = PolySelectInput;

PolySelectInput.prototype.combine_clicked = function()
{
	var $x, $value=[], $selected=this.selected.concat(); // клонирование массива.
	
	for ($x=0; $x<this.number; $x++)
	{
		$value[$x]=this.selects[$x].options[this.selects[$x].selectedIndex].value;
	}
	$value=$value.join('&');
	
	if ($selected.indexOf($value)!==-1) return;
	
	if ($selected.length==this.max) $selected.shift();
	$selected[$selected.length]=$value;

	this.update($selected);
}

PolySelectInput.prototype.fallback_subfields_container=function()
{
	return this.selects[0].parentNode;
}

PolySelectInput.prototype.title_by_value=function($value)
{
	var $x, $result=[];
	$value=$value.split('&');
	for ($x=0; $x<this.number; $x++)
	{
		$result[$x]=this.options_by_value[$x][$value[$x]].text;
	}
	return $result.join('/');
}

/*
####################################################
###                 SlugManager                  ###
###        «аведует плашками при EnumInput       ###
####################################################
*/

function SlugManager($list)
{
	this.list=$list;
	this.slugs_by_value={};	// плашки
	this.populate_slugs();
	this.list.add_custom_listener('update', this.list_updated.bind(this));
}

SlugManager.prototype.populate_slugs=function()
{
	var $x;
	for ($x=0; $x<this.list.selected.length; $x++)
	{
		$slug=this.attach_slug($x);
		$slug.appeared();
	}
	this.imprint_slugs(this.list.selected);
}

SlugManager.prototype.attach_slug=function($index)
{
	var $slug=this.slug_by_value(this.list.selected[$index]);
	$slug.attach($index);
	return $slug;
}

SlugManager.prototype.slug_by_value = function($value)
{
	if (!this.slugs_by_value.hasOwnProperty($value)) this.slugs_by_value[$value]=this.create_slug($value);
	return this.slugs_by_value[$value];
}

SlugManager.prototype.create_slug = function($value)
{
	$slug=new Slug($value, this);
	$slug.imprint();
	return $slug;
}

SlugManager.prototype.slug_title_by_value=function($value)
{
	return this.list.title_by_value($value);
}

// здесь нет защиты от повтор€ющихс€ значений, но цена операции очень невелика, поскольку все подразумеваемые плашки уже должны быть созданы.
SlugManager.prototype.imprint_slugs=function($values)
{
	var $x;
	for ($x in $values)
	{
		this.slug_by_value($values[$x]).imprint();
	}
}

SlugManager.prototype.check_slugs=function($values)
{
	var $x;
	for ($x in $values)
	{
		this.slug_by_value($values[$x]).check();
	}
}

SlugManager.prototype.list_updated=function($event)
{
	var $x;
	for ($x=0; $x<this.list.selected.length; $x++)
	{
		this.attach_slug($x);
	}
	for ($x in $event.details.values_involved)
	{
		if (this.list.selected.indexOf($event.details.values_involved[$x])!=-1) continue;
		this.slug_by_value($event.details.values_involved[$x]).detach();
	}
	this.check_slugs($event.details.values_involved);
}

/*
####################################################
###                  SlugSelect                  ###
###   ¬ыбор из <select>, отображение плашками.   ###
####################################################
*/
function setup_slugselect($list_id, $field_prefix, $max)
{
	if (!is_window_loaded()) register_load_function(setup_slugselect.bind.apply(setup_slugselect, bind_arguments(arguments)));
	else new SlugSelect($list_id, $field_prefix, $max);
}

function SlugSelect($list_id, $field_prefix, $max)
{
	SelectInput.call(this, $list_id, $field_prefix, $max);
	this.slugmanager=this.create_slugmanager();
}

SlugSelect.prototype = Object.create(SelectInput.prototype);
SlugSelect.prototype.constructor = SlugSelect;

SlugSelect.prototype.reclick_single_option=true;
SlugSelect.prototype.create_slugmanager=function()
{
	return new SlugManager(this);
}

/*
####################################################
###                 PolySlugSelect               ###
### ¬ыбор из нескольких <select>, + плашки.      ###
####################################################
*/
function setup_slugselect_poly($list_id, $field_prefix, $max, $number)
{
	if (!is_window_loaded()) register_load_function(setup_slugselect_poly.bind.apply(setup_slugselect_poly, bind_arguments(arguments)));
	else new SlugPolySelect($list_id, $field_prefix, $max, $number);
}

function SlugPolySelect($list_id, $field_prefix, $max, $number)
{
	PolySelectInput.call(this, $list_id, $field_prefix, $max, $number);
	this.slugmanager=this.create_slugmanager();
}

SlugPolySelect.prototype = Object.create(PolySelectInput.prototype);
SlugPolySelect.prototype.constructor = SlugPolySelect;

SlugPolySelect.prototype.create_slugmanager=function()
{
	return new SlugManager(this);
}

/*
####################################################
###                     Slug                     ###
###             —обственно объект плашки.        ###
####################################################
*/
function Slug($value, $manager)
{	
	this.manager=$manager;
	this.list=this.manager.list;
	this.value=$value;
	this.title=this.list.title_by_value(this.value);
	
	this.element=document.createElement('div');
	this.element.appendChild(document.createTextNode(this.title));
	this.element.className='multiselect_slug';
	this.element.god=this;
	
	this.close=document.createElement('div');
	this.close.appendChild(document.createTextNode('\u00D7'));
	this.close.className='close';
	this.close.god=this;
	this.element.appendChild(this.close);
	
	this.close.onclick=function($e) { this.god.unselect(); pauseEvent($e); }
	
	this.input=null;
}

has_custom_events(Slug);

Slug.prototype.unselect=function()
{
	if (!this.input) return;
	this.list.unselect(this.input);
}

Slug.prototype.append=function()
{
	insertAfter(this.input, this.element);
}

Slug.prototype.attach=function($new_index)
{
	if (this.list.subfields[$new_index]==this.input) return;
	
	if (this.input) this.input.slug=null;
	this.detach();
	
	this.input=this.list.subfields[$new_index];
	if (this.input.slug) this.input.slug.detach();
	this.input.slug=this;
	this.append();
}

Slug.prototype.detach=function($new_index)
{
	if (this.input) this.input.slug=null;
	this.input=null;
	if (this.element.parentNode) this.element.parentNode.removeChild(this.element);
}

Slug.prototype.appeared=function()
{
	this.fire_custom_event(new SimpleEvent('appear', { slug: this }));
}
Slug.prototype.disappeared=function()
{
	this.fire_custom_event(new SimpleEvent('disappear', { slug: this }));
}
Slug.prototype.active=function()
{
	if (this.input) return true;
	return false;
}
Slug.prototype.imprint=function()
{
	this.imprinted=this.active();
}
Slug.prototype.check=function()
{
	if (this.imprinted===this.active()) return;
	if (this.active()) this.appeared();
	else this.disappeared();
	this.imprint();
}