
var $map_data={},
	$point_img='files/gallery_frontend/star.gif',
	$point_img_over='files/gallery_frontend/star_over.gif',
	$point_img_side=12,
	$map_dragging=false,
	$LMB=0;

function analyze_event($e)
{
	var $map_id=$e.currentTarget.getAttribute('map_id');
	if ($map_id===null) return;
	var $data=$map_data[$map_id];
	var $scroll_parent=$e.currentTarget.parentNode;
	var $x=$e.pageX-$data.pageX+$scroll_parent.scrollLeft, $y=$e.pageY-$data.pageY+$scroll_parent.scrollTop;
	return {map_id: $map_id, x: $x, y: $y};
}
	
function map_click($e)
{
	var $analysis=analyze_event($e);
	if (!$analysis) return;
	var $data=$map_data[$analysis.map_id];
	if ($data.type==='point') save_coords($analysis.map_id, $analysis.x, $analysis.y);
	else if ($data.type==='fragment')
	{
		if ( ($map_dragging===false) || ($map_dragging.moved===false) ) reset_fragment($analysis.map_id);
		$map_dragging=false;
	}
}

function map_mouse_down($e)
{
	if ($e.button===$LMB)
	{
		var $analysis=analyze_event($e);
		if (!$analysis) return;
		
		$map_dragging=$analysis;
		$map_dragging.moved=false;
		$map_dragging.origin_x=$analysis.x;
		$map_dragging.origin_y=$analysis.y;
		save_origin($analysis.map_id, $map_dragging.origin_x, $map_dragging.origin_y);
	}
	pauseEvent($e);
}

function map_mouse_up($e)
{
	if ($map_dragging!==false) $map_dragging=$map_dragging.moved;
}

function map_mouse_out($e)
{
}

function map_mouse_move($e)
{
	if (typeof $map_dragging == 'object')
	{
		$map_dragging.moved=true;
		var $map_id=$map_dragging.map_id;
		var $data=$map_data[$map_id],
			$scroll_parent=$data.map.parentNode;
		var $x=$e.pageX-$data.pageX+$scroll_parent.scrollLeft, $y=$e.pageY-$data.pageY+$scroll_parent.scrollTop;
		save_corner($map_id, $x, $y);
		pauseEvent($e);
	}
}

function update_pointer($map_id)
{
	var $data=$map_data[$map_id];
	var $form=$data.form,
		$x_field=$form[$data.x_name],
		$y_field=$form[$data.y_name];
	
	if ( (!$x_field) || (!$y_field) ) return;
	if ( ($x_field.value==='') || ($y_field.value==='') ) return;
	place_pointer($map_id, $x_field.value, $y_field.value);
}

function update_fragment_border($map_id)
{
	var $data=$map_data[$map_id];
	var $form=$data.form,
		$x_field=$form[$data.x_name],
		$y_field=$form[$data.y_name],
		$x2_field=$form[$data.x2_name],
		$y2_field=$form[$data.y2_name];
	
	if ( (!$x_field) || (!$y_field) || (!$x2_field) || (!$y2_field) ) return;
	if ( ($x_field.value==='') || ($y_field.value==='') || ($x2_field.value==='') || ($y2_field.value==='') ) return;
	place_fragment_border($map_id, $x_field.value, $y_field.value, $x2_field.value, $y2_field.value);
}

function save_coords($map_id, $x, $y)
{
	var $map=get_image_map($map_id);
	if (!$map) return;

	var $data=$map_data[$map_id],
		$form=$data.form,
		$x_field=$form[$data.x_name],
		$y_field=$form[$data.y_name];
	
	$x_field.value=$x;
	$y_field.value=$y;
	place_pointer($map_id, $x, $y);
}

function save_origin($map_id, $x, $y)
{
	var $map=get_image_map($map_id);
	if (!$map) return;

	var $data=$map_data[$map_id],
		$form=$data.form,
		$x_field=$form[$data.x_name],
		$y_field=$form[$data.y_name],
		$x2_field=$form[$data.x2_name],
		$y2_field=$form[$data.y2_name];
	
	$x_field.value=$x;
	$y_field.value=$y;
	$x2_field.value=$x;
	$y2_field.value=$y;
	place_fragment_border($map_id, $x, $y, $x, $y);
}

function save_corner($map_id, $x, $y)
{
	var $map=get_image_map($map_id);
	if (!$map) return;

	var $data=$map_data[$map_id],
		$form=$data.form,
		$x_field=$form[$data.x_name],
		$y_field=$form[$data.y_name],
		$x2_field=$form[$data.x2_name],
		$y2_field=$form[$data.y2_name],
		$origin_x, $origin_y;

	if ($x<0) $x=0;
	else if ($x>$map.clientWidth) $x=$map.clientWidth;
	if ($y<0) $y=0;
	else if ($y>$map.clientHeight) $y=$map.clientHeight;
	if ($x>=$map_dragging.origin_x) $origin_x=$map_dragging.origin_x;
	else { $origin_x=$x; $x=$map_dragging.origin_x; }
	if ($y>=$map_dragging.origin_y) $origin_y=$map_dragging.origin_y;
	else { $origin_y=$y; $y=$map_dragging.origin_y; }
	
	$x_field.value=$origin_x;
	$x2_field.value=$x;
	$y_field.value=$origin_y;
	$y2_field.value=$y;

	place_fragment_border($map_id, $origin_x, $origin_y, $x, $y);
}

function reset_fragment($map_id)
{
	var $map=get_image_map($map_id);
	if (!$map) return;
	
	var $data=$map_data[$map_id],
		$form=$data.form,
		$x_field=$form[$data.x_name],
		$y_field=$form[$data.y_name],
		$x2_field=$form[$data.x2_name],
		$y2_field=$form[$data.y2_name];
		
	$x_field.value='';
	$x2_field.value='';
	$y_field.value='';
	$y2_field.value='';
	$data.fragment_border.style.display='none';
}

function place_pointer($map_id, $x, $y)
{
	var $map=get_image_map($map_id);
	if (!$map) return;

	var $data=$map_data[$map_id];
	$data.pointer.style.display='block';
	$data.pointer.style.left=($x-$point_img_side/2)+'px';
	$data.pointer.style.top=($y-$point_img_side/2)+'px';
}

function place_fragment_border($map_id, $x, $y, $x2, $y2)
{
	var $map=get_image_map($map_id);
	if (!$map) return;

	var $data=$map_data[$map_id];
	if ( (typeof $map_dragging != 'object') || ($map_dragging.moved) ) $data.fragment_border.style.display='block';
	else $data.fragment_border.style.display='none';
	
	$data.fragment_border.style.left=$x+'px';
	$data.fragment_border.style.top=$y+'px';
	$data.fragment_border.style.width=($x2-$x)+'px';
	$data.fragment_border.style.height=($y2-$y)+'px';
}

function get_image_map($map_id)
{
	var $map=document.getElementById('image_map_'+$map_id);
	if (!$map) return false;
	return $map;
}

function setup_point_map($map_id, $x_name, $y_name)
{
	var $map=get_image_map($map_id);
	if (!$map) return;
	$map.setAttribute('map_id', $map_id);
	var $form=findForm($map);
	$map_data[$map_id]=
	{
		type:'point',
		x_name: $x_name, y_name: $y_name,
		form: $form,
		map: $map
	};
}

function setup_fragment_map($map_id, $x_name, $y_name, $x2_name, $y2_name)
{
	var $map=get_image_map($map_id);
	if (!$map) return;
	$map.setAttribute('map_id', $map_id);
	var $form=findForm($map);
	$map_data[$map_id]=
	{
		type:'fragment',
		x_name: $x_name, y_name: $y_name,
		x2_name: $x2_name, y2_name: $y2_name,
		form: $form,
		map: $map
	};
}

var $display_maps={};
function display_locations($map_html_id, $ids)
{
	var $map=document.getElementById($map_html_id);
	if (!$map) return;
	$display_maps[$map_html_id]={ids: $ids, map: $map};
}

function complete_location_display()
{
	var $data, $coords, $id;
	for ($x in $display_maps)
	{
		$data=$display_maps[$x];
		$coords=element_position($data.map);
		$data.pageX=$coords.x;
		$data.pageY=$coords.y;
		
		for ($i in $data.ids)
		{
			$id=$data.ids[$i];
			if (($fragments)&&($fragments.hasOwnProperty($id))) display_fragment($data.map, $id);
			else if (($points)&&($points.hasOwnProperty($id))) display_point($data.map, $id);
		}
	}
}

function display_fragment($map, $id)
{
	var $fragment=$fragments[$id];
	
	var $border=document.createElement('div');
	$border.className='fragment_border marker';
	$map.parentNode.insertBefore($border, $map.parentNode.firstChild);
	
	$border.style.left=$fragment.coord_x+'px';
	$border.style.top=$fragment.coord_y+'px';
	$border.style.width=($fragment.coord_x2-$fragment.coord_x)+'px';
	$border.style.height=($fragment.coord_y2-$fragment.coord_y)+'px';
	$border.style.display='block';
	$border.setAttribute('location_id', $id);
	$fragment.border=$border;
	$fragment.element=$border;
	$border.setAttribute('title', $fragment.title);
	$fragment.map=$map;
}

function display_point($map, $id)
{
	var $point=$points[$id];
	
	var $pointer=document.createElement('img');
	$pointer.className='pointer marker';
	$pointer.src='../'+$point_img;
	$map.parentNode.insertBefore($pointer, $map.parentNode.firstChild);
	
	$pointer.style.left=($point.coord_x-$point_img_side/2)+'px';
	$pointer.style.top=($point.coord_y-$point_img_side/2)+'px';
	$pointer.style.display='block';
	$pointer.setAttribute('location_id', $id);
	$point.pointer=$pointer;
	$point.element=$pointer;
	$pointer.setAttribute('title', $point.title);
	$point.map=$map;
}

var $location_selects={};
function setup_location_select($map_html_id, $select_html_id)
{
	var $select=document.getElementById($select_html_id),
		$map=document.getElementById($map_html_id);
	
	if (!$map) return;
	if (!$select) return;
	
	$select.old_location=null;
	$location_selects[$select_html_id]={select: $select, map: $map};
}

function highlight_fragment($location_id)
{
	if (!$fragments.hasOwnProperty($location_id)) return;
	var $fragment=$fragments[$location_id];
	$fragments[$location_id].border.classList.add('highlighted');
}

function dehighlight_fragment($location_id)
{
	if (!$fragments.hasOwnProperty($location_id)) return;
	$fragments[$location_id].border.classList.remove('highlighted');
}

function highlight_point($location_id)
{
	if (!$points.hasOwnProperty($location_id)) return;
	$points[$location_id].pointer.classList.add('highlighted');
	$points[$location_id].pointer.src='../'+$point_img_over;
}

function dehighlight_point($location_id)
{
	if (!$points.hasOwnProperty($location_id)) return;
	$points[$location_id].pointer.classList.remove('highlighted');
	$points[$location_id].pointer.src='../'+$point_img;
}

function location_mouse_over($e)
{
	var $location_id=$e.currentTarget.getAttribute('location_id');
	if ($points.hasOwnProperty($location_id)) highlight_point($location_id)
	else if ($fragments.hasOwnProperty($location_id)) highlight_fragment($location_id)
}

function location_mouse_out($e)
{
	var $location_id=$e.currentTarget.getAttribute('location_id');
	if ( ($points.hasOwnProperty($location_id)) && (!$points[$location_id].pointer.classList.contains('selected')) ) dehighlight_point($location_id);
	else if ( ($fragments.hasOwnProperty($location_id)) && (!$fragments[$location_id].border.classList.contains('selected')) ) dehighlight_fragment($location_id);
}

function location_click($e)
{
	var $location_id=$e.currentTarget.getAttribute('location_id'), $location;
	if ($points.hasOwnProperty($location_id)) $location=$points[$location_id];
	else if ($fragments.hasOwnProperty($location_id)) $location=$fragments[$location_id];
	else return;
	
	var $option;
	for ($x in $location.select.options)
	{
		$option=$location.select.options[$x];
		if ($option.value==$location_id)
		{
			$location.select.selectedIndex=$x;
			location_select_change({currentTarget: $location.select}); // почему-то когда меняешь значение в скрипте, событие не срабатывает.
			break;
		}
	}
}

function location_select_change($e)
{
	var $select=$e.currentTarget, $location_id, $location,
		$old_location=$select.old_location;
	
	if ($select.selectedIndex==-1) return;
	$location_id=$select.options[$select.selectedIndex].value;
	$select.old_location=$location_id;
	pick_location($location_id, $old_location);
}

function pick_location($location_id, $old_location)
{
	if ($old_location)
	{
		if ($fragments.hasOwnProperty($old_location))
		{
			$fragments[$old_location].element.classList.remove('selected');
			dehighlight_fragment($old_location);
		}
		else if ($points.hasOwnProperty($old_location))
		{
			$points[$old_location].element.classList.remove('selected');
			dehighlight_point($old_location);
		}
	}
	
	if ($fragments.hasOwnProperty($location_id))
	{
		$location=$fragments[$location_id];
		$location.element.classList.add('selected');
		highlight_fragment($location_id);
	}
	else if ($points.hasOwnProperty($location_id))
	{
		$location=$points[$location_id];
		$location.element.classList.add('selected');
		highlight_point($location_id);
	}
}

function complete_location_select_setup()
{
	var $data, $location, $option, $location_id;
	for ($x in $location_selects)
	{
		$data=$location_selects[$x];
		$data.select.addEventListener('change', location_select_change);
		
		for ($y in $data.select.options)
		{
			$option=$data.select.options[$y];
			$location_id=$option.value;
			
			if ($fragments.hasOwnProperty($location_id)) $location=$fragments[$location_id];
			else if ($points.hasOwnProperty($location_id)) $location=$points[$location_id];
			else continue;
			
			$location.element.addEventListener('mouseover', location_mouse_over);
			$location.element.addEventListener('mouseout', location_mouse_out);
			$location.element.addEventListener('click', location_click);
			$location.select=$data.select;
		}
		
		location_select_change({currentTarget: $data.select}); // симуляция события.
	}
}

function complete_map_setup()
{
	for ($map_id in $map_data)
	{
		$map=get_image_map($map_id)
		var $coords=element_position($map);
		$map_data[$map_id].pageX=$coords.x;
		$map_data[$map_id].pageY=$coords.y;	
		
		if ($map_data[$map_id].type=='point')
		{
			var $pointer=document.createElement('img');
			$pointer.className='pointer input';
			$pointer.src='../'+$point_img;
			$map.parentNode.insertBefore($pointer, $map.parentNode.firstChild);
			$map_data[$map_id].pointer=$pointer;
			$map.addEventListener('click', map_click);
			update_pointer($map_id);
		}
		else if ($map_data[$map_id].type=='fragment')
		{
			var $border=document.createElement('div');
			$border.className='fragment_border input';
			$map.parentNode.insertBefore($border, $map.parentNode.firstChild);
			$map_data[$map_id].fragment_border=$border;
			update_fragment_border($map_id);
			
			$map.addEventListener('click', map_click);
			$map.addEventListener('mouseout', map_mouse_out);
			window.addEventListener('mousemove', map_mouse_move); // чтобы карта могла реагировать и на движение мыши за её пределами
			$map.addEventListener('mousedown', map_mouse_down);
			window.addEventListener('mouseup', map_mouse_up); // чтобы карта могла реагировать на отпускание клавиши за её пределами
		}
	}
}

function is_point_inputted($form_id)
{
	var $form=document.getElementById($form_id);
	if (!$form) return;
	var $good_form=($form.coord_x.value!='') && ($form.coord_y.value!='');
	if (!$good_form) alert('Выберите точку!');
	return $good_form;
}

function is_fragment_inputted($form_id)
{
	// STUB: названия полей могут быть другие, нужно сохранить их в массив и брать оттуда.
	var $form=document.getElementById($form_id);
	if (!$form) return;
	var $good_form=($form.coord_x.value!='') && ($form.coord_y.value!='') && ($form.coord_y2.value!='') && ($form.coord_y2.value!='');
	if (!$good_form) alert('Выберите фрагмент!');
	return $good_form;
}

var $fragment_selects=[];
function setup_fragment_select($form_id, $select_name, $map_container_id)
{
	var $select_num=$fragment_selects.length,
		$form=document.getElementById($form_id),
		$select=$form[$select_name],
		$map_container=document.getElementById($map_container_id),
		$map;
	
	if (!$map_container) return;
	if (!$form) return;
	if (!$select) return;
	
	for ($x in $map_container.children)
	{
		if ($map_container.children[$x].classList.contains('image_map'))
		{
			$map=$map_container.children[$x];
			break;
		}
	}
	if (!$map) return;
	
	var $border=document.createElement('div');
	$border.className='fragment_border input';
	$map.insertBefore($border, $map.firstChild);
	
	$fragment_selects[$select_num]={fragment_border: $border, map: $map, select: $select};
	$select.setAttribute('fragment_select_num', $select_num);
	$select.addEventListener('change', fragment_select_change);
}

function fragment_select_change($e)
{
	var $select_num=$e.currentTarget.getAttribute('fragment_select_num');
	update_fragment_select($num);
}

function update_fragment_select($num)
{
	var $data=$fragment_selects[$num],
		$selection=$data.select.selectedIndex;
	
	if (!$selection)
	{
		unselect_fragment($num);
		return;
	}
	
	$selection=$data.select.options[$selection].value;
	
	if (!$selection) unselect_fragment($num);
	else select_fragment($num, $selection);
}

function unselect_fragment($num)
{
	var $data=$fragment_selects[$num];
	
	$data.fragment_border.style.display='none';
}

function select_fragment($num, $fragment_id)
{
	var $data=$fragment_selects[$num],
		$fragment=$fragments[$fragment_id];
	
	$data.fragment_border.style.display='block';
	$data.fragment_border.style.left=$fragment.coord_x+'px';
	$data.fragment_border.style.top=$fragment.coord_y+'px';
	$data.fragment_border.style.width=$fragment.coord_x2-$fragment.coord_x+'px';
	$data.fragment_border.style.height=$fragment.coord_y2-$fragment.coord_y+'px';
	$data.fragment_border.setAttribute('title', $fragment.title);
}

function complete_fragment_select_setup()
{
	if ($fragment_selects.length==0) return;
	
	var $data;
	for ($num in $fragment_selects)
	{
		$data=$fragment_selects[$num];
		var $coords=element_position($data.map);
		$data.pageX=$coords.x;
		$data.pageY=$coords.y;
		update_fragment_select($num)
	}
}

function image_related_completion()
{
	complete_location_display();
	complete_location_select_setup();
	complete_map_setup();
	complete_fragment_select_setup();
}
window.addEventListener('load', image_related_completion);