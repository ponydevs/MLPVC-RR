<?php

namespace App\Models;

interface LinkableInterface {
	/**
	 * Returns the public-facing URL of this model
	 *
	 * @return string
	 */
	function toURL():string;

	/**
	 * Returns an anchor with the public-facing URL and the model's name/label/ID as the text
	 *
	 * @return string
	 */
	function toAnchor():string;
}
