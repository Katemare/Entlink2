<?
ini_set('xdebug.var_display_max_depth', 4);
$debug_display=['display', 'generic', 'engine', 'form', 'query', /* 'Data', */ 'Task', 'EntityType'];
//$debug_display=['query'];
$debug_count=array_fill_keys($debug_display, 0);
$debug_log=[];
function debug ($s, $domain='generic')
{
	global $debug, $debug_display, $debug_log, $debug_count;
	if (!$debug) return;
	if (!in_array($domain, $debug_display)) return;
	$debug_count[$domain]++;
	$entry="<small style=\"color:#EEE\">$domain</small> $s<br>";
	$debug_log[]=$entry;
	//echo_anyway($entry);
}

function echo_anyway($s)
{
	$buffer=ob_get_contents();
	ob_end_clean();
	echo $s;
	ob_start();
	echo $buffer;
}

function vdump($v)
{
	$ob_level=ob_get_level();
	if ($ob_level>0)
	{
		$buffer=ob_get_contents();
		ob_end_clean();
	}
	// xdebug_print_function_stack();
	var_dump($v);
	if ($ob_level>0)
	{
		ob_start();
		echo $buffer;
	}
	/*
	if (!is_object($v)) var_dump($v);
	else var_dump($v->intro());
	*/
}

function debug_value ($v)
{
	if ($v===true) $result='TRUE';
	elseif ($v===false) $result='FALSE';
	elseif (is_null($v)) $result='NULL';
	elseif (is_string($v)) $result='"'.$v.'"';
	elseif ( (is_array($v)) || (is_object($v)) )
	{
		ob_start();
		var_dump($v);
		$result=ob_get_contents();
		ob_end_clean();
		//$result=htmlspecialchars($result);
	}
	else $result=$v;
	return $result;
}

function debug_dump()
{
	vdump('debug dump');
	global $debug, $debug_log, $debug_count;
	if (!$debug) return;
	
	foreach ($debug_log as $msg)
	{
		echo $msg;
	}
	
	vdump($debug_count);
	$debug_log=[];
}

function debug_clear()
{
	global $debug, $debug_log, $debug_count;
	if (!$debug) return;
	$debug_log=[];
}

function load_debug_concern($module_code, $base)
{
	include_once(Engine()->module_address($module_code, $base.'_debug.php'));
}

trait Logger
{
	public abstract function log_domain();

	public function debug($s, $domain=null)
	{
		if ($domain===null)
		{
			if (!empty($this->log_domain)) $domain=$this->log_domain();
			else $domain='generic';
		}
		
		debug($s, $domain);
	}
	
	public abstract function log($msg_id, $details=[]);
}

?>