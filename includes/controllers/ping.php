<?php

use App\CoreUtils;
use App\Response;

if (!POST_REQUEST)
	CoreUtils::notFound();

Response::done();
