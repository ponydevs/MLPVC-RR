<?php
if (class_exists('Kint')){
  Kint::$app_root_dirs[PROJPATH] = 'PROJPATH';
  Kint::$app_root_dirs[CONFPATH] = 'CONFPATH';
  Kint::$app_root_dirs[FSPATH] = 'FSPATH';
  Kint::$app_root_dirs[APPATH] = 'APPATH';
  Kint::$enabled_mode = Kint::MODE_PLAIN;
  Kint::$mode_default = Kint::MODE_PLAIN;
  Kint::$aliases[] = 'sd';
  function sd(...$args) {
    Kint::dump(...$args);
    exit;
  }
}
