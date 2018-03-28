<?php

$_dir = rtrim(__DIR__, '\/').DIRECTORY_SEPARATOR;
require $_dir.'../../vendor/autoload.php';
require $_dir.'../conf.php';

$num = \App\RedisHelper::del(array_slice($argv, 1)) ?? 0;
echo basename(__FILE__).": $num key".($num===1?'':'s')." deleted successfully\n";
