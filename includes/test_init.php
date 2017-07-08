<?php

$_dir = rtrim(__DIR__, '\/').DIRECTORY_SEPARATOR;
$__autoload = $_dir.'../vendor/autoload.php';
if (!file_exists($__autoload))
	die('Autoload file missing - did you run `composer install`?');
require $__autoload;
unset($__autoload);
require $_dir.'constants.php';
require $_dir.'activerecord_init.php';
