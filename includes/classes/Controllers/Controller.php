<?php

namespace App\Controllers;

use App\Users;

abstract class Controller {
	/** @var array */
	protected $url;
	/** @var string */
	public $do;
	public function __construct(){
		Users::authenticate();
	}
}
