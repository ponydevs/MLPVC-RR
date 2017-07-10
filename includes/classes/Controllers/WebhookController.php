<?php

namespace App\Controllers;

class WebhookController extends Controller {
	public function index(){
		if (empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'GitHub-Hookshot/') === 0)
			CoreUtils::notFound();
		if (empty($_SERVER['HTTP_X_GITHUB_EVENT']) || empty($_SERVER['HTTP_X_HUB_SIGNATURE']))
			CoreUtils::notFound();

		$payloadHash = hash_hmac('sha1', file_get_contents('php://input'), GH_WEBHOOK_SECRET);
		if ($_SERVER['HTTP_X_HUB_SIGNATURE'] !== "sha1=$payloadHash")
			CoreUtils::notFound();

		switch (strtolower($_SERVER['HTTP_X_GITHUB_EVENT'])){
			case 'push':
				$output = [];
				chdir(PROJPATH);
				exec('git reset HEAD --hard',$output);
				exec('git pull',$output);
				$output = implode("\n", $output);
				if (empty($output))
					HTTP::statusCode(500, AND_DIE);
				$arr[] = "\n";
				exec('composer update --no-dev 2>&1',$arr);
				$output .= implode("\n", $arr);
				echo $output;
			break;
			case 'ping':
				echo 'pong';
			break;
			default: CoreUtils::notFound();
		}

		exit;
	}
}
