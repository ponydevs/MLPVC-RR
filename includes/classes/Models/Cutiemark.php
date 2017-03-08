<?php

namespace App\Models;
use App\DeviantArt;
use App\Exceptions\CURLRequestException;

class Cutiemark extends AbstractFillable {
	/** @var int */
	public $cmid, $ponyid;
	/** @var string */
	public $facing, $favme, $preview, $preview_src;
	/** @var int */
	public $favme_rotation;

	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);
	}

	/**
	 * @return string|null
	 */
	function getPreviewURL(){
		try {
			return $this->preview ?? DeviantArt::getCachedDeviation($this->favme)['preview'] ?? null;
		}
		catch (CURLRequestException $e){
			error_log('Failed to get preview URL: '.$e->getMessage()."\nStack trace:\n".$e->getTraceAsString());
			return null;
		}
	}
}
