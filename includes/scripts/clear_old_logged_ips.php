<?php

# GDPR

$_dir = rtrim(__DIR__, '\/').DIRECTORY_SEPARATOR;
require $_dir.'../init/minimal.php';

$done = \App\Models\Logs\Log::update_all(array(
	'set' => ['ip' => GDPR_IP_PLACEHOLDER],
    'conditions' => "now() - timestamp > INTERVAL '3 MONTH'",
));

$message = \App\CoreUtils::makePlural('log entry', $done, PREPEND_NUMBER).' updated';
if (posix_isatty(STDIN))
	echo basename(__FILE__).": $message\n";
else \App\CoreUtils::error_log($message);
