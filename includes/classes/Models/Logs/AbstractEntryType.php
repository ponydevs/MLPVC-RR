<?php

namespace App\Models\Logs;

use ActiveRecord\Model;
use App\RegExp;

/**
 * @property int $entryid
 * @property Log $log
 */
abstract class AbstractEntryType extends Model {
	static $table_name;

	static $primary_key = 'entryid';

	function get_log():?Log {
		$reftype = preg_replace(new RegExp('^log__'),'',static::$table_name);
		return Log::find_by_reftype_and_refid($reftype, $this->entryid);
	}
}
