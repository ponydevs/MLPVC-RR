<?php

$loader = new Twig_Loader_Filesystem(PROJPATH.'templates', PROJPATH);
\App\Twig::init($loader, array(
    'cache' => FSPATH.'tmp/twig_cache',
    'auto_reload' => true,
    'autoescape' => false,
));
