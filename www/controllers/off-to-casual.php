<?php

	$possible_images = array(
		'http://i.imgur.com/C7T0npq.png', // Original
		'http://i.imgur.com/RwnT8EX.png', // Coco & Rarity
	);

	$k = isset($_GET['i']) && is_numeric($_GET['i']) ? max(0,min(count($possible_images)-1,intval($_GET['i'], 10))) : array_rand($possible_images);
	CoreUtils::Redirect($possible_images[$k],true,302);
