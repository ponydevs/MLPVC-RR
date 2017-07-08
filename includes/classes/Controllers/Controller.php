<?php

namespace App\Controllers;

use App\Users;

abstract class Controller {
	/** @var string */
	public $do;
	public function __construct(){
		Users::authenticate();
	}
}
