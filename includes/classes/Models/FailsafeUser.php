<?php

namespace App\Models;

/**
 * @inheritdoc
 */
class FailsafeUser extends User {
	public static $connection = 'failsafe';

	public $name, $role, $avatar_url;
}
