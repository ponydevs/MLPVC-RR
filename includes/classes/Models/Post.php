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
 * @property Episode  $ep             (Via magic method)
 * @property string   $kind           (Via magic method)
 * @property bool     $finished       (Via magic method)
 * @property Log      $approval_entry (Via magic method)
 * @property bool     $is_request     (Via magic method)
 * @property bool     $is_reservation (Via magic method)
 * @method static Post|Post[] find(...$args)
 * @method static Post find_by_deviation_id(string $deviation_id)
 * @method static Post find_by_preview(string $preview_url)
 */
class Post extends NSModel implements LinkableInterface {
	const ORDER_BY_POSTED_AT = 'CASE WHEN requested_by IS NOT NULL THEN requested_at ELSE reserved_at END';
	public static $belongs_to = [
		['reserver', 'class' => 'User', 'foreign_key' => 'reserved_by'],
		['requester', 'class' => 'User', 'foreign_key' => 'requested_by'],
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
		if ($this->old_id !== null){
			$whereQuery = 'type = ? AND old_id = ?';
			$whereBind = [$this->kind, $this->old_id];
		}
		else {
			$whereQuery = 'id = ?';
			$whereBind = [$this->id];
		}

		return DB::$instance->setModel(Log::class)->querySingle(
			"SELECT l.*
			FROM log__post_lock pl
			LEFT JOIN log l ON l.reftype = 'post_lock' AND l.refid = pl.entryid
			WHERE $whereQuery
			ORDER BY pl.entryid ASC
			LIMIT 1", $whereBind
		);
	}

	public function get_kind(){
		return $this->is_request ? 'request' : 'reservation';
	}

	public function get_ep(){
		return Episode::find_by_season_and_episode($this->season, $this->episode);
	}

	public function getID():string {
		return 'post-'.$this->id;
	}

	/**
	 * @deprecated
	 */
	public function getOldID():?string {
		if ($this->old_id === null)
			return null;

		return $this->kind.'-'.$this->old_id;
	}

	public function toURL(Episode $Episode = null):string {
		if (empty($Episode))
			$Episode = $this->ep;
		return $Episode->toURL().'#'.$this->getID();
	}

	public function toAnchorWithPreview(){
		$haslabel = !empty($this->label);
		$alt = $haslabel ? CoreUtils::escapeHTML($this->label) : 'No label';
		$slabel = $haslabel ? $this->processLabel() : "<em>$alt</em>";
		return "<a class='post-link with-preview' href='{$this->toURL()}'><img src='{$this->preview}' alt='$alt'><span>$slabel</span></a>";
	}

	public function toAnchor(string $label = null, Episode $Episode = null, $newtab = false):string {
		if ($Episode === null)
			$Episode = $this->ep;
		/** @var $Episode Episode */
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
	 * List ltem generator function for request & reservation generators
	 *
	 * @param bool $view_only      Only show the "View" button
	 * @param bool $cachebust_url  Append a random string to the image URL to force a re-fetch
	 * @param bool $enablePromises Output "promise" elements in place of all images (requires JS to display)
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getLi(bool $view_only = false, bool $cachebust_url = false, bool $enablePromises = false):string {
		/** @noinspection PhpParamsInspection */
		return Posts::getLi($this, $view_only, $cachebust_url, $enablePromises);
	}

	public function getLabelHTML():string {
		return !empty($this->label) ? '<span class="label'.(strpos($this->label, '"') !== false ? ' noquotes' : '').'">'.$this->processLabel().'</span>' : '';
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
	public function getActionsHTML($view_only, bool $hide_reserver_status = true, bool $enablePromises):string {
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
