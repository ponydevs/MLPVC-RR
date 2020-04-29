<?php

use App\Twig;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(PROJPATH.'templates', PROJPATH);
Twig::init($loader, array(
  'cache' => FSPATH.'tmp/twig_cache',
  'auto_reload' => true,
  'autoescape' => false,
  'strict_variables' => true,
));
