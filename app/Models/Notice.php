<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Time;

/**
 * @property string   id
 * @property string   message_html
 * @property string   type
 * @property int      posted_by
 * @property DateTime created_at
 * @property DateTime updated_at
 * @property DateTime hide_after
 * @method static Notice|Notice[] find(...$args)
 */
class Notice extends NSModel {
  const VALID_TYPES = [
    'info' => 'Informational (blue)',
    'success' => 'Success (green)',
    'fail' => 'Failure (red)',
    'warn' => 'Warning (orange)',
    'caution' => 'Caution (yellow)',
  ];

  /**
   * @return Notice[]
   */
  public static function list() {
    return self::find('all', [
      'conditions' => 'hide_after > now()',
    ]);
  }

  public function getMessage(): string {
    $message = $this->message_html;
    $message = preg_replace_callback('~@ts\(([^)]+)\)~', function($match){
      $ts = strtotime($match[1]);
      return sprintf(
        '<span class="dynt-el">%s</span> (<time datetime="%s">%s</time>)',
        Time::format($ts, Time::FORMAT_READABLE),
        $match[1],
        Time::format($ts, Time::FORMAT_FULL),
      );
    },$message);
    return $message;
  }
}
