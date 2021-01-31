<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Auth;
use App\CoreUtils;
use App\DB;
use App\DeviantArt;
use App\Exceptions\CURLRequestException;
use App\Permission;
use App\Posts;
use App\Response;
use App\Time;
use App\UserPrefs;
use Exception;
use Throwable;
use function count;

/**
 * This is a blanket class for both requests and reservations.
 * Requests always have a non-null requested_by value which is to be used for post type detection.
 *
 * @property int        $id
 * @property string     $type
 * @property int        $season
 * @property int        $episode
 * @property int        $show_id
 * @property string     $preview
 * @property string     $fullsize
 * @property string     $label
 * @property int        $requested_by
 * @property DateTime   $requested_at
 * @property int        $reserved_by
 * @property DateTime   $reserved_at
 * @property string     $deviation_id
 * @property bool       $lock
 * @property DateTime   $finished_at
 * @property bool       $broken
 * @property User       $reserver       (Via relations)
 * @property User       $requester      (Via relations)
 * @property DateTime   $posted_at      (Via magic method)
 * @property int        $posted_by      (Via magic method)
 * @property User       $poster         (Via magic method)
 * @property Show       $show           (Via magic method)
 * @property string     $kind           (Via magic method)
 * @property bool       $finished       (Via magic method)
 * @property LockedPost $approval_entry (Via magic method)
 * @property bool       $is_request     (Via magic method)
 * @property bool       $is_reservation (Via magic method)
 * @method static Post|Post[] find(...$args)
 * @method static Post find_by_deviation_id(string $deviation_id)
 * @method static Post find_by_preview(string $preview_url)
 */
class Post extends NSModel implements Linkable {
  public static $belongs_to = [
    ['reserver', 'class' => 'User', 'foreign_key' => 'reserved_by'],
    ['requester', 'class' => 'User', 'foreign_key' => 'requested_by'],
    ['show'],
  ];

  public static $before_create = ['add_post_time'];
  public static $after_destroy = ['post_deletion'];

  public function add_post_time() {
    $this->posted_at = date('c');
  }

  public function post_deletion() {
    try {
      CoreUtils::socketEvent('post-delete', [
        'id' => $this->id,
      ]);
    }
    catch (Throwable $e){
      CoreUtils::logError("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
    }
  }

  /* For Twig */
  public function getShow() {
    return $this->show;
  }

  public function get_posted_at() {
    return $this->is_request ? $this->requested_at : $this->reserved_at;
  }

  public function set_posted_at($value) {
    if ($this->is_request)
      $this->requested_at = $value;
    else $this->reserved_at = $value;
  }

  public function get_posted_by(): ?int {
    return $this->is_request ? $this->requested_by : $this->reserved_by;
  }

  public function get_poster(): ?User {
    return User::find($this->posted_by);
  }

  public function get_finished() {
    return $this->deviation_id !== null && $this->reserved_by !== null;
  }

  public function get_is_request() {
    return $this->requested_by !== null;
  }

  public function get_is_reservation() {
    return !$this->is_request;
  }

  public function get_approval_entry() {
    return DB::$instance->setModel(LockedPost::class)->querySingle(
      "SELECT * FROM locked_posts
			WHERE post_id = ?
			ORDER BY created_at 
			LIMIT 1", [$this->id]
    );
  }

  public function get_kind() {
    return $this->is_request ? 'request' : 'reservation';
  }

  public const ORDER_BY_POSTED_AT = 'CASE WHEN requested_by IS NOT NULL THEN requested_at ELSE reserved_at END';
  public const CONTESTABLE = "<strong class='color-blue contest-note' title=\"Because this request was reserved more than 3 weeks ago it's now available for other members to reserve\"><span class='typcn typcn-info-large'></span> Can be contested</strong>";
  public const REQUEST_TYPES = [
    'chr' => 'Characters',
    'obj' => 'Objects',
    'bg' => 'Backgrounds',
  ];
  public const KINDS = ['request', 'reservation'];
  public const BROKEN = "<strong class='color-orange broken-note' title=\"The full size preview of this post was deemed unavailable and it is now marked as broken\"><span class='typcn typcn-plug'></span> Deemed broken</strong>";
  public const TRANSFER_ATTEMPT_CLEAR_REASONS = [
    'del' => 'the post was deleted',
    'snatch' => 'the post was reserved by someone else',
    'deny' => 'the post was transferred to someone else',
    'perm' => 'the previous reserver could no longer act on the post',
    'free' => 'the post became free for anyone to reserve',
  ];

  public function getIdString():string {
    return "post-{$this->id}";
  }

  public function toURL(Show $Episode = null):string {
    if (empty($Episode))
      $Episode = $this->show;

    return $Episode->toURL().'#'.$this->getIdString();
  }

  public function toAnchorWithPreview() {
    $haslabel = !empty($this->label);
    $alt = $haslabel ? CoreUtils::escapeHTML($this->label) : 'No label';
    $slabel = $haslabel ? $this->processLabel() : "<em>$alt</em>";

    return "<a class='post-link with-preview' href='{$this->toURL()}'><img src='{$this->preview}' alt='$alt'><span>$slabel</span></a>";
  }

  public function toAnchor(string $label = null, Show $Episode = null, $newtab = false):string {
    if ($Episode === null)
      $Episode = $this->show;
    $link = $this->toURL($Episode);
    if (empty($label))
      $label = $Episode->getID();
    else $label = htmlspecialchars($label);
    $target = $newtab ? 'target="_blank"' : '';

    return "<a href='$link' {$target}>$label</a>";
  }

  /**
   * A post is overdue when it has been reserved and left unfinished for over 3 weeks
   *
   * @param int|null $ts
   *
   * @return bool
   */
  public function isOverdue(?int $ts = null):bool {
    $now = $ts ?? time();

    return $this->is_request && $this->deviation_id === null && $this->reserved_by !== null && $now - $this->reserved_at->getTimestamp() >= Time::IN_SECONDS['week'] * 3;
  }

  public function processLabel():string {
    $label = CoreUtils::escapeHTML($this->label);
    $label = str_replace(array("''", '...'), array('"', '&hellip;'), $label);
    $label = preg_replace("/(\\w)'(\\w)/", '$1&rsquo;$2', $label);
    $label = preg_replace('/"([^"]+)"/', '&ldquo;$1&rdquo;', $label);
    $label = preg_replace('/(?:(f)ull[- ](b)od(?:y|ied)( version)?)/i', '<strong class="color-darkblue">$1ull $2ody</strong>$3', $label);
    $label = preg_replace('/(?:(f)ace[- ](o)nly( version)?)/i', '<strong class="color-darkblue">$1ace $2nly</strong>$3', $label);
    $label = preg_replace('/(?:(f)ull (s)cene( version)?)/i', '<strong class="color-darkblue">$1ull $2cene</strong>$3', $label);
    $label = preg_replace('/(?:(e)ntire (s)cene( version)?)/i', '<strong class="color-darkblue">$1ntire $2cene</strong>$3', $label);
    $label = preg_replace('/(?:(s)eparate (v)ector(s)?)/i', '<strong class="color-darkblue">$1eparate $2ector$3</strong>', $label);
    $label = preg_replace('/\[([\w\s]+ intensifies)]/i', '<span class="intensify">$1</span>', $label);

    return $label;
  }

  public function getFinishedImage(bool $view_only, string $cachebust = ''):string {
    try {
      $deviation = DeviantArt::getCachedDeviation($this->deviation_id);
    } catch (CURLRequestException $e) {
      CoreUtils::logError($e->getMessage()."\nStack trace:\n".$e->getTraceAsString());
      $deviation = null;
    }
    if ($deviation === null){
      $ImageLink = $view_only ? $this->toURL() : "http://fav.me/{$this->deviation_id}";
      $Image = "<a class='image deviation error' href='$ImageLink'>Preview unavailable<br><small>Click to view</small></a>";
    }
    else {
      $alt = CoreUtils::aposEncode($deviation->title);
      $ImageLink = $view_only ? $this->toURL() : "http://fav.me/{$deviation->id}";
      $approved = $this->lock ? ' approved' : '';
      $Image = "<div class='image deviation$approved'><a href='$ImageLink'><img src='{$deviation->preview}$cachebust' alt='$alt'>";
      if ($this->lock)
        $Image .= "<span class='approved-info' title='This submission has been accepted into the group gallery'></span>";
      $Image .= '</a></div>';
    }

    return $Image;
  }

  /**
   * List item generator function for request & reservation generators
   * TODO Turn into a view (eventually)
   *
   * @param bool $view_only      Only show the "View" button
   * @param bool $cachebust_url  Append a random string to the image URL to force a re-fetch
   * @param bool $enablePromises Output "promise" elements in place of all images (requires JS to display)
   *
   * @return string
   * @throws Exception
   */
  public function getLi(bool $view_only = false, bool $cachebust_url = false, bool $enablePromises = false):string {
    $ID = $this->getIdString();
    $alt = !empty($this->label) ? CoreUtils::aposEncode($this->label) : '';
    $postlink = $this->toURL();
    $ImageLink = $view_only ? $postlink : $this->fullsize;
    $cachebust = $cachebust_url ? '?t='.time() : '';
    $HTML = "<div class='image screencap'>".(
      $enablePromises
        ? "<div class='post-image-promise image-promise' data-href='$ImageLink' data-src='{$this->preview}$cachebust'></div>"
        : "<a href='$ImageLink'><img src='{$this->preview}$cachebust' alt='$alt'></a>"
      ).'</div>';
    $post_label = $this->getLabelHTML();
    $permalink = "<a href='$postlink'>".Time::tag($this->posted_at).'</a>';
    $isStaff = Permission::sufficient('staff');

    $posted_at = '<em class="info-line post-date">';
    if ($this->is_request){
      $isRequester = Auth::$signed_in && $this->requested_by === Auth::$user->id;
      $isReserver = Auth::$signed_in && $this->reserved_by === Auth::$user->id;
      $displayOverdue = Permission::sufficient('member') && $this->isOverdue();

      $posted_at .= "Requested $permalink";
      if (Auth::$signed_in && ($isStaff || $isRequester || $isReserver)){
        $posted_at .= ' by '.($isRequester ? "<a href='".Auth::$user->toURL(false)."'>You</a>" : $this->requester->toAnchor());
      }
    }
    else {
      $displayOverdue = false;
      $posted_at .= "Reserved $permalink";
    }
    $posted_at .= '</em>';

    $hide_reserved_status = $this->reserved_by === null || ($displayOverdue && !$isReserver && !$isStaff);
    if ($this->reserved_by !== null){
      $reserved_by = $displayOverdue && !$isReserver ? ' by '.$this->reserver->toAnchor() : '';
      $reserved_at = $this->is_request && $this->reserved_at !== null && !($hide_reserved_status && Permission::insufficient('staff'))
        ? "<em class='info-line reserve-date'>Reserved <strong>".Time::tag($this->reserved_at)."</strong>$reserved_by</em>"
        : '';
      if ($this->finished){
        $approved = $this->lock;
        if ($enablePromises){
          $view_only_promise = $view_only ? "data-viewonly='$view_only'" : '';
          $HTML = "<div class='image deviation'><div class='post-deviation-promise image-promise' data-post-id='{$this->id}' $view_only_promise></div></div>";
        }
        else $HTML = $this->getFinishedImage($view_only, $cachebust);
        $finished_at = $this->finished_at !== null
          ? "<em class='info-line finish-date'>Finished <strong>".Time::tag($this->finished_at).'</strong></em>'
          : '';
        $locked_at = '';
        if ($approved){
          $locked_post = $this->approval_entry;
          if (!empty($locked_post)){
            $approverIsNotReserver = $locked_post->user_id !== null && $locked_post->user_id !== $this->reserved_by;
            $approvedby = $isStaff && $locked_post->user_id !== null
              ? ' by '.(
              $approverIsNotReserver
                ? (
              $this->is_request && $locked_post->user_id === $this->requested_by
                ? 'the requester'
                : $locked_post->user->toAnchor()
              )
                : 'the reserver'
              )
              : '';
            $locked_at = $approved ? "<em class='approve-date'>Approved <strong>".Time::tag($locked_post->created_at)."</strong>$approvedby</em>" : '';
          }
          else $locked_at = '<em class="info-line approve-date">Approval data unavilable</em>';
        }
        $post_type = !empty($this->type) ? '<em class="info-line">Posted in the <strong>'.self::REQUEST_TYPES[$this->type].'</strong> section</em>'
          : '';
        $HTML .= $post_label.$posted_at.$post_type.$reserved_at.$finished_at.$locked_at;

        if (!empty($this->fullsize))
          $HTML .= "<span class='info-line'><a href='{$this->fullsize}' class='original' target='_blank' rel='noopener'><span class='typcn typcn-link'></span> Original image</a></span>";
        if (!$approved && Permission::sufficient('staff'))
          $HTML .= "<span class='info-line'><a href='{$this->reserver->deviantart_user->getOpenSubmissionsURL()}' target='_blank' rel='noopener'><span class='typcn typcn-arrow-forward'></span> View open submissions</a></span>";
      }
      else $HTML .= $post_label.$posted_at.$reserved_at;
    }
    else $HTML .= $post_label.$posted_at;

    if ($displayOverdue && ($isStaff || $isReserver))
      $HTML .= self::CONTESTABLE;

    if ($this->broken)
      $HTML .= self::BROKEN;

    $break = $this->broken ? 'class="admin-break"' : '';

    return "<li id='$ID' data-type='{$this->kind}' $break>$HTML".$this->getActionsHTML($view_only ? $postlink
        : false, $hide_reserved_status, $enablePromises).'</li>';
  }

  public function getLabelHTML():string {
    return !empty($this->label) ? '<span class="label'.(CoreUtils::contains($this->label, '"') ? ' noquotes'
        : '').'">'.$this->processLabel().'</span>' : '';
  }

  /**
   * Generate HTML for post action buttons
   *
   * @param false|string $view_only            Only show the "View" button
   *                                           Contains HREF attribute of button if string
   * @param bool         $hide_reserver_status
   * @param bool         $enablePromises
   *
   * @return string
   */
  public function getActionsHTML($view_only, bool $hide_reserver_status, bool $enablePromises):string {
    $by = $hide_reserver_status ? null : $this->reserver;
    $requested_by_user = $this->is_request && Auth::$signed_in && $this->requested_by === Auth::$user->id;
    $is_not_reserved = empty($by);
    $same_user = Auth::$signed_in && $this->reserved_by === Auth::$user->id;
    $can_edit = (empty($this->lock) && Permission::sufficient('staff')) || Permission::sufficient('developer') || ($requested_by_user && $is_not_reserved);
    $buttons = [];

    $HTML = Posts::getPostReserveButton($by, $view_only, false, $enablePromises);
    if (!empty($this->reserved_by)){
      $staffOrSameUser = ($same_user && Permission::sufficient('member')) || Permission::sufficient('staff');
      if (!$this->finished && $staffOrSameUser){
        $buttons[] = ['user-delete red cancel', 'Cancel Reservation'];
        $buttons[] = ['attachment green finish', ($same_user ? "I'm" : 'Mark as').' finished'];
      }
      if ($this->finished && !$this->lock){
        if (Permission::sufficient('staff'))
          $buttons[] = [
            (empty($this->preview) ? 'trash delete-only red' : 'media-eject orange').' unfinish', empty($this->preview)
              ? 'Delete' : 'Unfinish',
          ];
        if ($staffOrSameUser)
          $buttons[] = ['tick green check', 'Check'];
      }
    }

    if (empty($this->lock) && empty($buttons) && (Permission::sufficient('staff') || ($requested_by_user && $is_not_reserved)))
      $buttons[] = ['trash red delete', 'Delete'];
    if ($can_edit)
      array_splice($buttons, 0, 0, [['pencil darkblue edit', 'Edit']]);
    if ($this->lock && Permission::sufficient('staff'))
      $buttons[] = ['lock-open orange unlock', 'Unlock'];

    $HTML .= "<div class='actions'>";
    if ($view_only === false){
      $buttons[] = ['export blue share', 'Share'];
    }
    if (!empty($buttons)){
      if ($view_only !== false)
        $HTML .= "<div><a href='$view_only' class='btn link typcn typcn-arrow-forward'>View</a></div>";
      else {
        $regularButton = count($buttons) < 3;
        foreach ($buttons as $b){
          $WriteOut = "'".($regularButton ? ">{$b[1]}" : " title='".CoreUtils::aposEncode($b[1])."'>");
          $HTML .= "<button class='typcn typcn-{$b[0]}$WriteOut</button>";
        }
      }
    }
    $HTML .= '</div>';

    return $HTML;
  }

  /**
   * Approves this post and optionally notifies it's author
   */
  public function approve() {
    $this->lock = true;
    if (!$this->save())
      Response::dbError();

    LockedPost::record($this->id);

    if (UserPrefs::get('a_pcgearn', $this->reserver)){
      PCGSlotHistory::record($this->reserver->id, 'post_approved', null, [
        'id' => $this->id,
      ]);
      $this->reserver->syncPCGSlotCount();
    }

    if ($this->reserved_by !== Auth::$user->id)
      Notification::send($this->reserved_by, 'post-approved', ['id' => $this->id]);
  }
}
