<?php

	// Global variables \\
	$Color = 'Color';
	$color = 'color';
	$signedIn = false;
	/** @var $currentUser \App\Models\User */
	$currentUser = null;
	$do = !empty($_GET['do']) ? $_GET['do'] : 'index';
	$data = !empty($_GET['data']) ? $_GET['data'] : '';
	unset($_REQUEST['do']);
	unset($_REQUEST['data']);

	require "../includes/init.php";

	use App\RegExp;
	use App\HTTP;
	use App\Users;
	use App\CoreUtils;

	$phpExtensionPattern = new RegExp('\.php($|\?.*)');

	if (preg_match($phpExtensionPattern,$_SERVER['REQUEST_URI']))
		HTTP::redirect(preg_replace($phpExtensionPattern, '$1', $_SERVER['REQUEST_URI']));
	if (!preg_match($REWRITE_REGEX,"/$do/$data")){
		Users::authenticate();
		CoreUtils::notFound();
	}

	if ($do === GH_WEBHOOK_DO){
		if (empty(GH_WEBHOOK_DO)) HTTP::redirect('/');

		if (!empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'GitHub-Hookshot/') === 0){
			if (empty($_SERVER['HTTP_X_GITHUB_EVENT']) || empty($_SERVER['HTTP_X_HUB_SIGNATURE']))
				CoreUtils::notFound();

			$payloadHash = hash_hmac('sha1', file_get_contents('php://input'), GH_WEBHOOK_SECRET);
			if ($_SERVER['HTTP_X_HUB_SIGNATURE'] !== "sha1=$payloadHash")
				CoreUtils::notFound();

			switch (strtolower($_SERVER['HTTP_X_GITHUB_EVENT'])) {
				case 'push':
					$output = array();
					chdir(PROJPATH);
					exec("git reset HEAD --hard",$output);
					exec("git pull",$output);
					$output = implode("\n", $output);
					if (empty($output))
						HTTP::statusCode(500, AND_DIE);
					exec("composer update --no-dev 2>&1",$arr);
					$output .= implode("\n", $arr);
					echo $output;
				break;
				case 'ping':
					echo "pong";
				break;
				default: CoreUtils::notFound();
			}

			exit;
		}
		CoreUtils::notFound();
	}

	// Static redirects
	switch ($do){
		// PAGES
		case "logs":
			$do = 'admin';
			$data = rtrim("logs/$data",'/');
			HTTP::redirect(rtrim("/$do/$data", '/'));
		break;
		case "u":
			$do = 'user';
		break;
		case "cg":
		case "colourguides":
		case "colourguide":
		case "colorguides":
			$do = 'colorguide';
		break;
	}

	// Load controller
	$controller = INCPATH."controllers/$do.php";
	if (!($do === 'colorguide' && preg_match(new RegExp('\.(svg|png)$'), $data)))
		Users::authenticate();
	if (!file_exists($controller))
		CoreUtils::notFound();
	require $controller;
