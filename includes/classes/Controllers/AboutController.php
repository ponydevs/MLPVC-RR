<?php

namespace App\Controllers;
use App\CachedFile;
use App\CoreUtils;
use App\CSRFProtection;
use App\DB;
use App\HTTP;
use App\Models\Session;
use App\Permission;
use App\RegExp;
use App\Response;
use App\Statistics;
use App\Time;

class AboutController extends Controller {
	public function index(){
		CoreUtils::loadPage(__METHOD__, [
			'title' => 'About',
			'css' => [true],
			'js' => ['Chart',true],
		]);
	}

	public function browser($params){
		$AgentString = null;
		if (isset($params['session'])){
			if (Permission::insufficient('developer'))
				CoreUtils::noPerm();
			$SessionID = \intval($params['session'], 10);
			/** @var $Session \App\Models\Session */
			$Session = Session::find($SessionID);
			if (!empty($Session))
				$AgentString = $Session->user_agent;
		}
		else $Session = null;
		$browser = CoreUtils::detectBrowser($AgentString);
		if (empty($browser['platform']))
			CoreUtils::error_log('Could not find platform based on the following UA string: '.preg_replace(new RegExp(INVERSE_PRINTABLE_ASCII_PATTERN), '', $AgentString));

		if ($Session !== null){
			$Session->platform = $browser['platform'];
			$Session->browser_name = $browser['browser_name'];
			$Session->browser_ver = $browser['browser_ver'];
			$Session->save();
		}

		CoreUtils::fixPath('/about/browser'.(!empty($Session)?"/{$Session->id}":''));

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

	public function upcoming(){
		Response::done(['html' => CoreUtils::getSidebarUpcoming(NOWRAP)]);
	}
}
