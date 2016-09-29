<?php

	if (!Permission::Sufficient('staff'))
		CoreUtils::NotFound();

	CoreUtils::LoadPage(array(
		'title' => 'Users',
		'do-css'
	));
