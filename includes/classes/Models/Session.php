<?php

namespace App\Models;

class Session extends AbstractFillable {
	/** @var int */
	public
		$id;
	/** @var string */
	public
		$user,
		$platform,
		$browser_name,
		$browser_ver,
		$user_agent,
		$token,
		$access,
		$refresh,
		$expires,
		$created,
		$lastvisit,
		$scope;

	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);
	}
}
