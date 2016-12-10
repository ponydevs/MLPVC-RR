<?php

use App\CoreUtils;
use App\Permission;

if (!Permission::Sufficient('staff'))
	CoreUtils::notFound();

CoreUtils::loadPage(array(
	'title' => 'Users',
	'do-css'
));
