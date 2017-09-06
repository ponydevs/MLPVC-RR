<?php

namespace App\Controllers;
use App\CoreUtils;
use App\CSRFProtection;
use App\Permission;
use App\Response;
use App\UserPrefs;

class PreferenceController extends Controller {
	public function __construct(){
		parent::__construct();

		if (!POST_REQUEST || Permission::insufficient('user'))
			CoreUtils::noPerm();
		CSRFProtection::protect();
	}

	private $_setting, $_value;
	public function _getPreference($params){
		$this->_setting = $params['key'];
		$this->_value = UserPrefs::get($this->_setting);
	}

	public function get($params){
		$this->_getPreference($params);

		Response::done(['value' => $this->_value]);
	}

	public function set($params){
		$this->_getPreference($params);

		try {
			$newvalue = UserPrefs::process($this->_setting);
		}
		catch (\Exception $e){ Response::fail('Preference value error: '.$e->getMessage()); }

		if ($newvalue === $this->_value)
			Response::done(['value' => $newvalue]);
		if (!UserPrefs::set($this->_setting, $newvalue))
			Response::dbError();

		Response::done(['value' => $newvalue]);
	}
}
