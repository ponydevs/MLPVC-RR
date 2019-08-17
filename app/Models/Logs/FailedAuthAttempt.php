<?php

namespace App\Models\Logs;

use App\CoreUtils;
use App\DB;
use App\Logs;
use App\Time;

/**
 * @inheritdoc
 * @property string $user_agent
 */
class FailedAuthAttempt extends AbstractEntryType {
  public static $table_name = 'log__failed_auth_attempts';

  /**
   * Returns true if blocking should not occur
   */
  public static function canAuthenticate():bool {
    // Get all failed attempts in the last 10 minutes
    $ip = $_SERVER['REMOTE_ADDR'];
    $last = 5;
    /** @var $failedAttempts Log[] */
    $failedAttempts = DB::$instance->setModel('Logs\Log')->query(
      'SELECT l.* FROM log__failed_auth_attempts lf
			LEFT JOIN log l ON l.refid = lf.entryid
			WHERE l.ip = ?
			ORDER BY l.timestamp DESC
			LIMIT ?', [$ip, $last]);

    // If none, let it go
    if (empty($failedAttempts) || \count($failedAttempts) < 5)
      return true;

    // Otherwise calculate average distance between failed login attempts
    $total_dist = CoreUtils::tsDiff($failedAttempts[0]->timestamp);
    $cnt = \count($failedAttempts);
    for ($i = 1; $i < $cnt; $i++)
      $total_dist += $failedAttempts[$i - 1]->timestamp->getTimestamp() - $failedAttempts[$i]->timestamp->getTimestamp();
    $avg = $total_dist / $cnt;
    $threshold = Time::IN_SECONDS['minute'] * 3;
    // Allow login if average time between attempts is above 5 minutes
    $allow = $avg > $threshold;

    if (!$allow)
      CoreUtils::error_log("Blocked login attempt from $ip due to the average time between the last $last login attempts ({$avg}s) falling below the {$threshold}s threshold");

    return $allow;
  }

  public static function record() {
    Logs::logAction('failed_auth_attempts', [
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);
  }
}
