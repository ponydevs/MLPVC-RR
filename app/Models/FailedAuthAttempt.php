<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\CoreUtils;
use App\DB;
use App\Time;
use function count;

/**
 * @property int      $id
 * @property string   $ip
 * @property string   $user_agent
 * @property DateTime $created_at
 * @property DateTime $updated_at
 */
class FailedAuthAttempt extends NSModel {
  /**
   * Returns true if blocking should not occur
   */
  public static function canAuthenticate():bool {
    // Get all failed attempts in the last 10 minutes
    $ip = $_SERVER['REMOTE_ADDR'];
    $last = 5;
    /** @var $failedAttempts self[] */
    $failedAttempts = DB::$instance->where('ip', $ip)->orderBy('created_at', 'desc')->get('failed_auth_attempts', $last);

    // If none, let it go
    if (empty($failedAttempts) || count($failedAttempts) < 5)
      return true;

    // Otherwise calculate average distance between failed login attempts
    $total_dist = CoreUtils::tsDiff($failedAttempts[0]->created_at);
    $cnt = count($failedAttempts);
    for ($i = 1; $i < $cnt; $i++)
      $total_dist += $failedAttempts[$i - 1]->created_at->getTimestamp() - $failedAttempts[$i]->created_at->getTimestamp();
    $avg = $total_dist / $cnt;
    $threshold = Time::IN_SECONDS['minute'] * 3;
    // Allow login if average time between attempts is above 5 minutes
    $allow = $avg > $threshold;

    if (!$allow)
      CoreUtils::logError("Blocked login attempt from $ip due to the average time between the last $last login attempts ({$avg}s) falling below the {$threshold}s threshold");

    return $allow;
  }

  public static function record() {
    self::create([
      'ip' => $_SERVER['REMOTE_ADDR'],
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);
  }
}
