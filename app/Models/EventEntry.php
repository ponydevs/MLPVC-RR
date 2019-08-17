<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Auth;
use App\CoreUtils;
use App\DB;
use App\DeviantArt;
use App\Twig;

/**
 * @property int      $id
 * @property int      $event_id
 * @property int      $score
 * @property string   $prev_src
 * @property string   $prev_full
 * @property string   $prev_thumb
 * @property string   $sub_prov
 * @property string   $sub_id
 * @property string   $submitted_by
 * @property string   $title
 * @property DateTime $submitted_at
 * @property DateTime $last_edited
 * @property User     $submitter    (Via relations)
 * @property Event    $event        (Via relations)
 * @method static EventEntry find(...$args)
 */
class EventEntry extends NSModel {
  public static $table_name = 'event_entries';

  public static $belongs_to = [
    ['submitter', 'class' => 'User', 'foreign_key' => 'submitted_by'],
    ['event'],
  ];

  /** For Twig */
  public function getSubmitter() {
    return $this->submitter;
  }

  public static $has_many = [
    ['votes', 'class' => 'EventEntryVote', 'foreign_key' => 'entry_id'],
  ];

  public function updateScore() {
    if ($this->score === null)
      return;

    $score = DB::$instance->disableAutoClass()->where('entryid', $this->id)->getOne(EventEntryVote::$table_name, 'COALESCE(SUM(value),0) as score');
    $this->score = $score['score'];
    $this->save();

    try {
      CoreUtils::socketEvent('entry-score', [
        'entryid' => $this->id,
      ]);
    }
    catch (\Exception $e){
      CoreUtils::error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
    }
  }

  public function getUserVote(User $user):?EventEntryVote {
    return EventEntryVote::find_by_entry_id_and_user_id($this->id, $user->id);
  }

  public function getFormattedScore() {
    return $this->score < 0 ? '−'.(-$this->score) : $this->score;
  }

  private static function _getPreviewDiv(string $fullsize, string $preview, ?string $file_type = null):string {
    return Twig::$env->render('event/_entry_preview.html.twig', [
      'fullsize' => $fullsize,
      'preview' => $preview,
      'file_type' => $file_type,
    ]);
  }

  public function getListItemVoting(Event $event):string {
    return Twig::$env->render('event/_entry_voting.html.twig', [
      'event' => $event,
      'entry' => $this,
    ]);
  }

  public function getListItemPreview($submission = null):?string {
    if ($submission === null)
      $submission = DeviantArt::getCachedDeviation($this->sub_id, $this->sub_prov);
    if ($this->sub_prov === 'fav.me' && $submission->preview !== null && $submission->fullsize !== null)
      return self::_getPreviewDiv($submission->fullsize, $submission->preview, $submission->type);
    if ($this->prev_thumb !== null && $this->prev_full !== null)
      return self::_getPreviewDiv($this->prev_full, $this->prev_thumb, $submission->type);

    return '';
  }

  public function toListItemHTML(Event $event = null, bool $lazyload = false, bool $wrap = true):string {
    if ($event === null)
      $event = $this->event;

    return Twig::$env->render('event/_entry.html.twig', [
      'event' => $event,
      'entry' => $this,
      'wrap' => $wrap,
      'lazyload' => $lazyload,
      'signed_in' => Auth::$signed_in,
      'current_user' => Auth::$user,
    ]);
  }
}
