// js не умеет считать координаты на странице из коробки.
function element_position(e) {
    var x = 0, y = 0;
    var inner = true ;
    do {
        x += e.offsetLeft;
        y += e.offsetTop;
        var style = getComputedStyle(e,null) ;
        var borderTop = getNumericStyleProperty(style,"border-top-width") ;
        var borderLeft = getNumericStyleProperty(style,"border-left-width") ;
		
        y += borderTop;
        x += borderLeft;
        if (inner){
          var paddingTop = getNumericStyleProperty(style,"padding-top") ;
          var paddingLeft = getNumericStyleProperty(style,"padding-left") ;
          y += paddingTop ;
          x += paddingLeft ;
        }
        inner = false ;
    } while (e = e.offsetParent);
    return { x: x, y: y };
}

function getNumericStyleProperty(style, prop){
    return parseInt(style.getPropertyValue(prop),10) ;
}

function hasHorizontalScrollbar($obj)
{
	return $obj.scrollWidth > $obj.clientWidth;
}
function hasVerticalScrollbar($obj)
{
	return $obj.scrollHeight > $obj.clientHeight;
}

// аналогично, эта полезная функция должна быть включена в стандартный набор.
function findForm(node)
{
	while (node && node.nodeName != "FORM" && node.parentNode) {
		node = node.parentNode;
	}
	
	if (node.nodeName=="FORM") return node;
	return false;
}

function pauseEvent(e){
    if(e.stopPropagation) e.stopPropagation();
    if(e.preventDefault) e.preventDefault();
    e.cancelBubble=true;
    e.returnValue=false;
    return false;
}

function escapeRegExp(str) {
  return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
}

function find_option_by_value($select, $value)
{
	for ($x in $select.options)
	{
		if ($select.options[$x].value==$value) return $x;
	}
	return false;
}

function insertAfter(referenceNode, newNode) {
    referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
}

function SimpleEvent($type)
{
	if (typeof arguments[1] != 'undefined') $details=arguments[1];
	else $details=null;
	
	this.type=$type;
	this.details=$details;
}

function has_custom_events($constructor)
{
	$constructor.prototype.fire_custom_event=function($event)
	{
		if (!this.custom_event_listeners) return;
		if (!this.custom_event_listeners.hasOwnProperty($event.type)) return;
		
		var $x, $data, $call;
		for ($x in this.custom_event_listeners[$event.type])
		{
			$call=this.custom_event_listeners[$event.type][$x];
			$call($event);
		}
	}
	
	$constructor.prototype.add_custom_listener=function($type, $listener) // не знаю, как обойти указание явное объекта >_<
	{
		if (!this.custom_event_listeners) this.custom_event_listeners={};
		if (!this.custom_event_listeners.hasOwnProperty($type)) this.custom_event_listeners[$type]=[];
		else if (this.custom_event_listeners[$type].indexOf($listener)!=-1) return;
		this.custom_event_listeners[$type][this.custom_event_listeners[$type].length]=$listener;
	}
	
	$constructor.prototype.remove_custom_listener=function($type, $listener)
	{
		if (!this.custom_event_listeners) return;
		if (!this.custom_event_listeners.hasOwnProperty($event.type)) return;
		var $index=this.custom_event_listeners[$type].indexOf($listener);
		if ($index==-1) return;
		this.custom_event_listeners[$type].splice($index, 1);
	}
}

function scrollLeftMax($element)
{
	return $element.scrollWidth-$element.clientWidth;
}

function scrollTopMax($element)
{
	return $element.scrollHeight-$element.clientHeight;
}

function sort_assoc($obj)
{
	var sortable = [];
	for (var x in $obj)
		  sortable.push([x, $obj[x]]);
	sortable.sort(function(a, b) {return a[1] - b[1]});
	
	var $result={};
	for (var x in sortable)
		$result[sortable[0]]=sortable[1];
	
	return $result;
}

function register_load_function($func)
{
	if (document.readyState === "complete")
	{
		$func.call();
		return;
	}
	var $priority;
	if (typeof arguments[1] === 'undefined') $priority=register_load_function.PRIORITY_DEFAULT;
	else $priority=arguments[1];
	
	if (!register_load_function.to_load) register_load_function.to_load=[];
	register_load_function.to_load[register_load_function.to_load.length]={call: $func, priority: $priority};
}
register_load_function.PRIORITY_STANDARD_FRAMEWORK=0;
register_load_function.PRIORITY_CONSTRUCT_LAYOUT=10;
register_load_function.PRIORITY_INTERACTION_FRAMEWORK=20;
register_load_function.PRIORITY_LAST=30;
register_load_function.PRIORITY_DEFAULT=register_load_function.PRIORITY_LAST;

function run_load_functions()
{
	if (typeof arguments[0] !== 'undefined') $source=arguments[0]; else $source=register_load_function.to_load;
	if (!$source) return;
	
	$source.sort( function(a,b) { return a.priority-b.priority; } );
	
	for (var $x in $source)
	{
		$source[$x].call();
	}
}
window.addEventListener('load', function() { run_load_functions(); } /* избавляет от лишнего аргумента. */ );

// их необходимо запускать после добавления или изменения HTML.
function register_init_function($func)
{
	var $priority;
	if (typeof arguments[1] === 'undefined') $priority=register_load_function.PRIORITY_DEFAULT;
	else $priority=arguments[1];
	
	if (!register_init_function.to_init) register_init_function.to_init=[];
	register_init_function.to_init[register_init_function.to_init.length]={call: $func, priority: $priority};
	
	register_load_function($func, $priority);
}

function run_init_functions()
{
	if (!register_init_function.to_init) return;
	run_load_functions(register_init_function.to_init);
}

function is_window_loaded()
{
	return document.readyState === "complete";
}

function bind_arguments(args)
{
	var $args_array=Array.prototype.slice.call(args, 0);
	return [null].concat($args_array);
}

function is_mobile()
{
	return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

function get_preloader()
{
	if (!get_preloader.div)
	{
		var div=document.createElement('div');
		div.className='preloader';
		div.style.display='none';
		document.body.appendChild(div);
		get_preloader.div=div;
	}
	return get_preloader.div;
}