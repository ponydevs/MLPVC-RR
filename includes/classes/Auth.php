<?php

namespace App;

use App\Models\Session;
use App\Models\User;

/**
 * This class provides global access to some site-wide variables.
 * It's much more straight-forward and IDE friendly than the old approach using `global $var`
 */
class Auth {
	/** @var User|null Currently authenticated user (or null if guest) */
	static $user = null;

	/** @var Session|null Current session (or null if guest) */
	static $session = null;

	/** @var bool True if signed in, false if guest */
	static $signed_in = false;
}
