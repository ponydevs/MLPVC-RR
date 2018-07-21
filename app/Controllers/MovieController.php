<?php

namespace App\Controllers;
use App\CoreUtils;
use App\DB;
use App\Models\Episode;
use App\Episodes;

class MovieController extends Controller {
	public function view($params){
		Episodes::loadPage(Episode::find_by_season_and_episode(0, $params['id']));
	}
}
