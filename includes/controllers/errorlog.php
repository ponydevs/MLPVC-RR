<?php

use App\CoreUtils;
use App\Permission;

if (Permission::insufficient('developer'))
	CoreUtils::notFound();

header('Content-Type: text/plain; charset=utf-8;');
readfile(APPATH.'../mlpvc-rr-error.log');
