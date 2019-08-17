<?php

$loader = new Twig\Loader\FilesystemLoader(PROJPATH.'templates', PROJPATH);
\App\Twig::init($loader, array(
  'cache' => FSPATH.'tmp/twig_cache',
  'auto_reload' => true,
  'autoescape' => false,
  'strict_variables' => true,
));
