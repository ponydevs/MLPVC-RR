<?php

namespace App\Controllers;
use App\CoreUtils;

class PolyController {
	public $do = 'poly';

	function index(){
		CoreUtils::loadPage(array(
			'title' => 'Poly',
			'js' => array('jquery.ba-throttle-debounce','poly-editor', $this->do),
			// TODO add 'jquery.qtip'
			'css' => array('poly-editor', $this->do),
		));
	}
}
