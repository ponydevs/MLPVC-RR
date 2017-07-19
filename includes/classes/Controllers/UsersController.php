<?php

namespace App\Controllers;
use App\CoreUtils;
use App\DB;
use App\Permission;
use App\Models\User;

class UsersController extends Controller {
	public $do = 'users';

	public function list(){
		if (!Permission::sufficient('staff'))
			CoreUtils::notFound();

		$Users = DB::$instance->orderBy('name')->get(User::$table_name);

		CoreUtils::loadPage([
			'title' => 'Users',
			'do-css',
			'import' => [
				'Users' => $Users,
			],
		], $this);
	}
}
