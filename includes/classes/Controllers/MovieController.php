<?php

namespace App\Controllers;
use App\CoreUtils;
use App\Models\Episode;
use App\Episodes;

class MovieController extends Controller {
	function pageID($params){
		global $Database;

		$Database->where('season', 0)->where('episode', $params['id']);

		$this->_page();
	}
	function pageTitle($params){
		global $Database;

		$data = trim(strtolower($params['title']),' -');
		$Database->where("regexp_replace(regexp_replace(lower(\"title\"), '[^a-z]', '-', 'g'), '-{2,}', '-', 'g') = '$data'");

		$this->_page();
	}
	private function _page(){
		global $Database;

		/** @var $CurrtentEpisode Episode */
		$CurrtentEpisode = $Database->getOne('episodes');
		if (empty($CurrtentEpisode))
			CoreUtils::notFound();

		Episodes::loadPage($CurrtentEpisode);
	}
}
