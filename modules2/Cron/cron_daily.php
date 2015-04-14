<?
// впоследствии это будет модуль, к которому настройками подключаются регулярные задачи других модулей.
// запускается каждый день в 0:10
include('cron_def.php');

include($path.'/modules2/AdoptsGame/Missions/cron_mission_daily.php');
?>