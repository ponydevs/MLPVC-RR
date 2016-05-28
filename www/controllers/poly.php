<?php

	CoreUtils::LoadPage(array(
		'title' => 'Poly',
		'js' => array('jquery.ba-throttle-debounce','poly-editor', $do),
		'css' => array('poly-editor', $do),
	));
