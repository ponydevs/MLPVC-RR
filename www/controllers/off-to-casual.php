<?php

	$possible_images = array(
		'http://i.imgur.com/C7T0npq.png', // Original by DJDavid98
		'http://i.imgur.com/RwnT8EX.png', // Coco & Rarity by Pirill
		'http://i.imgur.com/qg9Y1LN.png', // Applebloom's new CM by Drakizora
		'http://i.imgur.com/cxCzsB8.png', // Applebloom falling by Drakizora
		'http://i.imgur.com/iUZe3O2.png', // CMs floating around Applebloom by Drakizora
	);
	$image_count = count($possible_images);

	$data = regex_replace(new RegExp('^(\d+)\.(png|jpe?g|gif)$'), '$1', $data);

	if (is_numeric($data))
		$k = max(0,min($image_count-1,intval($data, 10)-1));
	else {
		$k = intval(date('i'), 10) % $image_count;
	}

	HTTP::Redirect($possible_images[$k], true, 302);
