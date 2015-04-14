
var $descriptions_by_id={}
function setup_collapsible_descriptions()
{
	var $x, $key;
	
	var $elements=document.getElementsByClassName('description requestable');	
	for ($x=0; $x<$elements.length; $x++)
	{
		$key=$elements[$x].getAttribute('contribution_group')+$elements[$x].getAttribute('contribution_id');
		$descriptions_by_id[$key]=$elements[$x];
		new ElementExpander($elements[$x], ElementExpander.prototype.MODE_AJAX);
	}
	
	$elements=document.getElementsByClassName('description collapsed');
	for ($x=0; $x<$elements.length; $x++)
	{
		new ElementExpander($elements[$x]);
	}
	
	$elements=document.getElementsByClassName('description collapsible');
	for ($x=0; $x<$elements.length; $x++)
	{
		new ElementExpander($elements[$x], ElementExpander.prototype.MODE_EXPANDED);
	}
}

window.addEventListener('load', setup_collapsible_descriptions);

function parse_received($result, $method, $request_code)
{
	if ($method==='contribution_description')
	{
		var $id=fcd($result, 'id'), $group=fcd($result, 'group');
		var $key=$group+$id;
		if (!$descriptions_by_id.hasOwnProperty($key)) return;
		
		var $description=$descriptions_by_id[$key];
		$description.god.receive(fcd($result, 'description'));
	}
}

function ElementExpander($target)
{
	this.target=$target;
	this.target.god=this;
	if (typeof arguments[1] !== 'undefined') this.mode=arguments[1];
	else this.mode=this.MODE_COLLAPSED;
	
	this.create_content();
	this.create_element();
	if (this.mode===this.MODE_EXPANDED) this.expand();
	else if (this.mode===this.MODE_COLLAPSED) this.collapse();
	else if (this.mode===this.MODE_AJAX) this.hide_content();
	this.target.style.display='block';
	
}
ElementExpander.prototype.MODE_COLLAPSED=1; // текст уже присутствует, но свЄрнут.
ElementExpander.prototype.MODE_EXPANDED=2; // текст уже присутствует и может быть свЄрнут.
ElementExpander.prototype.MODE_AJAX=3; // текст нужно запросить по ј€ксу, пока он свЄрнут.
ElementExpander.prototype.MODE_REQUESTED=4; // текст запрошен.

ElementExpander.prototype.create_content=function()
{
	this.content=document.createElement('div');
	this.content.innerHTML=this.target.innerHTML;
	this.target.innerHTML='';
	this.target.appendChild(this.content);
}

ElementExpander.prototype.create_element=function()
{
	this.element=document.createElement('div');
	this.element.className='element_expander';
	this.element.god=this;
	this.target.appendChild(this.element);
	this.element.addEventListener('click', this.element_clicked.bind(this) );
}

ElementExpander.prototype.hide_content=function()
{
	this.content.style.display='none';
}

ElementExpander.prototype.show_content=function()
{
	this.content.style.display='block';
}

ElementExpander.prototype.collapse=function()
{
	this.hide_content();
	this.mode=this.MODE_COLLAPSED;
	this.element.classList.add('expand');
	this.element.classList.remove('collapse');
}

ElementExpander.prototype.expand=function()
{
	this.show_content();
	this.mode=this.MODE_EXPANDED;
	this.element.classList.add('collapse');
	this.element.classList.remove('expand');
}

ElementExpander.prototype.request=function()
{
	var $contribution_id=this.target.getAttribute('contribution_id');
	var $contribution_group=this.target.getAttribute('contribution_group');
	makeRequest('../api/user.php', 'contribution_description', 0, 'group='+$contribution_group+'&id='+$contribution_id);
	this.element.classList.add('requested');
	this.mode=this.MODE_REQUESTED;
}

ElementExpander.prototype.element_clicked=function()
{
	if (this.mode==this.MODE_COLLAPSED) this.expand();
	else if (this.mode==this.MODE_EXPANDED) this.collapse();
	else if (this.mode==this.MODE_AJAX) this.request();
	var $temp;
}

ElementExpander.prototype.receive=function($content)
{
	this.content.innerHTML=$content;
	this.mode=this.MODE_COLLAPSED;
	this.expand();
}