<?php

use App\CoreUtils;
use App\Permission;

if (Permission::Insufficient('developer'))
	CoreUtils::notFound();

header('Content-Type: text/plain; charset=utf-8;');
readfile(APPATH.'../mlpvc-rr-error.log');
