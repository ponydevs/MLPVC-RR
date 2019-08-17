<?php

require __DIR__.'/config/init/path-constants.php';
require __DIR__.'/config/init/env.php';

return [
  'paths' => [
    'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
    'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
  ],
  'environments' => [
    'default_database' => 'local',
    'local' => [
      'adapter' => 'pgsql',
      'host' => $_ENV['DB_HOST'],
      'user' => $_ENV['DB_USER'],
      'pass' => $_ENV['DB_PASS'],
      'name' => $_ENV['DB_NAME'],
    ],
  ],
];
