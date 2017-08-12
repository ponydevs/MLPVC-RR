<?php

namespace App\Controllers;
use App\CoreUtils;
use App\Models\Session;
use App\Permission;
use App\RegExp;

class BrowserController extends Controller {
	public $do = 'browser';
	public function index($params){
		$AgentString = null;
		if (isset($params['session']) && Permission::sufficient('developer')){
			$SessionID = intval($params['session'], 10);
			/** @var $Session Session */
			$Session = Session::find($SessionID);
			if (!empty($Session))
				$AgentString = $Session->user_agent;
		}
		else $Session = null;
		$browser = CoreUtils::detectBrowser($AgentString);
		if (empty($browser['platform']))
			error_log('Could not find platform based on the following UA string: '.preg_replace(new RegExp(INVERSE_PRINTABLE_ASCII_PATTERN), '', $AgentString));

		if ($Session !== null){
			$Session->platform = $browser['platform'];
			$Session->browser_name = $browser['browser_name'];
			$Session->browser_ver = $browser['browser_ver'];
			$Session->save();
		}

		CoreUtils::fixPath('/browser'.(!empty($Session)?"/{$Session->id}":''));

		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Browser recognition test page',
			'css' => [true],
			'no-robots' => true,
			'import' => [
				'AgentString' => $AgentString,
				'Session' => $Session ?? null,
				'browser' => $browser,
			],
		]);
	}
}
