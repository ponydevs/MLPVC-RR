<?php

use App\UsefulLogger as Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

function monolog_setup(){
	global $logger;
	$formatter = new LineFormatter(LineFormatter::SIMPLE_FORMAT, LineFormatter::SIMPLE_DATE);
	$formatter->includeStacktraces();

	if (!defined('LOG_PATH'))
		throw new RuntimeException('The LOG_PATH constant is not defined, please add it to your conf.php file');

	$stream = new StreamHandler(PROJPATH.'logs/'.LOG_PATH);
	$stream->setFormatter($formatter);

	$logger = new Logger('logger');
	$logger->pushHandler($stream);

	$handler = new \App\GracefulErrorHandler($logger);
	$handler->registerErrorHandler([], false);
	$handler->registerExceptionHandler();
	$handler->registerFatalHandler();
}
if (!defined('DISABLE_MONOLOG'))
	monolog_setup();
else ini_set('error_log', PROJPATH.'logs/'.LOG_PATH);
