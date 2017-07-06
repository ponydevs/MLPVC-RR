<?php

namespace App\Models\Logs;

/**
 * @inheritdoc
 * @property string $old
 * @property string $new
 * @property string $id   ID of the user whose name changed
 * @property User   $user
 */
class DANameChange extends AbstractEntryType {
	static $table_name = 'log__da_namechange';

	static $belongs_to = [
		['user', 'class' => '\App\Models\User', 'foreign_key' => 'id'],
	];
}
