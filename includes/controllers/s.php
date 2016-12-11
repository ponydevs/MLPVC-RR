<?php

use App\CoreUtils;
use App\Episodes;
use App\HTTP;
use App\RegExp;

if (POST_REQUEST)
	HTTP::statusCode(400, AND_DIE);

/** @var $data string */

if (!preg_match(new RegExp('^(req|res)/(\d+)$'), $data, $match))
	CoreUtils::notFound();

$match[1] .= (array('req' => 'uest', 'res' => 'ervation'))[$match[1]];

/** @var $LinkedPost \App\Models\Post */
$LinkedPost = $Database->where('id', $match[2])->getOne("{$match[1]}s");
if (empty($LinkedPost))
	CoreUtils::notFound();

$Episode = Episodes::getActual($LinkedPost->season, $LinkedPost->episode);
if (empty($Episode))
	CoreUtils::notFound();

$Episode->LinkedPost = $LinkedPost;

Episodes::loadPage($Episode, false);
