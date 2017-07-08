<?php

namespace App\Controllers;
use App\CoreUtils;
use App\DB;
use App\Models\Episode;
use App\Episodes;

class MovieController extends Controller {
	public function pageID($params){
		DB::where('season', 0)->where('episode', $params['id']);

		$this->_page();
	}
	public function pageTitle($params){
		$data = trim(strtolower($params['title']),' -');
		DB::where("regexp_replace(regexp_replace(lower(\"title\"), '[^a-z]', '-', 'g'), '-{2,}', '-', 'g') = '$data'");

		$this->_page();
	}
	private function _page(){
		/** @var $CurrtentEpisode Episode */
		$CurrtentEpisode = DB::getOne('episodes');
		if (empty($CurrtentEpisode))
			CoreUtils::notFound();

		Episodes::loadPage($CurrtentEpisode);
	}
}
