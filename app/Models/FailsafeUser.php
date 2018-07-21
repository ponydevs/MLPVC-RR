<?php

namespace App\Models;

/**
 * @inheritdoc
 */
class FailsafeUser extends User {
	public static $connection = 'failsafe';

	public $id, $name, $role, $avatar_url;
}
