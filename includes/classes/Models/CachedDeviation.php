<?php

namespace App\Models;

use App\CoreUtils;

class CachedDeviation extends AbstractFillable {
	/** @var string */
	public
		$provider,
		$id,
		$title,
		$author,
		$preview,
		$fullsize,
		$updated_on,
		$type;

	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);
	}

	public function toLinkWithPreview(){
		$stitle = CoreUtils::escapeHTML($this->title);
		return "<a class='deviation-link with-preview' href='http://{$this->provider}/{$this->id}'><img src='{$this->preview}' alt='$stitle'><span>$stitle</span></a>";
	}
}
