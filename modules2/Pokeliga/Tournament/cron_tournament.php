<?

// Запускается ежечасно в XX:30
//if (php_sapi_name()!=='cli') exit;
ini_set('display_errors', 1);

if (php_sapi_name()==='cli')
{
	$_SERVER['DOCUMENT_ROOT']='/var/www/htdocs2';
	$_SERVER['SERVER_NAME']='pokeliga.com';
}
$path='../../..';
include($path.'/entlink2_def.php');

$pool=EntityPool::default_pool(EntityPool::MODE_OPERATION);

// турниры, нуждающиеся в обновлении: все, не имеющие статуса STATE_AWARDED и также не находящиеся на паузе. на паузу турнир может поставить автоматическая обработка, если требуется контроль пользователя.

$query=
[
	'action'=>'select',
	'table'=>'tournaments',
	'where'=>
	[
		['field'=>'state', 'op'=>'!=', 'value'=>Tournament::STATE_AWARDED],
		['field'=>'paused_at', 'value'=>null]
	]
];
$ticket=new RequestTicket('request_single', [$query]);
$select=Select_by_ticket::from_ticket($ticket, ['id_group'=>'Tournament']);
$select->complete();
debug_dump();

$tournaments=$select->value->content->values;
if (empty($tournaments)) exit;

$tasks=[];
foreach ($tournaments as $tournament)
{
	$task=$tournament->task_request('cron');
	vdump($task); die('MEOW');
}

if (empty($tasks)) exit;
?>