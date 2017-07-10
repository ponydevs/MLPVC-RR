<?php

namespace App\Controllers;
use App\CoreUtils;
use App\Permission;
use App\RegExp;

class BrowserController extends Controller {
	public $do = 'browser';
	public function index($params){
		global $Database;

		$AgentString = null;
		if (isset($params['session']) && Permission::sufficient('developer')){
			$SessionID = intval($params['session'], 10);
			$Session = $Database->where('id', $SessionID)->getOne('sessions');
			if (!empty($Session))
				$AgentString = $Session->user_agent;
		}
		$browser = CoreUtils::detectBrowser($AgentString);
		if (empty($browser['platform']))
			error_log('Could not find platform based on the following UA string: '.preg_replace(new RegExp(INVERSE_PRINTABLE_ASCII_PATTERN), '', $AgentString));

		CoreUtils::fixPath('/browser'.(!empty($Session)?"/{$Session->id}":''));

		CoreUtils::loadPage([
			'title' => 'Browser recognition test page',
			'do-css',
			'no-robots',
			'import' => [
				'AgentString' => $AgentString,
				'Session' => $Session ?? null,
				'browser' => $browser,
			],
		], $this);
	}
}
