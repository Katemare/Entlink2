
/*

########################################################
### Это набор функционала для выпадающего списка для ###
### динамической подгрузки, поиска и экономии html.  ###
########################################################

*/

/*
###################################################
###                SelectManager                ###
###  Экономия html для повторяющихся списков.   ###
###################################################
*/

function setup_common_options()
{
	var $selects=document.getElementsByTagName('select');
	for (var $x in $selects)
	{
		if (!$x.match(/^\d+$/)) break;
		if (!$selects[$x].hasAttribute('options_group')) continue;
		new SelectManager($selects[$x]);
	}
}
register_load_function(setup_common_options, register_load_function.PRIORITY_CONSTRUCT_LAYOUT);

function SelectManager($select_id)
{
	if (typeof $select_id == 'object') this.select=$select_id;
	else
	{
		this.select_id=$select_id;
		this.select=document.getElementById(this.select_id);
	}
	
	this.populate_options();
	this.init_selection();
}

SelectManager.prototype.OPTIONS_GROUP_ATTRIBUTE='options_group';

SelectManager.prototype.populate_options=function()
{
	this.options_group=this.select.getAttribute(this.OPTIONS_GROUP_ATTRIBUTE);
	this.options_source=document.getElementById('options_for_'+this.options_group);
	if (!this.options_source) return;
	if (this.options_source.innerHTML=='') this.select.options[0]=new Option('Нет подходящих пунктов!', '');
	else this.select.innerHTML+=this.options_source.innerHTML;
	
	if ( (this.select.god) && (this.select.god instanceof SelectInput) ) this.select.god.refresh_options();
}

SelectManager.prototype.init_selection=function()
{
	var $value=this.select.getAttribute('selected_value'), $x, $done=false;
	for ($x in this.select.options)
	{
		if (this.select.options[$x].value==$value)
		{
			this.select.selectedIndex=$x;
			$done=true;
			break;
		}
	}
	if (!$done) this.unselect();
	return $done;
}

SelectManager.prototype.unselect=function()
{
	this.select.selectedIndex=-1;
}

SelectManager.prototype.empty_is_ok=function()
{
	return this.select.getAttribute('empty_is_ok') == true;
}

/*
###################################################
###                  SelectAJAX                 ###
###  Список с динамическим поиском вариантов.   ###
###################################################
*/

/*

<div>
<input type=text id="selectXXX_input" API_arguments="{{аргументы для поиска}}" />
<button id="selectXXX_button">{{стрелочка вниз}}</button>
<select id="selectXXX" name="..."><option value="...">{{выбрано по умолчанию}}</option></select>
</div>

Поле ввода и фальш-кнопка закрывают собой выпадающий список. При щелчке на фальш-кнопку список открывается. При изменении ввода список открывается, отправляется запрос поиска AJAX, показываются варианты. Если их больше некоторого предела, в конце добавляется "и ещё ХХХ вариантов, уточните поиск". Список закрывается при щелчке на него или потере фокуса элементами внутри данной группы. Если ввод совпадает с одним из найденных вариантов, он автоматически выбирается (если их несколько, выбирается первый). Если ввод не совпадает ни с одним из вариантов, выбора нет, а ввод подкрашивается красным.
*/

function setup_searchable_options()
{
	var $selects=document.getElementsByClassName('searchable_select'), $x;
	for ($x in $selects)
	{
		if (!$x.match(/^\d+$/)) break;
		if ($selects[$x].selectAJAX) continue;
		new SelectAJAX($selects[$x]);
	}
}
register_init_function(setup_searchable_options);

function SelectAJAX($element)
{
	this.element=$element;
	this.element.selectAJAX=this;
	for (var $x in this.element.children)
	{
		
		if ( (this.element.children[$x].nodeName==='DIV') && (this.element.children[$x].classList.contains('select_container')) )
		{
			this.select_container=this.element.children[$x]
			this.select=this.select_container.firstChild;
		}
		else if ( (this.element.children[$x].nodeName==='DIV') && (this.element.children[$x].classList.contains('loading')) )
		{
			this.searching_marker=this.element.children[$x];
		}
		else if (this.element.children[$x].nodeName==='INPUT') this.input=this.element.children[$x];
		else if (this.element.children[$x].nodeName==='BUTTON') this.button=this.element.children[$x];
	}
	
	SelectManager.call(this, this.select);

	this.expanded=false;
	this.request_interval=null;
	this.button.addEventListener('click', this.toggle.bind(this));
	this.select.addEventListener('change', this.selection_changed.bind(this));
	this.select.addEventListener('click', this.selection_clicked.bind(this));
	this.input.addEventListener('input', this.input_changed.bind(this));
	this.input.addEventListener('blur', this.input_blur.bind(this));
	this.input.addEventListener('focus', this.input_focus.bind(this));
	
	this.input.addEventListener('blur', this.element_blur.bind(this));
	this.select.addEventListener('blur', this.element_blur.bind(this));
	this.button.addEventListener('blur', this.element_blur.bind(this));
}

SelectAJAX.prototype = Object.create(SelectManager.prototype);
SelectAJAX.prototype.constructor = SelectAJAX;

SelectAJAX.prototype.OPTIONS_GROUP_ATTRIBUTE='API_arguments';
SelectAJAX.prototype.REQUEST_INTERVAL=1000; // поиск через секунду после последнего ввода.

SelectAJAX.prototype.init_selection=function()
{
	if (this.select.options.length>0)
	{
		var $selected=SelectManager.prototype.init_selection.call(this);
		if ($selected) this.input.value=this.select.options[this.select.selectedIndex].text;
	}
	else this.unselect();
}

SelectAJAX.prototype.unselect=function()
{
	this.input.value='';
	if (this.select.options.length>1) this.select.selectedIndex=-1;
	if (!this.empty_is_ok()) this.bad_selection();
}

SelectAJAX.prototype.bad_selection=function()
{
	this.element.classList.add('bad_selection');
}

SelectAJAX.prototype.good_selection=function()
{
	this.element.classList.remove('bad_selection');
}

SelectAJAX.prototype.toggle=function()
{
	if (this.expanded) this.collapse();
	else this.expand();
	return false;
}

SelectAJAX.prototype.expand=function()
{
	this.expanded=true;
	this.adjust();
}

SelectAJAX.prototype.collapse=function()
{
	if (!this.expanded) return;
	this.expanded=false;
	this.select.size=0;
	this.select.style.display='none';
}

SelectAJAX.prototype.adjust=function()
{
	if (!this.expanded) return;
	if (this.select.options.length==1) this.select_container.classList.add('single_option');
	else this.select_container.classList.remove('single_option');
	this.select.size=Math.min(this.select.options.length, 20);
	this.select.style.display='block';
}

SelectAJAX.prototype.selection_changed=function()
{
	var $selected=this.select.options[this.select.selectedIndex];
	if ($selected.value==="")
	{
		this.unselect();
		this.collapse();
		return;
	}
	this.input.value=this.select.options[this.select.selectedIndex].text;
	this.good_selection();
	this.collapse();
}

SelectAJAX.prototype.selection_clicked=function()
{
	if (this.select.options.length!=1) return;
	this.collapse();
	this.select.selectedIndex=0;
	this.selection_changed();
	this.input.focus();
}

SelectAJAX.prototype.input_changed=function()
{
	if (!this.empty_is_ok()) this.bad_selection();
	if (this.input.value=='')
	{
		this.unselect();
		return;
	}
	if (this.request_interval!==null) clearInterval(this.request_interval);
	if (this.request) { /* this.request.abort(); */ /* отключено, потому что один запрос может обслуживать несколько списков. */  this.request=null; }
	this.request_interval=setTimeout(this.request_search.bind(this), this.REQUEST_INTERVAL);
	
	this.searching();
}

SelectAJAX.prototype.input_blur=function()
{
	if (this.request_interval===null) return;
	clearInterval(this.request_interval);
	this.request_interval=null;
	this.request_search();
}

SelectAJAX.prototype.input_focus=function()
{
	if ( (this.select.options.length>1) || (this.element.classList.contains('bad_selection')) ) this.expand();
	this.input.select();
}

SelectAJAX.prototype.searching=function()
{
	this.select.options.length=0;
	this.collapse();
	this.searching_marker.classList.add('visible');
	this.bad_selection();
	this.adjust();
}

SelectAJAX.prototype.request_search=function()
{
	this.request_interval=null;
	if (this.input.value=='') return;
	var $search=this.input.value;
	if (!SelectAJAX.instances) SelectAJAX.instances={};
	var $instances = SelectAJAX.instances;
	var $request;
	
	if ( ($instances.hasOwnProperty(this.options_group)) && ($instances[this.options_group].hasOwnProperty($search)) )
	{
		$request=$instances[this.options_group][$search]; // может быть не только объектом Request, но и результатом работы такового.
	}
	else
	{
		$request=new Request(null, '../api/form.php', 'select_options', this.options_group, 'search='+encodeURIComponent($search));
		if (!$instances.hasOwnProperty(this.options_group)) $instances[this.options_group]={};
		if (!$instances[this.options_group].hasOwnProperty($search)) $instances[this.options_group][$search]=$request;
	}
	
	this.request=$request;
	var $call=this.parse_search_results.bind(this);
	if ($request.finished) $request.make_call($call);
	else
	{
		$request.add_call($call);
		$request.make();
	}
}


SelectAJAX.prototype.parse_search_results=function($result) // также принимает метод и код опций, но они не нужны.
{
	if (fcd($result, 'search')!=this.input.value) return;
	
	this.select.options.length=0;
	var $options=$result.getElementsByTagName('option'), $x;
	for ($x in $options)
	{
		if (!$x.match(/^\d+$/)) break;
		this.select.appendChild(new Option(fcd($options[$x], 'title'), fcd($options[$x], 'value')));
	}
	if (this.select.options.length>0)
	{
		this.unselect();
		this.expand();
	}
	else
	{
		this.input.value='Не найдено!';
	}
	this.searching_marker.classList.remove('visible');
	
	if ( (this.select.god) && (this.select.god instanceof SelectInput) ) this.select.god.refresh_options();
}

SelectAJAX.prototype.element_blur=function()
{
	if (!this.check_focus_interval) clearInterval(this.check_focus_interval);
	this.check_focus_interval=setTimeout(this.check_focus.bind(this), 20);
}

SelectAJAX.prototype.check_focus=function()
{
	if (document.activeElement===this.select) return;
	if (document.activeElement===this.input) return;
	if (document.activeElement===this.button) return;
	this.collapse();
	clearInterval(this.check_focus_interval);
	this.check_focus_interval=null;
}