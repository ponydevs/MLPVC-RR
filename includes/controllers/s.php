<?php

use App\CoreUtils;
use App\Episodes;
use App\HTTP;
use App\RegExp;

if (POST_REQUEST)
	HTTP::StatusCode(400, AND_DIE);

if (!regex_match(new RegExp('^(req|res)/(\d+)$'), $data, $match))
	CoreUtils::NotFound();

$match[1] .= (array('req' => 'uest', 'res' => 'ervation'))[$match[1]];

/** @var $LinkedPost \App\Models\Post */
$LinkedPost = $Database->where('id', $match[2])->getOne("{$match[1]}s");
if (empty($LinkedPost))
	CoreUtils::NotFound();

$Episode = Episodes::GetActual($LinkedPost->season, $LinkedPost->episode);
if (empty($Episode))
	CoreUtils::NotFound();

$Episode->LinkedPost = $LinkedPost;

Episodes::LoadPage($Episode, false);
