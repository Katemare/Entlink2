function createCookie(name,value,days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	}
	else var expires = "";
	document.cookie = name+"="+value+expires+"; path=/";
}

function readCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
}

function eraseCookie(name) {
	createCookie(name,"",-1);
}

"use strict";
function extend(constructor, parent_constructor /* , ... */)
{
	var traits_from;
	if (parent_constructor.instanceOf(Function))
	{
		constructor.prototype=Object.create(parent_constructor.prototype);
		constructor.prototype.constructor=constructor;
		constructor.superclass=parent_constructor.prototype;

		if (parent_constructor.prototype.constructor == Object.prototype.constructor)
		{
			parent_constructor.prototype.constructor = parent_constructor; // избавляет от какой-то там ошибки, возможно, работы <instanceof></instanceof>
		}
		
		traits_from=2;
	}
	else traits_from=1;
	
	if (arguments.length<=traits_from) return;
	for (var x=traits_from; x<arguments.length; x++)
	{
		if (arguments[x] instanceof Function) use_trait(constructor, arguments[x].prototype); // не гарантирует, что будет вызван конструктор! и не будет отвечать на instanceof.
		else use_trait(constructor, arguments[x]);
	}
}

function use_trait(constructor, trait)
{
	receive_properties(constructor.prototype, trait);
}

function receive_properties(target, source)
{
	var keys=Object.keys(source), key, getter, setter;
	for (var x in keys)
	{
		key=keys[x];
		getter=source.__lookupGetter__(key);
		setter=source.__lookupSetter__(key);
		
		if (getter || setter)
		{
			if (getter) target.__defineGetter__(key, getter);
			if (setter) target.__defineSetter__(key, setter);
		}
		else target[key]=source[key];
	}
}

function merge_objects()
{
	var result={};
	for (var x=0; x<arguments.length; x++)
	{
		receive_properties(result, arguments[x]);
	}
	return result;
}

function loadJSON(path, callback)
{
    var xobj = new XMLHttpRequest();
        xobj.overrideMimeType("application/json");
    xobj.open('GET', path, true);
    xobj.onreadystatechange = function () {
          if (xobj.readyState == 4 && xobj.status == "200") {
            // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
            callback(xobj.responseText);
          }
    };
    xobj.send(null);  
 }