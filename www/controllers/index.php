<?php

	$CurrentEpisode = Episodes::GetLatest();
	if (empty($CurrentEpisode))
		CoreUtils::LoadPage(array(
			'title' => 'Home',
			'view' => 'episode',
		));

	Episodes::LoadPage($CurrentEpisode);
