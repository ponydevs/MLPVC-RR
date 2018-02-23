<?php

$_dir = rtrim(__DIR__, '\/').DIRECTORY_SEPARATOR;
require $_dir.'../vendor/autoload.php';
require $_dir.'../includes/conf.php';

\App\RedisHelper::del('commit_id', 'commit_time');
