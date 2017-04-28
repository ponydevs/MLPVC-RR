<?php

namespace App;

use App\Models\Session;
use App\Models\User;

class Auth {
	/** @var User|null */
	static $user = null;

	/** @var Session|null */
	static $session = null;

	/** @var bool */
	static $signed_in = false;
}
