<?php

namespace App\Controllers;

use App\CoreUtils;
use App\GlobalSettings;
use App\Permission;
use App\Response;
use Exception;

class SettingController extends Controller {
  public function __construct() {
    parent::__construct();

    if (Permission::insufficient('staff'))
      CoreUtils::noPerm();
  }

  private $setting, $value;

  public function load_setting($params) {
    $this->setting = $params['key'];
    $this->value = GlobalSettings::get($this->setting);
  }

  public function api($params) {
    $this->load_setting($params);

    switch ($this->action){
      case 'GET':
        Response::done(['value' => $this->value]);
      break;
      case 'PUT':
        $this->load_setting($params);

        if (!isset($_REQUEST['value']))
          Response::fail('Missing setting value');

        try {
          $newvalue = GlobalSettings::process($this->setting);
        }
        catch (Exception $e){
          Response::fail('Preference value error: '.$e->getMessage());
        }

        if ($newvalue === $this->value)
          Response::done(['value' => $newvalue]);
        if (!GlobalSettings::set($this->setting, $newvalue))
          Response::dbError();

        Response::done(['value' => $newvalue]);
      break;
      default:
        CoreUtils::notAllowed();
    }
  }
}
