<?
include('entlink2_def.php');
$page=Router()->route();
$page->allow_direct_input();
$page->get_processor_task()->complete();
?>