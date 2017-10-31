<?php

namespace App\Controllers;
use App\Auth;
use App\CoreUtils;
use App\CSRFProtection;
use App\Models\User;
use App\Permission;
use App\Response;
use App\UserPrefs;
use App\Users;

class PreferenceController extends Controller {
	public function __construct(){
		parent::__construct();

		if (!POST_REQUEST || Permission::insufficient('user'))
			CoreUtils::noPerm();
		CSRFProtection::protect();
	}

	/** @var string */
	private $_setting, $_value;
	/** @var User|null */
	private $_user;
	public function _getPreference($params){
		$this->_setting = $params['key'];
		if (!empty($params['name'])){
			$user = Users::get($params['name'], 'name');
			if (!Auth::$signed_in)
				Response::fail();
			if (empty($user))
				Response::fail('The specified user does not exist');
			if (Auth::$user->id !== $user->id && Permission::insufficient('staff'))
				Response::fail();
			$this->_user = $user;
		}
		else $this->_user = null;
		$this->_value = UserPrefs::get($this->_setting, $this->_user);
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
		if (!UserPrefs::set($this->_setting, $newvalue, $this->_user))
			Response::dbError();

		Response::done(['value' => $newvalue]);
	}
}
