<?php

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../config/constants.php';

use App\CoreUtils;

$prefix = basename(__FILE__).':';
$keys = array_slice($argv, 1);
if (empty($keys)){
  echo "$prefix Please specify the keys to clear as arguments\n";
  exit;
}
$num = \App\RedisHelper::del($keys) ?? 0;
echo "$prefix ".CoreUtils::makePlural('key', $num, PREPEND_NUMBER)." deleted successfully\n";

if (in_array('commit_info', $keys, true)){
  require __DIR__.'/../config/init/twig.php';

  try {
    CoreUtils::socketEvent('update', ['git_info' => CoreUtils::getFooterGitInfo(NOWRAP, true)], WS_LOCAL_ORIGIN);
    echo "$prefix Sent update WS event\n";
  }
  catch (Throwable $e){
    echo "$prefix Could not send update WS event: {$e->getMessage()} ({$e->getFile()}:{$e->getLine()})\nStack trace:\n{$e->getTraceAsString()}\n";
  }
}
