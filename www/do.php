<?php

	// Global variables \\
	$Color = 'Color';
	$color = 'color';
	$signedIn = false;
	$currentUser = null;
	$do = !empty($_GET['do']) ? $_GET['do'] : 'index';
	$data = !empty($_GET['data']) ? $_GET['data'] : '';
	
	require "init.php";

	if (!regex_match($REWRITE_REGEX,"/$do/$data"))
		CoreUtils::NotFound();

	if ($do === GH_WEBHOOK_DO){
		if (empty(GH_WEBHOOK_DO)) CoreUtils::Redirect('/', AND_DIE);

		if (!empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'GitHub-Hookshot/') === 0){
			if (empty($_SERVER['HTTP_X_GITHUB_EVENT']) || empty($_SERVER['HTTP_X_HUB_SIGNATURE']))
				CoreUtils::NotFound();

			$payloadHash = hash_hmac('sha1', file_get_contents('php://input'), GH_WEBHOOK_SECRET);
			if ($_SERVER['HTTP_X_HUB_SIGNATURE'] !== "sha1=$payloadHash")
				CoreUtils::NotFound();

			switch (strtolower($_SERVER['HTTP_X_GITHUB_EVENT'])) {
				case 'push':
					$output = array();
					exec("git reset HEAD --hard",$output);
					exec("git pull",$output);
					$output = implode("\n", $output);
					if (empty($output))
						CoreUtils::StatusCode(500, AND_DIE);
					echo $output;
				break;
				case 'ping':
					echo "pong";
				break;
				default: CoreUtils::NotFound();
			}

			exit;
		}
		CoreUtils::NotFound();
	}

	// Static redirects
	switch ($do){
		// PAGES
		case "logs":
			$do = 'admin';
			$data = rtrim("logs/$data",'/');
			CoreUtils::Redirect(rtrim("/$do/$data",'/'));
		case "u":
			$do = 'user';
		case "colourguides":
		case "colourguide":
		case "colorguides":
			$do = 'colorguide';
	}

	// Load controller
	$controller = APPATH."controllers/$do.php";
	if (!file_exists($controller))
		CoreUtils::NotFound();
	require $controller;
