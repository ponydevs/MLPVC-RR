<?php

namespace App\Models;

use ActiveRecord\Model;

/**
 * @property int    $id
 * @property string $user_id
 * @property string $platform
 * @property string $browser_name
 * @property string $browser_ver
 * @property string $user_agent
 * @property string $token
 * @property string $access
 * @property string $refresh
 * @property string $expires
 * @property string $created
 * @property string $lastvisit
 * @property string $scope
 * @property User   $user
 * @method static Session find_by_token(string $token)
 * @method static Session find_by_refresh(string $code)
 */
class Session extends Model {
	static $belongs_to = [
		['user', 'readonly' => true],
	];
}

