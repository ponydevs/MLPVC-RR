<?php

# GDPR

use App\CoreUtils;
use App\Models\Log;

require __DIR__.'/../config/init/minimal.php';

$done = Log::update_all(array(
  'set' => ['ip' => GDPR_IP_PLACEHOLDER],
  'conditions' => "now() - created_at > INTERVAL '3 MONTH'",
));

$message = CoreUtils::makePlural('log entry', $done, PREPEND_NUMBER).' updated';
if (posix_isatty(STDIN))
  echo basename(__FILE__).": $message\n";
else CoreUtils::error_log($message);
