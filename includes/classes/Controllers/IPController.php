<?php

namespace App\Controllers;

use App\CachedFile;
use App\CoreUtils;
use App\DB;
use App\Models\KnownIP;
use App\Permission;
use IPTools\IP;

class IPController extends Controller {
	function index($params){
		if (Permission::insufficient('staff'))
			CoreUtils::notFound();

		$ip = $params['ip'];

		try {
			$ip = (string) IP::parse($ip);
		}
		catch(\Throwable $e){
			CoreUtils::notFound();
		}

		if (in_array($ip, \App\Logs::LOCALHOST_IPS, true))
			$ip = 'localhost';

		CoreUtils::fixPath("/admin/ip/$ip");

		$knownIPs = KnownIP::find_all_by_ip($ip);
		$Users = [];
		if (count($knownIPs) > 0){
			foreach ($knownIPs as $knownIP){
				$user = $knownIP->user;
				if (!empty($user))
					$Users[] = $user;
			}
		}

		CoreUtils::loadPage([
			'view' => 'admin-ip',
			'css' => 'admin-ip',
			'title' => "$ip - IP Address - Admin Area",
			'import' => [
				'KnownIPs' => $knownIPs,
				'ip' => $ip,
				'Users' => $Users,
				'nav_adminip' => true,
			]
		]);
	}
}
