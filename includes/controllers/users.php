<?php

use App\CoreUtils;
use App\Permission;

if (!Permission::sufficient('staff'))
	CoreUtils::notFound();

CoreUtils::loadPage(array(
	'title' => 'Users',
	'do-css'
));
