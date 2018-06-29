<?php

// Autoload classes \\
require __DIR__.'/init/minimal.php';
require __DIR__.'/init/kint.php';
require __DIR__.'/init/monolog.php';
require __DIR__.'/init/twig.php';

if (defined('CSP_ENABLED') && CSP_ENABLED === true){
	header('Content-Security-Policy: '.CSP_HEADER);
	header('X-Content-Security-Policy: '.CSP_HEADER);
	header('X-WebKit-CSP: '.CSP_HEADER);
}

use App\About;
use App\DB;
use App\PostgresDbWrapper;

// Maintenance mode \\
if (defined('MAINTENANCE_START'))
	\App\TwigHelper::display('error/fatal', [ 'errcause' => 'maintenance' ]);

// Database connection & Required Functionality Checking \\
try {
	$inipath = 'in/to '.php_ini_loaded_file().' then restart '.About::getServerSoftware().' and/or FPM';
	if (About::iniGet('short_open_tag') !== true)
		throw new RuntimeException("Short open tags (&lt;?) are disabled\nUncomment/add the line <strong>short_open_tag=On</strong> $inipath to fix");
}
catch (Exception $e){
	\App\TwigHelper::display('error/fatal', [ 'errcause' => 'libmiss' ]);
}

try {
	$conn = \Activerecord\Connection::instance();
	DB::$instance = PostgresDbWrapper::withConnection(DB_NAME, $conn->connection);
}
catch (Exception $e){
	\App\TwigHelper::display('error/fatal', [ 'errcause' => 'db' ]);
}
