<?php

use App\CoreUtils;
use App\Permission;

if (!Permission::Sufficient('staff'))
	CoreUtils::NotFound();

CoreUtils::LoadPage(array(
	'title' => 'Users',
	'do-css'
));
