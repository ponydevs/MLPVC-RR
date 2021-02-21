<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\CoreUtils;
use App\DB;
use App\DeviantArt;
use App\Permission;
use App\Time;
use RuntimeException;
use function count;
use function in_array;

/**
 * @property int          $id
 * @property int          $max_entries
 * @property string       $name
 * @property string       $type
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
    ['entries', 'class_name' => 'EventEntry', 'order' => 'score desc, created_at asc'],
  ];

  public static $belongs_to = [
    ['creator', 'class' => 'User', 'foreign_key' => 'added_by'],
    ['finalizer', 'class' => 'User', 'foreign_key' => 'finalized_by'],
  ];

  /** For Twig */
  function getCreator() {
    return $this->creator;
  }

  public const EVENT_TYPES = [
    'collab' => 'Collaboration',
    'contest' => 'Contest',
  ];

  public const REGULAR_ENTRY_ROLES = ['user', 'member', 'staff'];

  public const SPECIAL_ENTRY_ROLES = [
    'spec_discord' => 'Discord Server Members',
  ];

  /**
   * @return Event[]
   */
  public static function upcoming() {
    return DB::$instance->where('starts_at > NOW()')
      ->orWhere('ends_at > NOW()')
      ->orderBy('starts_at')
      ->get('events');
  }

  public function toURL():string {
    return "/event/{$this->id}-".$this->getSafeName();
  }

  public function toAnchor():string {
    return "<a href='{$this->toURL()}'>$this->name</a>";
  }

  public function getSafeName():string {
    return CoreUtils::makeUrlSafe($this->name);
  }

  public function checkCanEnter(User $user):bool {
    switch ($this->entry_role){
      case 'user':
      case 'member':
      case 'staff':
        return $user->perm($this->entry_role);
      case 'spec_discord':
        return $user->isDiscordServerMember(true);
      default:
        throw new RuntimeException("Unhandled entry role {$this->entry_role} on event #{$this->id}");
    }
  }

  public function checkCanVote(User $user):bool {
    return !$this->hasEnded() && Permission::sufficient($this->vote_role, $user->role);
  }

  public function hasStarted(?int $now = null) {
    return ($now ?? time()) >= strtotime($this->starts_at);
  }

  public function hasEnded(?int $now = null) {
    return ($now ?? time()) >= strtotime($this->ends_at);
  }

  public function isOngoing() {
    return $this->hasStarted() && !$this->hasEnded();
  }

  public function getEntryRoleName():string {
    return in_array($this->entry_role, self::REGULAR_ENTRY_ROLES) ? CoreUtils::makePlural(Permission::ROLES_ASSOC[$this->entry_role])
      : self::SPECIAL_ENTRY_ROLES[$this->entry_role];
  }

  public function isFinalized() {
    return $this->type === 'collab' ? !empty($this->result_favme) : $this->hasEnded();
  }

  public function getEntriesHTML(bool $lazyload = false, bool $wrap = WRAP):string {
    $HTML = '';
    $Entries = $this->entries;
    foreach ($Entries as $entry)
      $HTML .= $entry->toListItemHTML($this, $lazyload);

    return $wrap ? "<ul id='event-entries'>$HTML</ul>" : $HTML;
  }

  public function getWinnerHTML(bool $wrap = WRAP):string {
    $HTML = '';

    if ($this->type === 'collab') {
      $deviation = DeviantArt::getCachedDeviation($this->result_favme);
      if ($deviation) {
        $HTML = '<div id="final-image"><div>'.$deviation->toLinkWithPreview().'</div></div>';
      } else {
        $url = "http://fav.me/{$this->result_favme}";
        $HTML = "<div id='final-image'><p>Could not load preview, use this link to view the deviation: <a href='$url'>$url</a></p></div>";
      }
    }
    else {

      /** @var $HighestScoringEntries EventEntry[] */
      $HighestScoringEntries = DB::$instance->setModel(EventEntry::class)->query(
        'SELECT * FROM event_entries 
				WHERE event_id = ? AND score > 0 AND score = (SELECT MAX(score) FROM event_entries)
				ORDER BY created_at', [$this->id]);

      if (empty($HighestScoringEntries))
        $HTML .= "<div class='notice info'><span class='typcn typcn-times'></span> No entries match the win criteria, thus the event ended without a winner</div>";
      else {
        $HTML .= '<p>The event has concluded with '.CoreUtils::makePlural('winner', count($HighestScoringEntries), PREPEND_NUMBER).'.</p>';
        foreach ($HighestScoringEntries as $entry){
          $title = CoreUtils::escapeHTML($entry->title);
          $preview = isset($entry->prev_full)
            ? "<a href='{$entry->prev_src}'><img src='{$entry->prev_thumb}' alt=''><span class='title'>$title</span></a>"
            : "<span class='title'>$title</span>";
          $by = '<div>'.$entry->submitter->toAnchor(WITH_AVATAR).'</div>';
          $HTML .= "<div class='winning-entry'>$preview$by</div>";
        }
      }
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
