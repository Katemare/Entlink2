<?
namespace
{
function load_debug_concern($dir, $base)
{
	$class='Logger_'.$base;
	if (trait_exists($class)) return;
	class_alias('\Pokeliga\Entlink\Logger', 'Logger_'.$base);
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
}

namespace Pokeliga\Entlink
{
trait Logger
{
	public function log ($msg_id, $details=[])
	{
	}
}
}
?>