<?php

use App\CoreUtils;
use App\Models\Episode;
use App\Episodes;
use App\RegExp;

if (!preg_match(new RegExp('^(\d+)?(-?[a-z\-]+)?$','i'), $data, $title))
	CoreUtils::notFound();

$Database->where('season', 0);
if (isset($title[1]) && is_numeric($title[1]))
	$Database->where('episode', intval($data, 10));
else if (!empty($title[2])){
	$data = strtolower($title[2]);
	$Database->where("regexp_replace(regexp_replace(lower(\"title\"), '[^a-z]', '-', 'g'), '-{2,}', '-', 'g') = '$data'");
}
else {
	$Database->reset();
	CoreUtils::notFound();
}

/** @var $CurrtentEpisode Episode */
$CurrtentEpisode = $Database->getOne('episodes');
if (empty($CurrtentEpisode))
	CoreUtils::notFound();

$CurrtentEpisode = $CurrtentEpisode->addAiringData();
Episodes::loadPage($CurrtentEpisode);
