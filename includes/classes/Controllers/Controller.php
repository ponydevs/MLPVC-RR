<?php

namespace App\Controllers;

use App\Users;

abstract class Controller {
	protected static $auth = true;

	public function __construct(){
		if (static::$auth)
			Users::authenticate();
	}
}
