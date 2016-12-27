<?php

namespace App\Controllers;
use App\CoreUtils;

class ComponentsController extends Controller {
	public $do = 'components';

	function index(){
		CoreUtils::loadPage(array(
			'title' => 'Components',
			'no-robots',
		), $this);
	}
}
