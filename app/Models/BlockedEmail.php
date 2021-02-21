<?php

namespace App\Models;

use ActiveRecord\DateTime;

/**
 * @property int      $id
 * @property string   $email
 * @property DateTime $created_at
 * @property DateTime $updated_at
 * @method static BlockedEmail find(...$args)
 * @method static BlockedEmail find_by_email(string $email)
 */
class BlockedEmail extends NSModel {
  public static $table_name = 'blocked_emails';

  public static $after_create = ['deleteVerifications'];

  public static function record(string $email) {
    if (!self::exists(['conditions' => ['email' => $email]])){
      self::create([
        'email' => $email,
      ]);
    }
  }

  public function deleteVerifications() {
    EmailVerification::delete_all(['conditions' => ['email' => $this->email]]);
  }
}
