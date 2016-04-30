<?php

	if (!regex_match(new RegExp('^([a-z\-]+|\d+)$'),$data))
		CoreUtils::NotFound();

	$assoc = array('friendship-games' => 3);
	$flip_assoc = array_flip($assoc);

	if (!is_numeric($data)){
		if (empty($assoc[$data]))
			CoreUtils::NotFound();
		$url = $data;
		$data = $assoc[$data];
	}
	else {
		$data = intval($data, 10);
		if (empty($flip_assoc[$data]))
			CoreUtils::NotFound();
		$url = $flip_assoc[$data];
	}

	Episode::LoadPage($data);
