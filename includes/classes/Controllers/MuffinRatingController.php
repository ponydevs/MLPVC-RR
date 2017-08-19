<?php

namespace App\Controllers;
use App\File;
use App\RegExp;

class MuffinRatingController {
	public $do = 'muffin-rating';

	public function image(){
		$ScorePercent = 100;
		if (isset($_GET['w']) && preg_match(new RegExp('^(\d|[1-9]\d|100)$'), $_GET['w']))
			$ScorePercent = intval($_GET['w'], 10);
		$RatingFile = File::get(APPATH.'img/muffin-rating.svg');
		header('Content-Type: image/svg+xml');
		die(str_replace("width='100'", "width='$ScorePercent'", $RatingFile));
	}
}
