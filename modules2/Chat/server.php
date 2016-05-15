<?
if (php_sapi_name()==='cli')
{
	// $_SERVER['DOCUMENT_ROOT']='/var/www/htdocs2';
	$_SERVER['DOCUMENT_ROOT']='C:\winginx\home\igo.test\public_html';
	$_SERVER['SERVER_NAME']='pokeliga.com';
	$path=$_SERVER['DOCUMENT_ROOT'].'/entlink2';
}
include($path.'/entlink2_def.php');
if (!$debug and php_sapi_name()!=='cli') die('No way.');
Engine()->service=true;

ini_set('display_errors', 1);
set_time_limit(0);

function Server()
{
	global $server;
	return $server;
}

// $port=$argv[2];
$port=9300;
$server=new ChatServer($port);
$server->set_anon_handle_manager('PokemonHandleManager');
echo "OK!\n";
$server->start();
?>