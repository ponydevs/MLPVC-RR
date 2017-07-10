<?php

namespace App\Controllers;
use App\CoreUtils;
use App\Permission;

class UsersController extends Controller {
	public $do = 'users';

	public function list(){
		if (!Permission::sufficient('staff'))
			CoreUtils::notFound();

		CoreUtils::loadPage([
			'title' => 'Users',
			'do-css'
		], $this);
	}
}
