<?

function load_debug_concern($module_code, $base)
{
	$class='Logger_'.$base;
	if (trait_exists($class)) return;
	class_alias('Logger', 'Logger_'.$base);
}

function vdump($s)
{
}

function debug ($s, $domain='generic')
{
}

function debug_dump()
{
}

trait Logger
{
	public function log ($msg_id, $details=[])
	{
	}
}

?>