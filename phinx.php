<?php

require __DIR__.'/includes/test_init.php';

$pdo = \Activerecord\Connection::instance()->connection;

return [
	'paths' => [
		'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
		'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
	],
	'environments' => [
		'default_database' => 'local',
		'local' => [
			'adapter' => 'pgsql',
			'host' => DB_HOST,
			'name' => 'mlpvc-rr',
			'user' => DB_USER,
			'pass' => DB_PASS,
		],
	],
];
