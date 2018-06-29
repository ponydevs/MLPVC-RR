<?php

$loader = new Twig_Loader_Filesystem(PROJPATH.'templates', PROJPATH);
\App\TwigHelper::init($loader, array(
    'cache' => FSPATH.'tmp/twig_cache',
    'auto_reload' => true,
    'autoescape' => false,
));
