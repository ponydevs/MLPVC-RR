<?php

use App\RegExp;

$ScorePercent = 100;
if (isset($_GET['w']) && regex_match(new RegExp('^(\d|[1-9]\d|100)$'), $_GET['w']))
	$ScorePercent = intval($_GET['w'], 10);
$RatingFile = file_get_contents(APPATH."img/muffin-rating.svg");
header('Content-Type: image/svg+xml');
die(str_replace("width='100'", "width='$ScorePercent'", $RatingFile));
