<?php

namespace App\Controllers;

use App\CachedFile;
use App\CoreUtils;
use App\DB;
use App\Models\KnownIP;
use App\Permission;
use IPTools\IP;

class IPController extends Controller {
	function __construct(){
		parent::__construct();

		if (KnownIP::count() === 0)
			$this->_import();
	}

	private function _import(){
		$lockfile = CachedFile::init(FSPATH.'ip_import.lock', -1);
		if (!$lockfile->expired())
			return;

		/** @var $query KnownIP[] */
		$query = DB::$instance->setModel('KnownIP')->query(
			'SELECT DISTINCT
				initiator as user_id,
				MAX(timestamp) as last_seen,
				MIN(timestamp) as first_seen,
				ip
			FROM log
			GROUP BY user_id, ip
			ORDER BY last_seen DESC');
		foreach ($query as $item)
			KnownIP::record($item->ip, $item->user_id, $item->last_seen, $item->first_seen);

		$lockfile->bump();
	}

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

		$this->_import();
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
