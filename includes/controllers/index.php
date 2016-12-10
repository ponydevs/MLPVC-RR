<?php

use App\Episodes;
use App\CoreUtils;

$CurrentEpisode = Episodes::getLatest();
if (empty($CurrentEpisode))
	CoreUtils::loadPage(array(
		'title' => 'Home',
		'view' => 'episode',
	));

Episodes::loadPage($CurrentEpisode);
