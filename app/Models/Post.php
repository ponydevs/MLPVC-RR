<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Auth;
use App\CoreUtils;
use App\DB;
use App\DeviantArt;
use App\Logs;
use App\Models\Logs\Log;
use App\Permission;
use App\Posts;
use App\RegExp;
use App\Response;
use App\Time;
use App\UserPrefs;

/**
 * This is a blanket class for both requests and reservations.
 * Requests always have a non-null requested_by value which is to be used for post type detection.
 *
 * @property int      $id
 * @property int|null $old_id
 * @property string   $type
 * @property int      $season
 * @property int      $episode
 * @property int      $show_id
 * @property string   $preview
 * @property string   $fullsize
 * @property string   $label
 * @property string   $requested_by
 * @property DateTime $requested_at
 * @property string   $reserved_by
 * @property DateTime $reserved_at
 * @property string   $deviation_id
 * @property bool     $lock
 * @property DateTime $finished_at
 * @property bool     $broken
 * @property User     $reserver       (Via relations)
 * @property User     $requester      (Via relations)
 * @property DateTime $posted_at      (Via magic method)
 * @property string   $posted_by      (Via magic method)
 * @property User     $poster         (Via magic method)
 * @property Show     $show           (Via magic method)
 * @property string   $kind           (Via magic method)
 * @property bool     $finished       (Via magic method)
 * @property Log      $approval_entry (Via magic method)
 * @property bool     $is_request     (Via magic method)
 * @property bool     $is_reservation (Via magic method)
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

	public function add_post_time(){
		$this->posted_at = date('c');
	}

	public function post_deletion(){
		try {
			CoreUtils::socketEvent('post-delete',[
				'id' => $this->id,
			]);
		}
		catch (\Exception $e){
			CoreUtils::error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
		}

		Posts::clearTransferAttempts($this, 'del');
	}

	/* For Twig */
	public function getShow(){
		return $this->show;
	}

	public function get_posted_at(){
		return $this->is_request ? $this->requested_at : $this->reserved_at;
	}

	public function set_posted_at($value){
		if ($this->is_request)
			$this->requested_at = $value;
		else $this->reserved_at = $value;
	}

	public function get_posted_by(){
		return $this->is_request ? $this->requested_by : $this->reserved_by;
	}

	public function get_poster(){
		return User::find($this->posted_by);
	}

	public function get_finished(){
		return $this->deviation_id !== null && $this->reserved_by !== null;
	}

	public function get_is_request(){
		return $this->requested_by !== null;
	}

	public function get_is_reservation(){
		return !$this->is_request;
	}

	public function get_approval_entry(){
		$where_query = "type = 'post' AND id = :id";
		$where_bind = ['id' => $this->id];
		if ($this->old_id !== null){
			$where_query = "($where_query) OR (type = :kind AND old_id = :old_id)";
			$where_bind['kind'] = $this->kind;
			$where_bind['old_id'] = $this->old_id;
		}

		return DB::$instance->setModel(Log::class)->querySingle(
			"SELECT l.*
			FROM log__post_lock pl
			LEFT JOIN log l ON l.reftype = 'post_lock' AND l.refid = pl.entryid
			WHERE $where_query
			ORDER BY pl.entryid ASC
			LIMIT 1", $where_bind
		);
	}

	public function get_kind(){
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

	/**
	 * @deprecated
	 */
	public function getOldIdString():?string {
		if ($this->old_id === null)
			return null;

		return $this->kind.'-'.$this->old_id;
	}

	public function toURL(Show $Episode = null):string {
		if (empty($Episode))
			$Episode = $this->show;
		return $Episode->toURL().'#'.$this->getIdString();
	}

	public function toAnchorWithPreview(){
		$haslabel = !empty($this->label);
		$alt = $haslabel ? CoreUtils::escapeHTML($this->label) : 'No label';
		$slabel = $haslabel ? $this->processLabel() : "<em>$alt</em>";
		return "<a class='post-link with-preview' href='{$this->toURL()}'><img src='{$this->preview}' alt='$alt'><span>$slabel</span></a>";
	}

	public function toAnchor(string $label = null, Show $Episode = null, $newtab = false):string {
		if ($Episode === null)
			$Episode = $this->show;
		/** @var $Episode Show */
		$link = $this->toURL($Episode);
		if (empty($label))
			$label = $Episode->getID();
		else $label = htmlspecialchars($label);
		$target = $newtab ? 'target="_blank"' : '';
		return "<a href='$link' {$target}>$label</a>";
	}

	public function isTransferable(?int $ts = null):bool {
		if ($this->reserved_by === null)
			return true;
		return ($ts ?? time()) - $this->reserved_at->getTimestamp() >= Time::IN_SECONDS['day']*5;
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
		return $this->is_request && $this->deviation_id === null && $this->reserved_by !== null && $now - $this->reserved_at->getTimestamp() >= Time::IN_SECONDS['week']*3;
	}

	public function processLabel():string {
		$label = CoreUtils::escapeHTML($this->label);
		$label = preg_replace(new RegExp("(\\w)'(\\w)"), '$1&rsquo;$2', $label);
		$label = preg_replace(new RegExp("''"), '"', $label);
		$label = preg_replace(new RegExp('"([^"]+)"'), '&ldquo;$1&rdquo;', $label);
		$label = preg_replace(new RegExp('\.\.\.'), '&hellip;', $label);
		$label = preg_replace(new RegExp('(?:(f)ull[- ](b)od(?:y|ied)( version)?)','i'),'<strong class="color-darkblue">$1ull $2ody</strong>$3', $label);
		$label = preg_replace(new RegExp('(?:(f)ace[- ](o)nly( version)?)','i'),'<strong class="color-darkblue">$1ace $2nly</strong>$3', $label);
		$label = preg_replace(new RegExp('(?:(f)ull (s)cene?)','i'),'<strong class="color-darkblue">$1ull $2cene</strong>$3', $label);
		$label = preg_replace(new RegExp('(?:(e)ntire (s)cene?)','i'),'<strong class="color-darkblue">$1ntire $2cene</strong>$3', $label);
		$label = preg_replace(new RegExp('\[([\w\s]+ intensifies)\]','i'),'<span class="intensify">$1</span>', $label);
		return $label;
	}

	public function getFinishedImage(bool $view_only, string $cachebust = ''):string {
		$Deviation = DeviantArt::getCachedDeviation($this->deviation_id);
		if (empty($Deviation)){
			$ImageLink = $view_only ? $this->toURL() : "http://fav.me/{$this->deviation_id}";
			$Image = "<a class='image deviation error' href='$ImageLink'>Preview unavailable<br><small>Click to view</small></a>";
		}
		else {
			$alt = CoreUtils::aposEncode($Deviation->title);
			$ImageLink = $view_only ? $this->toURL() : "http://fav.me/{$Deviation->id}";
			$approved = $this->lock ? ' approved' : '';
			$Image = "<div class='image deviation$approved'><a href='$ImageLink'><img src='{$Deviation->preview}$cachebust' alt='$alt'>";
			if ($this->lock)
				$Image .= "<span class='approved-info' title='This submission has been accepted into the group gallery'></span>";
			$Image .= '</a></div>';
		}
		return $Image;
	}

	/**
	 * List item generator function for request & reservation generators
	 *
	 * TODO Turn into a view (eventually)
	 *
	 * @param bool $view_only      Only show the "View" button
	 * @param bool $cachebust_url  Append a random string to the image URL to force a re-fetch
	 * @param bool $enablePromises Output "promise" elements in place of all images (requires JS to display)
	 *
	 * @return string
	 * @throws \Exception
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
				$posted_at .= ' by '.($isRequester ? "<a href='/@".Auth::$user->name."'>You</a>" : $this->requester->toAnchor());
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
					$LogEntry = $this->approval_entry;
					if (!empty($LogEntry)){
						$approverIsNotReserver = $LogEntry->initiator !== null && $LogEntry->initiator !== $this->reserved_by;
						$approvedby = $isStaff && $LogEntry->initiator !== null
							? ' by '.(
								$approverIsNotReserver
								? (
									$this->is_request && $LogEntry->initiator === $this->requested_by
									? 'the requester'
									: $LogEntry->actor->toAnchor()
								)
								: 'the reserver'
							)
							: '';
						$locked_at = $approved ? "<em class='approve-date'>Approved <strong>".Time::tag($LogEntry->timestamp)."</strong>$approvedby</em>" : '';
					}
					else $locked_at = '<em class="info-line approve-date">Approval data unavilable</em>';
				}
				$post_type = !empty($this->type) ? '<em class="info-line">Posted in the <strong>'.self::REQUEST_TYPES[$this->type].'</strong> section</em>' : '';
				$HTML .= $post_label.$posted_at.$post_type.$reserved_at.$finished_at.$locked_at;

				if (!empty($this->fullsize))
					$HTML .= "<span class='info-line'><a href='{$this->fullsize}' class='original color-green' target='_blank' rel='noopener'><span class='typcn typcn-link'></span> Original image</a></span>";
				if (!$approved && Permission::sufficient('staff'))
					$HTML .= "<span class='info-line'><a href='{$this->reserver->getOpenSubmissionsURL()}' class='color-blue' target='_blank' rel='noopener'><span class='typcn typcn-arrow-forward'></span> View open submissions</a></span>";
			}
			else $HTML .= $post_label.$posted_at.$reserved_at;
		}
		else $HTML .= $post_label.$posted_at;

		if ($displayOverdue && ($isStaff || $isReserver))
			$HTML .= self::CONTESTABLE;

		if ($this->broken)
			$HTML .= self::BROKEN;

		$break = $this->broken ? 'class="admin-break"' : '';

		return "<li id='$ID' data-type='{$this->kind}' $break>$HTML".$this->getActionsHTML($view_only ? $postlink : false, $hide_reserved_status, $enablePromises).'</li>';
	}

	public function getLabelHTML():string {
		return !empty($this->label) ? '<span class="label'.(CoreUtils::contains($this->label, '"') ? ' noquotes' : '').'">'.$this->processLabel().'</span>' : '';
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
		$By = $hide_reserver_status ? null : $this->reserver;
		$requestedByUser = $this->is_request && Auth::$signed_in && $this->requested_by === Auth::$user->id;
		$isNotReserved = empty($By);
		$sameUser = Auth::$signed_in && $this->reserved_by === Auth::$user->id;
		$CanEdit = (empty($this->lock) && Permission::sufficient('staff')) || Permission::sufficient('developer') || ($requestedByUser && $isNotReserved);
		$Buttons = [];

		$HTML = Posts::getPostReserveButton($By, $view_only, false, $enablePromises);
		if (!empty($this->reserved_by)){
			$staffOrSameUser = ($sameUser && Permission::sufficient('member')) || Permission::sufficient('staff');
			if (!$this->finished){
				if (!$sameUser && Permission::sufficient('member') && $this->isTransferable() && !$this->isOverdue())
					$Buttons[] = ['user-add darkblue pls-transfer', 'Take on'];
				if ($staffOrSameUser){
					$Buttons[] = ['user-delete red cancel', 'Cancel Reservation'];
					$Buttons[] = ['attachment green finish', ($sameUser ? "I'm" : 'Mark as').' finished'];
				}
			}
			if ($this->finished && !$this->lock){
				if (Permission::sufficient('staff'))
					$Buttons[] = [
						(empty($this->preview) ? 'trash delete-only red' : 'media-eject orange').' unfinish', empty($this->preview)
							? 'Delete' : 'Unfinish'
					];
				if ($staffOrSameUser)
					$Buttons[] = ['tick green check', 'Check'];
			}
		}

		if (empty($this->lock) && empty($Buttons) && (Permission::sufficient('staff') || ($requestedByUser && $isNotReserved)))
			$Buttons[] = ['trash red delete', 'Delete'];
		if ($CanEdit)
			array_splice($Buttons, 0, 0, [['pencil darkblue edit', 'Edit']]);
		if ($this->lock && Permission::sufficient('staff'))
			$Buttons[] = ['lock-open orange unlock', 'Unlock'];

		$HTML .= "<div class='actions'>";
		if ($view_only === false){
			$Buttons[] = ['export blue share', 'Share'];
		}
		if (!empty($Buttons)){
			if ($view_only !== false)
				$HTML .= "<div><a href='$view_only' class='btn blue typcn typcn-arrow-forward'>View</a></div>";
			else {
				$regularButton = \count($Buttons) < 3;
				foreach ($Buttons as $b){
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
	public function approve(){
		$this->lock = true;
		if (!$this->save())
			Response::dbError();

		$postdata = [ 'id' => $this->id ];
		Logs::logAction('post_lock',$postdata);

		if (UserPrefs::get('a_pcgearn', $this->reserver)){
			PCGSlotHistory::record($this->reserver->id, 'post_approved', null, [
				'id' => $this->id,
			]);
			$this->reserver->syncPCGSlotCount();
		}

		if ($this->reserved_by !== Auth::$user->id)
			Notification::send($this->reserved_by, 'post-approved', $postdata);
	}
}
