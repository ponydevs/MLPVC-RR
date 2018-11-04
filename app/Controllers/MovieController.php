<?php

namespace App\Controllers;
use App\CoreUtils;
use App\DB;
use App\Models\Show;
use App\ShowHelper;

class MovieController extends Controller {
	public function view($params){
		ShowHelper::loadPage(Show::find_by_season_and_episode(0, $params['id']));
	}
}
