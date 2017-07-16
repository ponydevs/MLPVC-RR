<?php

namespace App\Controllers;
use App\CoreUtils;
use App\DB;
use App\Models\Episode;
use App\Episodes;

class MovieController extends Controller {
	public function pageID($params){
		DB::$instance->where('season', 0)->where('episode', $params['id']);

		$this->_page();
	}
	public function pageTitle($params){
		$data = strtolower(trim($params['title'], ' -'));
		DB::$instance->where("regexp_replace(regexp_replace(lower(\"title\"), '[^a-z]', '-', 'g'), '-{2,}', '-', 'g') = '$data'");

		$this->_page();
	}
	private function _page(){
		/** @var $CurrtentEpisode Episode */
		$CurrtentEpisode = DB::$instance->getOne('episodes');
		if (empty($CurrtentEpisode))
			CoreUtils::notFound();

		Episodes::loadPage($CurrtentEpisode);
	}
}
