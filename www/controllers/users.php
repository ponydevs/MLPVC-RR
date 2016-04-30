<?php

	if (!Permission::Sufficient('inspector'))
		CoreUtils::NotFound();

	CoreUtils::LoadPage(array(
		'title' => 'Users',
		'do-css'
	));
