<?
// впоследствии это будет модуль, к которому настройками подключаются регулярные задачи других модулей.
// запускается каждый час в ХХ:05.
include('cron_def.php');

include($path.'/modules2/AdoptsGame/Missions/cron_mission_hourly.php');
?>