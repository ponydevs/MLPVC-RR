<?php

	require __DIR__.'/../includes/init.php';

	use App\RegExp;
	use App\HTTP;
	use App\Users;
	use App\CoreUtils;

	$permRedirectPattern = new RegExp('^\s*(.*?)\.php(\?.*)?$','i');
	$requri = CoreUtils::trim($_SERVER['REQUEST_URI']);
	if (preg_match($permRedirectPattern,$requri))
		HTTP::tempRedirect(preg_replace($permRedirectPattern, '$1$2', $requri));
	$decoded_uri = CoreUtils::trim(urldecode($requri));
	if (!CoreUtils::isURLSafe($decoded_uri, $matches)){
		Users::authenticate();
		CoreUtils::badReq();
	}

	// Enforce URL
	$decoded_uri = '/'.rtrim(($matches[1]??'').'/'.($matches[2]??''),'/');
	$qs = strtok('?');
	if ($qs !== false)
		$decoded_uri .= "?$qs";
	CoreUtils::fixPath($decoded_uri);

	require INCPATH.'routes.php';
	/** @var $match array */
	$match = $router->match($decoded_uri);
	if (!isset($match['target']))
		CoreUtils::notFound();
	(\App\RouteHelper::processHandler($match['target']))($match['params']);

