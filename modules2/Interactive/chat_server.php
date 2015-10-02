<?php
set_time_limit(0);
$port=$argv[2];
$port=9300;
$chat=new Chat($port);
$chat->start();
?>