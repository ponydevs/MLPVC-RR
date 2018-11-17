<?php

use App\CoreUtils;
use App\UsefulLogger as Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

function monolog_setup(){
	global $logger;
	$formatter = new LineFormatter(LineFormatter::SIMPLE_FORMAT, LineFormatter::SIMPLE_DATE);
	$formatter->includeStacktraces();

	if (empty(CoreUtils::env('LOG_PATH')))
		throw new RuntimeException('The LOG_PATH environment variable is not defined, please add it to your .env file');

	$stream = new StreamHandler(FULL_LOG_PATH);
	$stream->setFormatter($formatter);

	$logger = new Logger('logger');
	$logger->pushHandler($stream);

	$handler = new \App\GracefulErrorHandler($logger);
	$handler->registerErrorHandler([], false);
	$handler->registerExceptionHandler();
	$handler->registerFatalHandler();
}
if (CoreUtils::env('DISABLE_MONOLOG') !== 'true')
	monolog_setup();
else ini_set('error_log', FULL_LOG_PATH);
