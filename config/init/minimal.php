<?php

$autoload = __DIR__.'/../../vendor/autoload.php';
if (!file_exists($autoload))
  die('Autoload file missing - did you run `composer install`?');
require $autoload;
unset($autoload);
require __DIR__.'/../constants.php';
require __DIR__.'/activerecord.php';
require __DIR__.'/twig.php';
