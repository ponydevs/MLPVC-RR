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
	public function __toString():string {
		return "<div class='notice {$this->type}'>{$this->message_html}</div>";
	}
}
