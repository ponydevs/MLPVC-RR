<?php

namespace App\Controllers;
use App\HTTP;

class EQGController extends Controller {
	public function redirectInt($params){
		HTTP::redirect("/movie/{$params['id']}");
	}

	public function redirectStr($params){
		HTTP::redirect("/movie/equestria-girls-{$params['id']}");
	}
}
