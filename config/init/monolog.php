<?php

use App\CoreUtils;
use App\GracefulErrorHandler;
use App\UsefulLogger as Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;

function monolog_setup() {
  global $logger;
  $formatter = new LineFormatter(LineFormatter::SIMPLE_FORMAT, LineFormatter::SIMPLE_DATE);
  $formatter->includeStacktraces();

  if (empty(CoreUtils::env('LOG_PATH')))
    throw new RuntimeException('The LOG_PATH environment variable is not defined, please add it to your .env file');

  $stream = new StreamHandler(FULL_LOG_PATH);
  $stream->setFormatter($formatter);

  $logger = new Logger('logger');
  $logger->pushHandler($stream);

  GracefulErrorHandler::register($logger);
}

monolog_setup();
