<?php

use App\PennyErrorHandler;
use App\UsefulLogger as Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;

function monolog_setup() {
  global $logger;
  $formatter = new LineFormatter(LineFormatter::SIMPLE_FORMAT, LineFormatter::SIMPLE_DATE);
  $formatter->includeStacktraces();

  if (empty($_ENV['LOG_PATH']))
    throw new RuntimeException('The LOG_PATH environment variable is not defined, please add it to your .env file');

  $stream_handler = new StreamHandler(FULL_LOG_PATH);
  $stream_handler->setFormatter($formatter);

  $logger = new Logger('logger');
  $logger->pushHandler($stream_handler);

  if (!empty($_ENV['DISCORD_LOG_WEBHOOK_URL'])){
    $discord_handler = new DiscordHandler\DiscordHandler(
      $_ENV['DISCORD_LOG_WEBHOOK_URL'],
      null,
      null,
      Logger::WARNING
    );

    $discord_handler->getConfig()
      ->setEmbedMode(true)
      ->setDatetimeFormat('c');
    $logger->pushHandler($discord_handler);
  }

  if ($_ENV['PRODUCTION'] === 'true') {
    PennyErrorHandler::register($logger);
  }
}

monolog_setup();
