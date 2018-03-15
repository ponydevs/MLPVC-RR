<?php

$_dir = rtrim(__DIR__, '\/').DIRECTORY_SEPARATOR;
require $_dir.'../../vendor/autoload.php';
require $_dir.'../conf.php';

\App\RedisHelper::del(array_slice($argv, 1));
