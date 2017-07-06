<?php

namespace App\Models;

use ActiveRecord\Model;
use App\DeviantArt;
use App\Exceptions\CURLRequestException;

/**
 * @property int $cmid
 * @property int $ponyid
 * @property int $favme_rotation
 * @property string $facing
 * @property string $favme
 * @property string $preview
 * @property string $preview_src
 */
class Cutiemark extends Model {
	/** @return string|null */
	function getPreviewURL():?string {
		try {
			return $this->preview ?? DeviantArt::getCachedDeviation($this->favme)->preview ?? null;
		}
		catch (CURLRequestException $e){
			error_log('Failed to get preview URL: '.$e->getMessage()."\nStack trace:\n".$e->getTraceAsString());
			return null;
		}
	}
}
