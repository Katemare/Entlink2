var $req;
var $reqnum;
var $timeout;
try
  {
  $req = new XMLHttpRequest();
  } catch(e)
    {
    try
      {
      $req = new ActiveXObject('Msxml2.XMLHTTP');
      } catch(e)
        {
        try
          {
          $req = new ActiveXObject('Microsoft.XMLHTTP');
          } catch(e)
            {
            $req = null;
            }
        }
    }

var $req_queue=[];
function makeRequest(rx, msg, code, extra)
{
	if (!$req) return;
	
	if ($reqnum>0)
	{
		$req_queue[$req_queue.length]=arguments;
	}
	else
	{
		$req.open('POST', rx, true);
		$req.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		$req.onreadystatechange = catch_send;
		$req.send('message=' + msg + '&request_code=' + encodeURIComponent(code) + ((extra != null) ? ('&' + extra) : ''));
		$reqnum++;
		$timeout = setTimeout(abort_request, 10000);
	}
}

function catch_send()
{
var resp;
try // https://bugzilla.mozilla.org/show_bug.cgi?id=238559
  {
  if ($req.readyState == 4)
    {
    clearTimeout($timeout);
    if ($req.status == 200)
      {
      resp = $req.responseXML.documentElement;	  
      parse_received(
        resp.getElementsByTagName('result')[0],
        resp.getElementsByTagName('method')[0].firstChild.data,
		resp.getElementsByTagName('request_code')[0].firstChild.data
        );
      }
    }
  $reqnum--;
  check_request_queue();
  } catch(e) {};
}

function abort_request()
{
    $req.abort();
	check_request_queue();
}

// STUB! пока не умеет комбинировать запросы.
function check_request_queue()
{
	if ($req_queue.length==0) return;
	var $arguments=$req_queue[0];
	$req_queue.shift();
	makeRequest.apply(null, $arguments);
}

function fcd($obj, $tag)
{
try
  {
  return $obj.getElementsByTagName($tag)[0].firstChild.data;
  } catch(e)
    {
    return '';
    }
}

function Request(call, server_address, msg, code, extra)
{
	this.req=new XMLHttpRequest();
	this.server_address=server_address;
	this.message=msg;
	this.request_code=code;
	this.extra=extra;
	this.calls=[];
	this.abort_calls=[];
	if (call) this.add_call(call);
	this.set=false;
	this.finished=false;
}

Request.current=null;
Request.queue=[];

Request.prototype.make=function()
{
	if (this.set) return;
	if (!Request.current) this.execute();
	else this.queue();
	this.set=true;
}

Request.prototype.execute=function()
{
	Request.current=this;
	this.req.open('POST', this.server_address, true);
	this.req.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	this.req.onreadystatechange = this.catch_send.bind(this);
	this.req.send('message=' + this.message + '&request_code=' + encodeURIComponent(this.request_code) + ((this.extra !== null) ? ('&' + this.extra) : ''));
	this.abort_timeout=setTimeout(this.abort.bind(this), 10000);
}

Request.prototype.queue=function()
{
	Request.queue.push(this);
}

Request.prototype.add_call=function($call)
{
	this.calls.push($call);
}

Request.prototype.add_abort_call=function($call)
{
	this.abort_calls.push($call);
}

Request.prototype.catch_send=function()
{
	try // https://bugzilla.mozilla.org/show_bug.cgi?id=238559
	{
		if (this.req.readyState!=4) return;
		clearTimeout(this.abort_timeout);
		if (this.req.status!=200) this.abort();
		else
		{
			this.response = this.req.responseXML.documentElement;
			this.result=this.response.getElementsByTagName('result')[0];
			this.result_method=this.response.getElementsByTagName('method')[0].firstChild.data;
			this.result_code=this.response.getElementsByTagName('request_code')[0].firstChild.data;
			this.finished=true;
			this.make_calls();
		}
	}
	catch(e) {};
	this.resolved();
}

Request.prototype.make_calls=function($pool)
{
	if (!this.finished) return;
	if (!$pool) $pool=this.calls;
	
	for (var $x in $pool)
	{
		this.make_call($pool[$x]);
	}
}

Request.prototype.make_abort_calls=function()
{
	this.make_calls(this.abort_calls);
}

Request.prototype.make_call=function($call)
{
	if (!this.finished) return;
	$call(this.result, this.result_method, this.result_code);
}

Request.prototype.aborted=false;
Request.prototype.abort=function()
{
	clearTimeout(this.abort_timeout);
	this.req.abort();
	this.aborted=true;
	this.make_abort_calls();
	this.resolved();
}

Request.prototype.resolved=function()
{
	Request.current=null;
	if (Request.queue.length==0) return;
	$next_request=Request.queue.shift();
	$next_request.execute();
}