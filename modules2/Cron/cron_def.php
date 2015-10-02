<?
// впоследствии это будет модуль, к которому настройками подключаютс¤ регул¤рные задачи других модулей.
if (php_sapi_name()==='cli')
{
	$_SERVER['DOCUMENT_ROOT']='/var/www/htdocs2';
	$_SERVER['SERVER_NAME']='pokeliga.com';
	$path=$_SERVER['DOCUMENT_ROOT'].'/entlink';
}
else $path='../..';

include($path.'/entlink2_def.php');

if ((!$debug) && (php_sapi_name()!=='cli') ) exit;
const CRON=true;
ini_set('display_errors', 1);

$pool=EntityPool::default_pool(EntityPool::MODE_OPERATION);