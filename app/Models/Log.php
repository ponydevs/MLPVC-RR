<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\JSON;
use App\Logs;
use function in_array;

/**
 * @property int            $id
 * @property int            $refid
 * @property string         $initiator
 * @property string         $reftype
 * @property DateTime       $created_at
 * @property DateTime       $updated_at
 * @property string         $ip
 * @property DeviantartUser $actor      (Via relations)
 * @property array          $data       (Via magic method)
 * @method static Log find_by_reftype_and_refid(string $reftype, int $refid)
 * @method static Log[] find_all_by_ip(string $ip)
 */
class Log extends NSModel {
  public static $table_name = 'logs';

  public static $belongs_to = [
    ['actor', 'class' => 'DeviantartUser', 'foreign_key' => 'initiator'],
  ];

  /** For Twig */
  public function getActor():DeviantartUser {
    return $this->actor;
  }

  public function get_data():?array {
    $attr = $this->read_attribute('data');

    if ($attr === null)
      return null;

    if (is_string($attr))
      return JSON::decode($attr);

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
