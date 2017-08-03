<?php

namespace App\Models\Logs;

use App\RegExp;
use App\Models\NSModel;

/**
 * @property int    $entryid
 * @property Log    $log     (Via magic method)
 * @property string $type    (Via magic method)
 */
abstract class AbstractEntryType extends NSModel {
	public static $table_name;

	public static $primary_key = 'entryid';

	public function get_log():?Log {
		return Log::find_by_reftype_and_refid($this->type, $this->entryid);
	}

	public function get_type():string {
		return preg_replace(new RegExp('^log__'),'',static::$table_name);
	}
}
