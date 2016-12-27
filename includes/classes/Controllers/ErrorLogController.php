<?php

namespace App\Controllers;
use App\CoreUtils;
use App\Permission;

class ErrorLogController extends Controller {
	function index(){
		if (Permission::insufficient('developer'))
			CoreUtils::notFound();

		header('Content-Type: text/plain; charset=utf-8;');
		readfile(APPATH.'../mlpvc-rr-error.log');
	}
}
