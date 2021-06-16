<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\CoreUtils;
use App\DB;
use App\DeviantArt;
use App\Time;

/**
 * @property int          $id
 * @property int          $max_entries
 * @property string       $name
 * @property string       $entry_role
 * @property string       $vote_role
 * @property DateTime     $starts_at
 * @property DateTime     $ends_at
 * @property int          $added_by
 * @property DateTime     $created_at
 * @property string       $desc_src
 * @property string       $desc_rend
 * @property string       $result_favme
 * @property int          $finalized_by
 * @property DateTime     $finalized_at
 * @property EventEntry[] $entries      (Via relations)
 * @property User         $creator      (Via relations)
 * @property User         $finalizer    (Via relations)
 * @method static Event find(...$args)
 */
class Event extends NSModel implements Linkable {
  public static $has_many = [
    ['entries', 'class_name' => 'EventEntry', 'order' => 'created_at asc'],
  ];

  public static $belongs_to = [
    ['creator', 'class' => 'User', 'foreign_key' => 'added_by'],
    ['finalizer', 'class' => 'User', 'foreign_key' => 'finalized_by'],
  ];

  /** For Twig */
  function getCreator() {
    return $this->creator;
  }

  public const SPECIAL_ENTRY_ROLES = [
    'spec_discord' => 'Discord Server Members',
  ];

  /**
   * @return Event[]
   */
  public static function upcoming() {
    return [];
  }

  public function toURL():string {
    return "/event/$this->id-".$this->getSafeName();
  }

  public function toAnchor():string {
    return "<a href='{$this->toURL()}'>$this->name</a>";
  }

  public function getSafeName():string {
    return CoreUtils::makeUrlSafe($this->name);
  }

  public function checkCanEnter():bool {
    return false;
  }

  public function checkCanVote():bool {
    return false;
  }

  public function hasStarted() {
    return true;
  }

  public function hasEnded() {
    return true;
  }

  public function isOngoing() {
    return false;
  }

  public function getEntryRoleName():string {
    return self::SPECIAL_ENTRY_ROLES[$this->entry_role];
  }

  public function getEntriesHTML(bool $lazyload = false, bool $wrap = WRAP):string {
    $HTML = '';
    $Entries = $this->entries;
    foreach ($Entries as $entry)
      $HTML .= $entry->toListItemHTML($this, $lazyload);

    return $wrap ? "<ul id='event-entries'>$HTML</ul>" : $HTML;
  }

  public function getWinnerHTML(bool $wrap = WRAP):string {
    $deviation = DeviantArt::getCachedDeviation($this->result_favme);
    if ($deviation){
      $HTML = '<div id="final-image"><div>'.$deviation->toLinkWithPreview().'</div></div>';
    }
    else {
      $url = "http://fav.me/{$this->result_favme}";
      $HTML = "<div id='final-image'><p>Could not load preview, use this link to view the deviation: <a href='$url'>$url</a></p></div>";
    }

    return $wrap ? "<div id='results'>$HTML</div>" : $HTML;
  }

  public function getDurationString():string {
    $diff = Time::difference($this->starts_at->getTimestamp(), $this->ends_at->getTimestamp());

    return Time::differenceToString($diff, true);
  }

  /**
   * @param User   $user
   * @param string $cols
   *
   * @return EventEntry[]
   */
  public function getEntriesFor(User $user, string $cols = '*'):?array {
    return DB::$instance->where('submitted_by', $user->id)->where('event_id', $this->id)->get(EventEntry::$table_name, null, $cols);
  }
}
