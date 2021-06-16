<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Auth;
use App\DeviantArt;
use App\Twig;
use RuntimeException;

/**
 * @property int      $id
 * @property int      $event_id
 * @property string   $prev_src
 * @property string   $prev_full
 * @property string   $prev_thumb
 * @property string   $sub_prov
 * @property string   $sub_id
 * @property int      $submitted_by
 * @property string   $title
 * @property DateTime $created_at
 * @property DateTime $updated_at
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

    throw new RuntimeException('Score changes are currently not allowed!');
  }

  private static function _getPreviewDiv(string $fullsize, string $preview, ?string $file_type = null):string {
    return Twig::$env->render('event/_entry_preview.html.twig', [
      'fullsize' => $fullsize,
      'preview' => $preview,
      'file_type' => $file_type,
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
