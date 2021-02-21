<?php

# GDPR

require __DIR__.'/../config/init/minimal.php';

use App\CoreUtils;
use App\Models\EmailVerification;
use Monolog\Logger;

// Remove verification entries older than 24 hours

$log_done = EmailVerification::delete_all(array(
  'conditions' => "now() - created_at > INTERVAL '24 HOURS'",
));

$log_message = CoreUtils::makePlural('verification entry', $log_done, PREPEND_NUMBER).' deleted';
if (posix_isatty(STDIN))
  echo basename(__FILE__).": $log_message\n";
else CoreUtils::logError($log_message, Logger::INFO);
