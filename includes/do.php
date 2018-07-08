<?php

	require __DIR__.'/../includes/init.php';

	use App\RegExp;
	use App\HTTP;
	use App\Users;
	use App\CoreUtils;

	// Strip &hellip; and what comes after
	$decoded_uri = CoreUtils::trim(urldecode($_SERVER['REQUEST_URI']));
	$request_uri = preg_replace(new RegExp('….*$'),'',$decoded_uri);
	// Strip non-ascii
	$safe_uri = preg_replace(new RegExp('[^ -~]'), '', $request_uri);
	// Enforce URL
	CoreUtils::fixPath($safe_uri);

	require INCPATH.'routes.php';
	/** @var $match array */
	$match = $router->match($safe_uri);
	if (!isset($match['target']))
		CoreUtils::notFound();
	(\App\RouteHelper::processHandler($match['target']))($match['params']);

