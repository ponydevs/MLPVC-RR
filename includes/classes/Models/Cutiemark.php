<?php

namespace App\Models;

use App\DeviantArt;
use App\Exceptions\CURLRequestException;

/**
 * @property int        $id
 * @property int        $appearance_id
 * @property int        $favme_rotation
 * @property string     $facing
 * @property string     $favme
 * @property string     $preview
 * @property string     $preview_src
 * @property Appearance $appearance
 * @method static Cutiemark[] find_by_sql($sql, $data = null)
 */
class Cutiemark extends NSModel {
	static $table_name = 'cutiemarks';

	public static $belongs_to = [
		['appearance']
	];

	/** @return string|null */
	public function getPreviewURL():?string {
		try {
			return $this->preview ?? DeviantArt::getCachedDeviation($this->favme)->preview ?? null;
		}
		catch (CURLRequestException $e){
			error_log('Failed to get preview URL: '.$e->getMessage()."\nStack trace:\n".$e->getTraceAsString());
			return null;
		}
	}
}
