<?php

$__dir = rtrim(__DIR__, '\/').DIRECTORY_SEPARATOR;
$__autoload = $__dir.'../../vendor/autoload.php';
if (!file_exists($__autoload))
	die('Autoload file missing - did you run `composer install`?');
require $__autoload;
unset($__autoload);
require $__dir.'../constants.php';
require $__dir.'activerecord.php';
unset($__dir);
