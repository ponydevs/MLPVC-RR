<?php

namespace App\Controllers;
use App\HTTP;

class EQGController extends Controller {
	function redirectInt($params){
		HTTP::redirect("/movie/{$params['id']}");
	}

	function redirectStr($params){
		HTTP::redirect("/movie/equestria-girls-{$params['id']}");
	}
}
