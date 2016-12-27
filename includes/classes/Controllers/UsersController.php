<?php

namespace App\Controllers;
use App\CoreUtils;
use App\HTTP;
use App\Permission;
use App\Users;

class UsersController extends Controller {
	public $do = 'users';

	function list(){
		if (!Permission::sufficient('staff'))
			CoreUtils::notFound();

		CoreUtils::loadPage(array(
			'title' => 'Users',
			'do-css'
		), $this);
	}
}
