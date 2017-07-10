<?php

namespace App\Controllers;
use App\CoreUtils;

class ComponentsController extends Controller {
	public $do = 'components';

	public function index(){
		CoreUtils::loadPage([
			'title' => 'Components',
			'no-robots',
		], $this);
	}
}
