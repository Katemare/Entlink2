<?
// namespace Entlink;
//ini_set('display_errors', '1');
//ini_set('xdebug.max_nesting_level', 200);
ini_set('default_charet', 'UTF8');
ini_set('mbstring.language', 'Russian');
//ini_set('mbstring.internal_encoding', 'UTF-8');
error_reporting(E_ALL ^ E_DEPRECATED);
define('ENTLINK', 1);
date_default_timezone_set('Europe/Moscow');

function mb_ucfirst($text)
{
	return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
}

function mb_lcfirst($text)
{
	return mb_strtolower(mb_substr($text, 0, 1)).mb_substr($text, 1);
}

function string_instanceof($class1, $class2)
{
	if ($class1===$class2) return true;
	return is_subclass_of($class1, $class2);
}

function min_max_int($val, $min, $max)
{
	$val=(int)$val;
	if ($val<$min) return $min;
	if ($val>$max) return $max;
	return $val;
}

function paste_array($arg1, $arg2)
{
	if ($arg1===null) return $arg2;
	if ($arg2===null) return $arg1;
	if ( (!is_array($arg1)) && (!is_array($arg2)) ) return [$arg1, $arg2];
	if ( (is_array($arg1)) && (is_array($arg2)) ) return array_merge($arg1, $arg2);
	if (is_array($arg1)) 
	{
		$arg1[]=$arg2;
		return $arg1;
	}
	if (is_array($arg2))
	{
		array_unshift($arg2, $arg1);
		return $arg2;
	}
	die('SHOULDNT BE HERE');
}

// ------------------------------
$entlink=[];
$conf_file='conf2.php';
session_start();
include_once($conf_file);
$debug=(bool)$entlink['development'];
if (empty($entlink['modules_dir'])) $modules_path='modules';
else $modules_path=$entlink['modules_dir'];

if ($debug) include_once($modules_path.'/debug_development.php'); else include_once($modules_path.'/debug_production.php');

include_once($modules_path.'/Engine.php');
$engine=Engine();
$engine->setup($entlink);
Retriever()->basic_connect(); // COMP: устанавливает связь с БД на уровне, необходимом модулю совместимости

include_once(Engine()->server_address('compatible2/users/getuser.php'));

$EC=false;
if ($US_Login==='EvilCat')
{
	ini_set('display_errors', '1');
	$debug=1;
	//$US_Login='Endo';
	//$USid=36339;
	//$US_Login='Loadgreen';
	//$USid=2039;
	$EC=true;
}

if (!empty($entlink['development']))
{
	$EC=true;
	ini_set('display_errors', '1');
	$debug=1;
	
	$US_Login='EvilCat';
	$USid=1;
	
//	$US_Login='Тесто';
//	$USid=4;
	
// 	$US_Login='Meow!';
//	$USid=3;

//	$US_Login='Katemare';
//	$USid=2;
}
global $pgtitle;
$pgtitle='Игра';
?>