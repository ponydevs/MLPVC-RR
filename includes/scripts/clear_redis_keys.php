<?php

$_dir = rtrim(__DIR__, '\/').DIRECTORY_SEPARATOR;
require $_dir.'../../vendor/autoload.php';
require $_dir.'../constants.php';

use App\CoreUtils;

$prefix = basename(__FILE__).':';
$keys = array_slice($argv, 1);
$num = \App\RedisHelper::del($keys) ?? 0;
echo "$prefix ".CoreUtils::makePlural('key',$num,PREPEND_NUMBER)." deleted successfully\n";

if (in_array('commit_id', $keys, true)){
	try {
		CoreUtils::socketEvent('update', ['git_info' => CoreUtils::getFooterGitInfo(NOWRAP, true)], WS_LOCAL_ORIGIN);
		echo "$prefix Sent update WS event\n";
	}
	catch (Throwable $e){
		echo "$prefix Could not send update WS event: {$e->getMessage()} ({$e->getFile()}:{$e->getLine()})\nStack trace:\n{$e->getTraceAsString()}\n";
	}
}
