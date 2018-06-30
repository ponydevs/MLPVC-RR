<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\CoreUtils;

/**
 * @property string   id
 * @property string   message_html
 * @property string   type
 * @property string   posted_by
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
	public static function list(){
		return self::find('all', [
			'conditions' => 'hide_after > now()'
		]);
	}
}
