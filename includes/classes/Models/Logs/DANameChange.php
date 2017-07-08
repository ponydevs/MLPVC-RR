<?php

namespace App\Models\Logs;

/**
 * @inheritdoc
 * @property string $old
 * @property string $new
 * @property string $user_id
 * @property User   $user
 * @method static DANameChange|DANameChange[] find_by_old(string $username)
 */
class DANameChange extends AbstractEntryType {
	public static $table_name = 'log__da_namechange';

	public static $belongs_to = [
		['user', 'class' => '\App\Models\User', 'foreign_key' => 'user_id'],
	];
}
