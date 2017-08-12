<?php

namespace App\Controllers;

use App\Users;

abstract class Controller {
	public function __construct(){
		Users::authenticate();
	}
}
