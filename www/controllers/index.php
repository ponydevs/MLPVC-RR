<?php

	$CurrentEpisode = Episode::GetLatest();
	if (empty($CurrentEpisode)){
		unset($CurrentEpisode);
		CoreUtils::LoadPage(array(
			'title' => 'Home',
			'view' => 'episode',
		));
	}

	Episode::LoadPage($CurrentEpisode);
