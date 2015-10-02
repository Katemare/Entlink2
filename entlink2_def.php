<?
//ini_set('display_errors', '1');
//ini_set('xdebug.max_nesting_level', 200);
ini_set('default_charet', 'UTF8');
//ini_set('mbstring.internal_encoding', 'UTF-8');
error_reporting(E_ALL ^ E_DEPRECATED);
define('ENTLINK', 1);

function string_instanceof($class1, $class2)
{
	if ($class1===$class2) return true;
	return is_subclass_of($class1, $class2);
}

// ------------------------------
$conf_file='conf2.php';
session_start();
$entlink=include_once($conf_file);
include_once('Engine.php');

$debug=!empty($entlink['development']);
if ($debug) include_once('debug_development.php'); else include_once('debug_production.php');

$engine=Engine();
$engine->setup($entlink);

// COMP - старая авторизация
include_once(Engine()->server_address('compatible2/users/getuser.php'));

$EC=false;
if ($US_Login==='EvilCat')
{
	ini_set('display_errors', '1');
	$debug=1;
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