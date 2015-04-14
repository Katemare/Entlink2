
// улучшенная версия image_location.js

/*
#####################################################
###                      Map                      ###
###   Изображение, на котором размещены локации.  ###
#####################################################
*/

var $maps={}, $panning;

function setup_map($map_id)
{
	window.addEventListener('load', complete_setup_map.bind(null, $map_id));
}

function complete_setup_map($map_id)
{
	new Map($map_id);
}

function Map($map_id)
{
	this.map_id=$map_id;	// может быть несколько карт, олицетворяющих одно и то же изображение.
	$maps[$map_id]=this;
	this.find_map();
	this.locations={};
}

has_custom_events(Map);

Map.prototype.find_map=function()
{
	this.element=document.getElementById('map_input'+this.map_id); // это должен быть div, содержащий img.
	this.img=this.element.firstChild;
}

Map.prototype.add_points=function($data)
{
	var $x;
	for ($x in $data)
	{
		new Map_point(this, $data[$x]);
	}
}

Map.prototype.add_fragments=function($data)
{
	var $x;
	for ($x in $data)
	{
		new Map_fragment(this, $data[$x]);
	}
}

Map.prototype.location_created=function($location)
{
	this.locations[$location.id]=$location;
	$location.add_custom_listener('mouse_over', this.relay_location_event.bind(this));
	$location.add_custom_listener('mouse_out', this.relay_location_event.bind(this));
	$location.add_custom_listener('mouse_down', this.relay_location_event.bind(this));
	$location.add_custom_listener('mouse_up', this.relay_location_event.bind(this));
	$location.add_custom_listener('click', this.relay_location_event.bind(this));
}

Map.prototype.display_locations=function()
{
	var $x;
	for ($x in this.locations)
	{
		this.locations[$x].draw();
	}
}

Map.prototype.point_by_id=function($id)
{
	if (!this.locations.hasOwnProperty($id)) return false;
	if (! (this.locations[$id] instanceof Map_point)) return false;
	return this.locations[$id];
}

Map.prototype.fragment_by_id=function($id)
{
	if (!this.locations.hasOwnProperty($id)) return false;
	if (! (this.locations[$id] instanceof Map_fragment)) return false;
	return this.locations[$id];
}

// позволяет подписаться на события открытого списка локаций определённой карты.
Map.prototype.relay_location_event=function($event)
{
	if ($event.type!='click') return; // TEST
	this.fire_custom_event($event);
}

function display_map_points($map_id, $points)
{
	window.addEventListener('load', complete_display_map_points.bind(null, $map_id, $points));
}

function complete_display_map_points($map_id, $points)
{
	if (!$maps.hasOwnProperty($map_id)) return;
	$maps[$map_id].add_points($points);
	$maps[$map_id].display_locations();
}

/*
#####################################################
###              Разовый показ маршрута           ###
#####################################################
*/

function display_map_route($map_id, $from, $to)
{
	if (document.readyState !== "complete")
	{
		window.addEventListener('load', display_map_route.bind(null, $map_id, $from, $to));
		return;
	}
	
	var map=$maps[$map_id];
	
	var canvas=document.createElement('canvas');
	canvas.className='map_canvas';
	canvas.width = map.img.clientWidth;
	canvas.height = map.img.clientHeight;
	map.element.appendChild(canvas);
	
	var context = canvas.getContext('2d');
	context.strokeStyle='#AA0000';
	context.lineWidth=3;
	
	$from=map.point_by_id($from);
	$to=map.point_by_id($to);
	context.moveTo($from.x, $from.y);
	context.lineTo($to.x, $to.y);
	
	var $length=Math.sqrt( Math.pow($to.x-$from.x, 2) + Math.pow($to.y-$from.y, 2) );
	var $angle=Math.acos( ($to.x-$from.x)/$length);
	if ($to.y<$from.y) $angle=2*Math.PI-$angle;
	$angle+=Math.PI;
	var $arrow_length=20, $arrow_incline=0.2;
	context.lineTo($to.x+Math.cos($angle-$arrow_incline)*$arrow_length, $to.y+Math.sin($angle-$arrow_incline)*$arrow_length);
	context.moveTo($to.x, $to.y);
	context.lineTo($to.x+Math.cos($angle+$arrow_incline)*$arrow_length, $to.y+Math.sin($angle+$arrow_incline)*$arrow_length);
	context.moveTo($to.x, $to.y);
	
	context.stroke();
	
	$from.pan(300, 1);
}

/*
#####################################################
###                  RouteManager                 ###
###      Объект, управляющий вводом маршрута.     ###
#####################################################
*/

function setup_map_route_input($map_id, $field_names /*, $reset_id */)
{
	window.addEventListener('load', complete_setup_map_route_input.bind(null, $map_id, $field_names));
}

function complete_setup_map_route_input($map_id, $field_names /*, $reset_id */)
{
	new RouteManager($map_id, $field_names /*, $reset_id */);
}

function RouteManager($map_id, $field_names /*, $reset_id */)
{
	if (typeof $map_id == 'object') this.map=$map_id;
	else this.map=$maps[$map_id];
	this.form=findForm(this.map.element);
	this.create_canvas();
	this.find_fields($field_names);
	this.fill_points();
	this.map.add_custom_listener('click', this.pick_point.bind(this));
	this.last_point=null;
}

RouteManager.prototype.create_canvas=function()
{
	this.canvas=document.createElement('canvas');
	this.canvas.className='map_canvas';
	this.canvas.width=this.map.img.clientWidth;
	this.canvas.height=this.map.img.clientHeight;
	this.map.element.appendChild(this.canvas);
	this.context=this.canvas.getContext('2d');
	this.erase();
}

RouteManager.prototype.find_fields=function($field_names)
{
	this.fields=[];
	var $x;
	for ($x in $field_names)
	{
		this.fields[$x]=this.form[$field_names[$x]];
	}
	this.max=this.fields.length;
}

RouteManager.prototype.fill_points=function()
{
	this.points=[];
	var $x, $location;
	for ($x in this.fields)
	{
		if (!this.fields[$x].value) break;
		$location=this.map.point_by_id(this.fields[$x].value);
		if (!$location) break;
		this.points[$x]=$location;
	}
	this.last_drawn=0;
	this.draw();
	if (this.points.length>0) this.points[0].pan(300, 1);
}

RouteManager.prototype.draw=function()
{
	if (this.points.length<=1) return;
	if (this.last_drawn==this.points.length-1) return;
	
	var $x;
	this.context.beginPath();
	for ($x=this.last_drawn; $x<this.points.length; $x++)
	{
		if ($x==this.last_drawn) this.context.moveTo(this.points[$x].x, this.points[$x].y);
		else this.context.lineTo(this.points[$x].x, this.points[$x].y);
	}
	
	// стрелка
	var $last_point=this.points[this.points.length-1], $pre_point=this.points[this.points.length-2];
	var $length=Math.sqrt( Math.pow($last_point.x-$pre_point.x, 2) + Math.pow($last_point.y-$pre_point.y, 2) );
	var $angle=Math.acos( ($last_point.x-$pre_point.x)/$length);
	if ($last_point.y<$pre_point.y) $angle=2*Math.PI-$angle;
	$angle+=Math.PI;
	var $arrow_length=20, $arrow_incline=0.2;
	this.context.lineTo($last_point.x+Math.cos($angle-$arrow_incline)*$arrow_length, $last_point.y+Math.sin($angle-$arrow_incline)*$arrow_length);
	this.context.moveTo($last_point.x, $last_point.y);
	this.context.lineTo($last_point.x+Math.cos($angle+$arrow_incline)*$arrow_length, $last_point.y+Math.sin($angle+$arrow_incline)*$arrow_length);
	this.context.moveTo($last_point.x, $last_point.y);
	
	this.context.stroke();
	this.last_drawn=this.points.length-1;
}

RouteManager.prototype.erase=function()
{
	this.canvas.width=this.canvas.width;
	this.context.strokeStyle='#AA0000';
	this.context.lineWidth=3;
}

RouteManager.prototype.reset=function()
{
	this.erase();
	this.points=[];
	this.last_drawn=0;
	var $x;
	for ($x in this.fields)
	{
		this.fields[$x].value='';
	}
}

RouteManager.prototype.save=function()
{
	var $x;
	for ($x in this.points)
	{
		this.fields[$x].value=this.points[$x].id;
	}
	for ($x=this.points.length; $x<this.fields.length-1; $x++)
	{
		this.fields[$x].value='';
	}
}

RouteManager.prototype.pick_point=function($event)
{
	if (!($event.details.map_location instanceof Map_point)) return;
	if ( (this.points.indexOf($event.details.map_location)!=-1) && (this.points.length<this.max) ) return;
	
	this.fix_highlight($event.details.map_location);
	var $next_point_id=this.next_point_index();
	if ($next_point_id<this.points.length) this.reset();
	
	this.points[$next_point_id]=$event.details.map_location;
	this.draw();
	this.save();
}

RouteManager.prototype.next_point_index=function()
{
	if (this.points.length==this.max) return 0;
	return this.points.length;
}

RouteManager.prototype.fix_highlight=function($point)
{
	this.unfix_highlight();
	
	$point.element.classList.add('selected');
	$point.highlight();
	this.last_point=$point;
}

RouteManager.prototype.unfix_highlight=function()
{
	if (!this.last_point) return;
	this.last_point.element.classList.remove('selected');
	this.last_point.dehighlight();
	this.last_point=null;
}

/*
#####################################################
###                  Map_location                 ###
###             Некий объект на карте             ###
#####################################################
*/

function Map_location($map_id, $data)
{
	this.id=$data.id;
	if (typeof $map_id == 'object') this.map=$map_id;
	else this.map=$maps[$map_id];
	this.x=parseInt($data.x);
	this.y=parseInt($data.y);
	if ($data.title) this.title=$data.title;
	this.map.location_created(this);
}

has_custom_events(Map_location);

Map_location.prototype.draw=function()
{
	if (!this.element) this.supply_element();
	this.append();
	this.place();
}

Map_location.prototype.center_x=function()
{
	return this.x-this.element.clientWidth/2;
}

Map_location.prototype.center_y=function()
{
	return this.y-this.element.clientHeight/2;
}

Map_location.prototype.center=function()
{
	this.element.scrollIntoView();
	this.map.element.scrollLeft-=this.map.element.clientWidth;
	this.map.element.scrollTop-=this.map.element.clientHeight;
}

Map_location.prototype.pan=function($pixels_per_second, $max_seconds)
{
	if ($panning) $panning.cancel_pan();
	this.origin_x=this.map.element.scrollLeft;
	this.origin_y=this.map.element.scrollTop;
	this.target_x=Math.max(0, Math.min(scrollLeftMax(this.map.element), this.center_x()-this.map.element.offsetWidth/2));
	this.target_y=Math.max(0, Math.min(scrollTopMax(this.map.element), this.center_y()-this.map.element.offsetHeight/2));
	
	var $length=Math.sqrt( Math.pow(this.target_x-this.origin_x, 2) + Math.pow(this.target_y-this.origin_y, 2));
	if ($length/$pixels_per_second>$max_seconds) $pixels_per_second=$length/$max_seconds;
	this.pixels_per_second=$pixels_per_second;
	
	this.period=3; // ms??
	/*
	this.pan_method=this.cancel_pan.bind(this);
	this.map.element.addEventListener('scroll', this.pan_method);
	*/
	this.interval=setInterval(this.pan_step.bind(this), this.period);
	
	$panning=this;
	this.pan_step();
}

Map_location.prototype.cancel_pan=function()
{
	clearInterval(this.interval);
	this.map.element.removeEventListener('scroll', this.pan_method);
	delete this.origin_x, this.origin_y, this.target_x, this.target_y, this.angle, this.pixels_per_second, this.period, this.interval, this.pan_method;
	$panning=null;
}

// Кажется, компьютер уверен, что в секунде 100 миллисекунд.
Map_location.prototype.pan_step=function()
{
	var $current_x=this.map.element.scrollLeft;
	var $current_y=this.map.element.scrollTop;
	
	var $length=Math.sqrt( Math.pow(this.target_x-$current_x, 2) + Math.pow(this.target_y-$current_y, 2));
	if ($length<=Math.max(this.pixels_per_second*(this.period/100), 10)) return this.pan_finish();
	
	var $angle=Math.acos( (this.target_x-$current_x)/$length);
	if (this.target_y<$current_y) $angle=2*Math.PI-$angle;
	
	var $new_x=$current_x+this.pixels_per_second*Math.cos($angle)*(this.period/100);
	var $new_y=$current_y+this.pixels_per_second*Math.sin($angle)*(this.period/100);

	this.map.element.scrollLeft=parseInt($new_x);
	this.map.element.scrollTop=parseInt($new_y);
}

Map_location.prototype.pan_finish=function()
{
	this.map.element.scrollLeft=this.target_x;
	this.map.element.scrollTop=this.target_y;
	this.cancel_pan();
}

Map_location.prototype.place=function()
{
	this.element.style.left=(this.center_x())+'px';
	this.element.style.top=(this.center_y())+'px';
	this.element.style.display='block';
}

Map_location.prototype.append=function()
{
	if (this.element.parentNode) return;
	this.map.element.insertBefore(this.element, this.map.img);
	this.element.style.display='block';
}

Map_location.prototype.remove=function()
{
	if (!this.element) return;
	if (!this.element.parentNode) return;
	this.element.parentNode.removeChild(this.element);
}

Map_location.prototype.supply_element=function()
{
	this.element=this.create_element();
	this.element.god=this;
	
	this.element.addEventListener('mouseover', this.element_mouse_over.bind(this));
	this.element.addEventListener('mouseout', this.element_mouse_out.bind(this));
	this.element.addEventListener('mousedown', this.element_mouse_down.bind(this));
	this.element.addEventListener('mouseup', this.element_mouse_over.bind(this));
	this.element.addEventListener('click', this.element_click.bind(this));
}

Map_location.prototype.create_element=function() { alert('inherit me'); }

Map_location.prototype.element_mouse_over=function($e)
{
	this.highlight();
	this.fire_custom_event(new SimpleEvent('mouse_over', { map_location: this, mouse: $e }));
}
Map_location.prototype.element_mouse_out=function($e)
{
	this.dehighlight();
	this.fire_custom_event(new SimpleEvent('mouse_out', { map_location: this, mouse: $e }));
}
Map_location.prototype.element_mouse_down=function($e)
{
	this.fire_custom_event(new SimpleEvent('mouse_down', { map_location: this, mouse: $e }));
}
Map_location.prototype.element_mouse_up=function($e)
{
	this.fire_custom_event(new SimpleEvent('mouse_up', { map_location: this, mouse: $e }));
}
Map_location.prototype.element_click=function($e)
{
	this.fire_custom_event(new SimpleEvent('click', { map_location: this, mouse: $e }));
}

Map_location.prototype.highlight=function()
{
	this.element.classList.add('highlighted');
	this.fire_custom_event(new SimpleEvent('highlight', { map_location: this}));
}

Map_location.prototype.dehighlight=function()
{
	this.element.classList.remove('highlighted');
	this.fire_custom_event(new SimpleEvent('dehighlight', { map_location: this}));
}

/*
#####################################################
###                    Map_point                  ###
###                 Точка на карте.               ###
#####################################################
*/

function Map_point($map_id, $data)
{
	Map_location.call(this, $map_id, $data);
}

Map_point.prototype = Object.create(Map_location.prototype);
Map_point.prototype.constructor = Map_point;

Map_point.prototype.marker_src=function() { return '../files/gallery_frontend/star.gif'; }

Map_point.prototype.marker_src_over=function() { return '../files/gallery_frontend/star_over.gif'; }

Map_point.prototype.create_element=function()
{
	var $element=document.createElement('img');
	$element.className='pointer';
	$element.src=this.marker_src();
	$element.setAttribute('title', this.title);
	return $element;
}

Map_point.prototype.place=function()
{
	if (this.element.complete)
	{
		this.show();
		Map_location.prototype.place.call(this);
	}
	else
	{
		this.hide();
		this.element.addEventListener('load', this.place.bind(this));
	}
}

Map_point.prototype.highlight=function()
{
	this.element.src=this.marker_src_over();
	Map_location.prototype.highlight.call(this);
}

Map_point.prototype.dehighlight=function()
{
	if (!this.element.classList.contains('selected')) this.element.src=this.marker_src();
	Map_location.prototype.highlight.call(this);
}

Map_point.prototype.hide=function()
{
	this.element.style.visibility='hidden'
}

Map_point.prototype.show=function()
{
	this.element.style.visibility='visible';
}

/*
#####################################################
###                 Map_fragment                  ###
###              Фрагмент на карте.               ###
#####################################################
*/

function Map_fragment($map_id, $data)
{
	this.x2=parseInt($data.x2);
	this.y2=parseInt($data.y2);
	Map_location.call(this, $map_id, $data);
}

Map_fragment.prototype = Object.create(Map_location.prototype);
Map_fragment.prototype.constructor = Map_fragment;

Map_fragment.prototype.create_element=function()
{
	var $border=document.createElement('div');
	$border.className='fragment_border';
	$border.setAttribute('title', this.title);
	return $border;
}

Map_fragment.prototype.place=function()
{
	this.element.style.left=this.x+'px';
	this.element.style.top=this.y+'px';
	this.element.style.width=this.x2-this.x+'px';
	this.element.style.height=this.y2-this.y+'px';
	this.element.style.display='block';
}