<?php

namespace App\Models\Logs;

/**
 * @property string $target_id
 * @property string $reason
 * @property User   $target
 */
class Banish extends AbstractEntryType {
	public static $table_name = 'log__banish';

	public static $belongs_to = [
		['target', 'class' => '\App\Models\User'],
	];
}
