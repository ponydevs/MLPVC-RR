<?php

namespace App\Controllers;
use App\CoreUtils;
use App\Response;

class FooterController extends Controller {
	function git(){
		Response::done(['footer' => CoreUtils::getFooterGitInfo()]);
	}
}
