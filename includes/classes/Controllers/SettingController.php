<?php

namespace App\Controllers;
use App\CoreUtils;
use App\CSRFProtection;
use App\GlobalSettings;
use App\Permission;
use App\Response;

/** @property $_setting array */
class SettingController extends Controller {

	public function __construct(){
		parent::__construct();

		if (!Permission::sufficient('staff'))
			CoreUtils::notFound();
		CSRFProtection::protect();
	}

	private $_setting, $_value;
	public function _getSetting($params){
		$this->_setting = $params['key'];
		$this->_value = GlobalSettings::get($this->_setting);
	}

	public function get($params){
		$this->_getSetting($params);

		Response::done(['value' => $this->_value]);
	}

	public function set($params){
		$this->_getSetting($params);

		if (!isset($_POST['value']))
			Response::fail('Missing setting value');

		try {
			$newvalue = GlobalSettings::process($this->_setting);
		}
		catch (\Exception $e){ Response::fail('Preference value error: '.$e->getMessage()); }

		if ($newvalue === $this->_value)
			Response::done(['value' => $newvalue]);
		if (!GlobalSettings::set($this->_setting, $newvalue))
			Response::dbError();

		Response::done(['value' => $newvalue]);
	}
}
