<?php

namespace App\Models\Logs;

/**
 * @property string $target_id
 * @property string $reason
 * @property User   $target
 */
class Banish extends AbstractEntryType {
	static $table_name = 'log__banish';

	static $belongs_to = [
		['target', 'class' => '\App\Models\User'],
	];
}
