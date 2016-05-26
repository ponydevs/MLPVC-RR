<?php

	$possible_images = array(
		'http://i.imgur.com/C7T0npq.png', // Original by DJDavid98
		'http://i.imgur.com/RwnT8EX.png', // Coco & Rarity by Pirill
		'http://i.imgur.com/qg9Y1LN.png', // Applebloom's new CM by Drakizora
	);

	$k = isset($_GET['i']) && is_numeric($_GET['i']) ? max(0,min(count($possible_images)-1,intval($_GET['i'], 10))) : array_rand($possible_images);
	CoreUtils::Redirect($possible_images[$k],true,302);
