<?php

namespace App\Controllers;

use App\CSRFProtection;
use App\Users;

abstract class Controller {
	protected static $auth = true;

	public function __construct(){
		CSRFProtection::detect();
		if (static::$auth)
			Users::authenticate();
	}
}
