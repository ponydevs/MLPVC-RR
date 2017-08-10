<?php

require __DIR__.'/includes/conf.php';

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
			'user' => DB_USER,
			'pass' => DB_PASS,
			'name' => DB_NAME,
		],
	],
];
