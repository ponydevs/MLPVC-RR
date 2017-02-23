<?php

	// Global variables \\
	$Color = 'Color';
	$color = 'color';
	$signedIn = false;
	/** @var $currentUser \App\Models\User */
	global $currentUser;
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

			switch (strtolower($_SERVER['HTTP_X_GITHUB_EVENT'])){
				case 'push':
					$output = array();
					chdir(PROJPATH);
					exec("git reset HEAD --hard",$output);
					exec("git pull",$output);
					$output = implode("\n", $output);
					if (empty($output))
						HTTP::statusCode(500, AND_DIE);
					$arr[] = "\n";
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

	require INCPATH.'routes.php';
	$path = $do === 'index' ? '/' : "/$do".($data?"/$data":'');
	$match = $router->match($path);
	if (!isset($match['target']))
		CoreUtils::notFound();
	(\App\RouteHelper::processHandler($match['target']))($match['params']);

