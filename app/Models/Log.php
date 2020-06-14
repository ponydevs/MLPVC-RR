<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\JSON;
use App\Logs;
use function in_array;

/**
 * @property int      $id
 * @property int      $initiator
 * @property string   $entry_type
 * @property DateTime $created_at
 * @property DateTime $updated_at
 * @property string   $ip
 * @property User     $actor      (Via relations)
 * @property array    $data       (Via magic method)
 * @method static Log[] find_all_by_ip(string $ip)
 */
class Log extends NSModel {
  public static $table_name = 'logs';

  public static $belongs_to = [
    ['actor', 'class' => 'User', 'foreign_key' => 'initiator'],
  ];

  /** For Twig */
  public function getActor() {
    return $this->actor;
  }

  /** For Twig */
  public function getData() {
    return $this->data;
  }

  public function get_data():?array {
    $attr = $this->read_attribute('data');

    if ($attr === null)
      return null;

    static $decoded;

    if (isset($decoded))
      return $decoded;

    if (is_string($attr)) {
      $decoded = JSON::decode($attr);
      foreach ($decoded as $key => $value) {
        if (is_array($value) && isset($value['date'])) {
          $decoded[$key] = $value['date'];
        }
      }
      return $decoded;
    }

    return $attr;
  }

  public function set_data($value) {
    if (!is_string($value))
      $value = JSON::encode($value, JSON_FORCE_OBJECT);
    $this->assign_attribute('data', $value);
  }

  public function getDisplayIP():string {
    return in_array(strtolower($this->ip), Logs::LOCALHOST_IPS, true) ? 'localhost' : $this->ip;
  }
}
