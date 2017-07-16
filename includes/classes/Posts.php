<?php

namespace App;

use App\Models\Episode;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Request;
use App\Models\Reservation;
use App\Models\User;
use App\Exceptions\MismatchedProviderException;

class Posts {
	const
		TYPES = ['request', 'reservation'],
		REQUEST_TYPES = [
			'chr' => 'Characters',
			'obj' => 'Objects',
			'bg' => 'Backgrounds',
	];

	/**
	 * Retrieves requests & reservations for the episode specified
	 * Optionally lists broken posts
	 *
	 * @param Episode $Episode
	 * @param int     $only
	 * @param bool    $showBroken
	 *
	 * @return Post[]|Post[][]
	 */
	public static function get(Episode $Episode, int $only = null, bool $showBroken = false){
		$return = [];
		if ($only !== ONLY_RESERVATIONS){
			$return[] = Request::find('all', [
				'conditions'=> [
					'season = ? AND episode = ?'.($showBroken === false?' AND broken IS NOT true':''),
					$Episode->season,
					$Episode->episode
				],
				'order' => 'requested_at asc',
			]);
		}
		if ($only !== ONLY_REQUESTS){
			if ($showBroken === false)
				DB::$instance->where('broken != true');
			$return[] = Reservation::find('all', [
				'conditions'=> [
					'season = ? AND episode = ?'.($showBroken === false?' AND broken IS NOT true':''),
					$Episode->season,
					$Episode->episode
				],
				'order' => 'reserved_at asc',
			]);;
		}

		return $only ? $return[0] : $return;
	}

	/**
	 * Get list of most recent posts
	 *
	 * @param bool $wrap
	 *
	 * @return string
	 */
	public static function getMostRecentList($wrap = WRAP){
		$cols = 'id,season,episode,label,preview,lock,deviation_id,reserved_by,finished_at,broken,reserved_at';
		$RecentPosts = DB::$instance->disableAutoClass()->query(
			"SELECT * FROM
			(
				SELECT $cols, requested_by, requested_at AS posted FROM requests
				WHERE requested_at > NOW() - INTERVAL '20 DAYS'
				UNION ALL
				SELECT $cols, null AS requested_by, reserved_at AS posted FROM reservations
				WHERE reserved_at > NOW() - INTERVAL '20 DAYS'
			) t
			ORDER BY posted DESC
			LIMIT 20");

		$HTML = '';
		foreach ($RecentPosts as $Post){
			$is_request = !empty($Post['requested_by']);
			$className = '\\App\\Models\\'.($is_request ? 'Request' : 'Reservation');
			if (!$is_request)
				unset($Post['requested_by']);
			$HTML .= self::getLi(new $className($Post), true, false, true);
		}
		return $wrap ? "<ul>$HTML</ul>" : $HTML;
	}

	/**
	 * POST data validator function used when creating/editing posts
	 *
	 * @param string                   $thing "request"/"reservation"
	 * @param array                    $array Array to output the checked data into
	 * @param Request|Reservation|null $Post  Optional, exsting post to compare new data against
	 */
	public static function checkPostDetails($thing, array &$array, $Post = null){
		$editing = !empty($Post);

		$label = (new Input('label','string', [
			Input::IS_OPTIONAL => true,
			Input::IN_RANGE => [3,255],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_RANGE => 'The description must be between @min and @max characters'
			]
		]))->out();
		if ($label !== null){
			if (!$editing || $label !== $Post->label){
				CoreUtils::checkStringValidity($label,'The description',INVERSE_PRINTABLE_ASCII_PATTERN);
				$label = preg_replace(new RegExp("''"),'"',$label);
				CoreUtils::set($array, 'label', $label);
			}
		}
		else if (!$editing && $thing !== 'reservation')
			Response::fail('Description cannot be empty');
		else CoreUtils::set($array, 'label', null);

		if ($thing === 'request'){
			$type = (new Input('type',function($value){
				if (!in_array($value, ['chr', 'obj', 'bg']))
					return Input::ERROR_INVALID;
			}, [
				Input::IS_OPTIONAL => true,
				Input::CUSTOM_ERROR_MESSAGES => [
					Input::ERROR_INVALID => 'Request type (@value) is invalid'
				]
			]))->out();
			if ($type === null && !$editing)
				Response::fail('Missing request type');

			if (!$editing || (isset($type) && $type !== $Post->type))
				CoreUtils::set($array,'type',$type);

			if (Permission::sufficient('developer')){
				$reserved_at = self::validateReservedAt();
				if (isset($reserved_at)){
					if ($reserved_at !== strtotime($Post->reserved_at))
						CoreUtils::set($array,'reserved_at',date('c', $reserved_at));
				}
				else CoreUtils::set($array,'reserved_at',null);
			}
		}

		if (Permission::sufficient('developer')){
			$posted = (new Input('posted','timestamp', [
				Input::IS_OPTIONAL => true,
				Input::CUSTOM_ERROR_MESSAGES => [
					Input::ERROR_INVALID => '"Posted" timestamp (@value) is invalid',
				]
			]))->out();
			if (isset($posted) && $posted !== strtotime($Post->posted))
				CoreUtils::set($array,'posted',date('c', $posted));

			$finished_at = self::validateFinishedAt();
			if (isset($finished_at)){
				if ($finished_at !== strtotime($Post->finished_at))
					CoreUtils::set($array,'finished_at',date('c', $finished_at));
			}
			else CoreUtils::set($array,'finished_at',null);
		}
	}

	/**
	 * Check image URL in POST request
	 *
	 * @param string $image_url
	 * @param array|null $Post Existing post for comparison
	 *
	 * @return ImageProvider
	 */
	public static function checkImage($image_url, $Post = null){
		try {
			$Image = new ImageProvider($image_url);
		}
		catch (\Exception $e){ Response::fail($e->getMessage()); }


		foreach (Posts::TYPES as $type){
			if (!empty($Post->id))
				DB::$instance->where('r.id',$Post->id,'!=');
			/** @var $UsedUnder Post */
			$UsedUnder = DB::$instance
				->disableAutoClass()
				->join('episodes ep','r.season = ep.season AND r.episode = ep.episode','LEFT')
				->where('r.preview',$Image->preview)
				->getOne("{$type}s r",'r.id, ep.season, ep.episode, ep.twoparter');
			if (!empty($UsedUnder)){
				/** @var $UsedUnderPost Post */
				$className = '\App\Models\\'.CoreUtils::capitalize($type);
				$UsedUnderPost = new $className($UsedUnder);
				$UsedUnderEpisode = new Episode($UsedUnder);

				Response::fail("This exact image has already been used for a $type under ".$UsedUnderPost->toAnchor(null,$UsedUnderEpisode,true));
			}
		}

		return $Image;
	}

	/**
	 * Checks the image which allows a request to be finished
	 *
	 * @param string|null $ReserverID
	 *
	 * @return array
	 */
	public static function checkRequestFinishingImage($ReserverID = null){
		$deviation = (new Input('deviation','string', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Please specify a deviation URL',
			]
		]))->out();
		try {
			$Image = new ImageProvider($deviation, ['fav.me', 'dA']);

			foreach (Posts::TYPES as $what){
				if (DB::$instance->where('deviation_id', $Image->id)->has("{$what}s"))
					Response::fail("This exact deviation has already been marked as the finished version of a different $what");
			}

			$return = ['deviation_id' => $Image->id];
			$Deviation = DeviantArt::getCachedDeviation($Image->id);
			if (!empty($Deviation->author)){
				$Author = Users::get($Deviation->author, 'name');

				if (!empty($Author)){
					if (!isset($_POST['allow_overwrite_reserver']) && !empty($ReserverID) && $Author->id !== $ReserverID){
						$sameUser = Auth::$user->id === $ReserverID;
						$person = $sameUser ? 'you' : 'the user who reserved this post';
						Response::fail("You've linked to an image which was not submitted by $person. If this was intentional, press Continue to proceed with marking the post finished <b>but</b> note that it will make {$Author->name} the new reserver.".($sameUser
								? "<br><br>This means that you'll no longer be able to interact with this post until {$Author->name} or an administrator cancels the reservation on it."
								: ''), ['retry' => true]);
					}

					$return['reserved_by'] = $Author->id;
				}
			}

			if (CoreUtils::isDeviationInClub($return['deviation_id']) === true)
				$return['lock'] = true;

			return $return;
		}
		catch (MismatchedProviderException $e){
			Response::fail('The finished vector must be uploaded to DeviantArt, '.$e->getActualProvider().' links are not allowed');
		}
		catch (\Exception $e){ Response::fail($e->getMessage()); }
	}

	/**
	 * Generate HTML of requests for episode pages
	 *
	 * @param Post[]|null $Requests
	 * @param bool        $returnArranged Return an arranged array of posts instead of raw HTML
	 * @param bool        $lazyload       Output promise elements in place of deviation data
	 *
	 * @return string|array
	 * @throws \Exception
	 */
	public static function getRequestsSection(?array $Requests = null, bool $returnArranged = false, bool $lazyload = false){
		$Arranged = ['finished' => !$returnArranged ? '' : []];
		if (!$returnArranged){
			$Arranged['unfinished'] = [];
			$Arranged['unfinished']['bg'] =
			$Arranged['unfinished']['obj'] =
			$Arranged['unfinished']['chr'] = $Arranged['finished'];
		}
		else $Arranged['unfinished'] = $Arranged['finished'];

		$loaders = $Requests === null;

		if (!empty($Requests) && is_array($Requests)){
			foreach ($Requests as $Request){
				$HTML = !$returnArranged ? self::getLi($Request,false,false,$lazyload) : $Request;

				if (!$returnArranged){
					if ($Request->finished)
						$Arranged['finished'] .= $HTML;
					else $Arranged['unfinished'][$Request->type] .= $HTML;
				}
				else {
					$k = ($Request->finished?'':'un').'finished';
					$Arranged[$k][] = $HTML;
				}
			}
		}

		if ($returnArranged)
			return $Arranged;

		$Groups = '';
		foreach ($Arranged['unfinished'] as $g => $c)
			$Groups .= "<div class='group' id='group-$g'><h3>".self::REQUEST_TYPES[$g].":</h3><ul>$c</ul></div>";

		if (Permission::sufficient('user')){
			$makeRq = '<button id="request-btn" class="green">Make a request</button>';
			$reqForm = self::_getForm('request');
		}
		else $reqForm = $makeRq = '';

		$loading = $loaders ? ' loading' : '';

		return <<<HTML
<section id="requests" class="posts">
	<div class="unfinished$loading">
		<h2>List of Requests$makeRq</h2>
		$Groups
	</div>
	<div class="finished$loading">
		<h2>Finished Requests</h2>
		<ul>{$Arranged['finished']}</ul>
	</div>$reqForm
</section>
HTML;
	}


	/**
	 * Generate HTML of reservations for episode pages
	 *
	 * @param Post[]|null $Reservations
	 * @param bool        $returnArranged Return an arranged array of posts instead of raw HTML
	 * @param bool        $lazyload       Output promise elements in place of deviation data
	 *
	 * @return string|array
	 */
	public static function getReservationsSection(?array $Reservations = null, bool $returnArranged = false, bool $lazyload = false){
		$Arranged = [];
		$Arranged['unfinished'] =
		$Arranged['finished'] = !$returnArranged ? '' : [];

		$loaders = $Reservations === null;

		if (is_array($Reservations)){
			foreach ($Reservations as $Reservation){
				$k = ($Reservation->finished?'':'un').'finished';
				if (!$returnArranged)
					$Arranged[$k] .= self::getLi($Reservation,false,false,$lazyload);
				else $Arranged[$k][] = $Reservation;
			}
		}

		if ($returnArranged)
			return $Arranged;

		if (Permission::sufficient('member')){
			$makeRes = '<button id="reservation-btn" class="green">Make a reservation</button>';
			$resForm = self::_getForm('reservation');
		}
		else $resForm = $makeRes = '';
		$addRes = Permission::sufficient('staff') ? '<button id="add-reservation-btn" class="darkblue">Add a reservation</button>' :'';

		$loading = $loaders ? ' loading' : '';

		return <<<HTML
<section id="reservations" class="posts">
	<div class="unfinished$loading">
		<h2>List of Reservations$makeRes</h2>
		<ul>{$Arranged['unfinished']}</ul>
	</div>
	<div class="finished$loading">
		<h2>Finished Reservations$addRes</h2>
		<ul>{$Arranged['finished']}</ul>
	</div>$resForm
</section>

HTML;
	}

	/**
	 * Get Request / Reservation Submission Form HTML
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	private static function _getForm($type){
		$Type = strtoupper($type[0]).CoreUtils::substring($type,1);
		$optional = $type === 'reservation' ? 'optional, ' : '';
		$optreq = $type === 'reservation' ? '' : 'required';

		$HTML = <<<HTML
	<form class="hidden post-form" data-type="$type">
		<h2>Make a $type</h2>
		<div>
			<label>
				<span>$Type description ({$optional}3-255 chars)</span>
				<input type="text" name="label" pattern="^.{3,255}$" maxlength="255" $optreq>
			</label>
			<label>
				<span>Image URL</span>
				<input type="text" name="image_url" pattern="^.{2,255}$" required>&nbsp;
				<button type="button" class="check-img red typcn typcn-arrow-repeat">Check image</button><br>
			</label>
			<div class="img-preview">
				<div class="notice info">
					<p>Please click the <strong>Check image</strong> button after providing an URL to get a preview & verify if the link is correct.</p>
					<hr>
					<p class="keep">You can use a link from any of the <a href="/about#supported-providers" target="_blank">suppported image providers</a>.</p>
				</div>
			</div>
HTML;
		if ($type === 'request')
			$HTML .= <<<HTML
			<label>
				<span>$Type type</span>
				<select name="type" required>
					<option value="" style="display:none" selected>Choose one</option>
					<optgroup label="$Type types">
						<option value="chr">Character</option>
						<option value="obj">Object</option>
						<option value="bg">Background</option>
					</optgroup>
				</select>
			</label>

HTML;
		if (Permission::sufficient('developer')){
			$UNP = USERNAME_PATTERN;
			$HTML .= <<<HTML
			<label>
				<span>$Type as user</span>
				<input type="text" name="post_as" pattern="^$UNP$" maxlength="20" placeholder="Username" spellcheck="false">
			</label>

HTML;
		}

		$HTML .= <<<HTML
		</div>
		<button class="green">Submit $type</button> <button type="reset">Cancel</button>
	</form>
HTML;
		return $HTML;
	}

	/**
	 * @param Post        $Post
	 * @param string|null $sent_by
	 * @param string      $cols
	 *
	 * @return array|null
	 */
	public static function getTransferAttempts(Post $Post, $sent_by = null, $cols = 'read_at,sent_at'){
		if ($Post->reserved_by !== null)
			DB::$instance->where('recipient_id', $Post->reserved_by);
		if (!empty($sent_by))
			DB::$instance->where("data->>'user'", $sent_by);
		return DB::$instance
			->where('type', 'post-passon')
			->where("data->>'type'", $Post->kind)
			->where("data->>'id'", $Post->id)
			->orderBy('sent_at', NEWEST_FIRST)
			->get('notifications',null,$cols);
	}

	const TRANSFER_ATTEMPT_CLEAR_REASONS = [
		'del' => 'the post was deleted',
		'snatch' => 'the post was reserved by someone else',
		'deny' => 'the post was transferred to someone else',
		'perm' => 'the previous reserver could no longer act on the post',
		'free' => 'the post became free for anyone to reserve',
	];

	/**
	 * @param Post        $Post
	 * @param string      $reason
	 * @param User        $sent_by
	 *
	 * @throws \InvalidArgumentException
	 */
	public static function clearTransferAttempts(Post $Post, string $reason, ?User $sent_by = null){
		if (empty(self::TRANSFER_ATTEMPT_CLEAR_REASONS[$reason]))
			throw new \InvalidArgumentException("Invalid clear reason $reason");

		DB::$instance->where('read_at IS NULL');
		$TransferAttempts = Posts::getTransferAttempts($Post, $sent_by, 'id,data');
		if (!empty($TransferAttempts)){
			$SentFor = [];
			foreach ($TransferAttempts as $n){
				Notifications::safeMarkRead($n['id']);

				$data = JSON::decode($n['data']);
				if (!empty($SentFor[$data['user']][$reason]["{$data['type']}-{$data['id']}"]))
					continue;

				Notification::send($data['user'], "post-pass$reason", [
					'id' => $data['id'],
					'type' => $data['type'],
					'by' => Auth::$user->id,
				]);
				$SentFor[$data['user']][$reason]["{$data['type']}-{$data['id']}"] = true;
			}
		}
	}

	const CONTESTABLE = "<strong class='color-blue contest-note' title=\"Because this request was reserved more than 3 weeks ago it’s now available for other members to reserve\"><span class='typcn typcn-info-large'></span> Can be contested</strong>";
	const BROKEN = "<strong class='color-orange broken-note' title=\"The full size preview of this post was deemed unavailable and it is now marked as broken\"><span class='typcn typcn-plug'></span> Deemed broken</strong>";

	/**
	 * List ltem generator function for request & reservation generators
	 *
	 * @param Request|Reservation $Post
	 * @param bool                $view_only          Only show the "View" button
	 * @param bool                $cachebust_url      Append a random string to the image URL to force a re-fetch
	 * @param bool                $deviation_promises Output promise elements in place of cached deviation data
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getLi($Post, bool $view_only = false, bool $cachebust_url = false, bool $deviation_promises = false):string {
		$ID = $Post->getID();
		$alt = !empty($Post->label) ? CoreUtils::aposEncode($Post->label) : '';
		$postlink = $Post->toLink();
		$ImageLink = $view_only ? $postlink : $Post->fullsize;
		$cachebust = $cachebust_url ? '?t='.time() : '';
		$Image = "<div class='image screencap'><a href='$ImageLink'><img src='{$Post->preview}$cachebust' alt='$alt'></a></div>";
		$post_label = self::_getPostLabel($Post);
		$permalink = "<a href='$postlink'>".Time::tag($Post->posted).'</a>';
		$isStaff = Permission::sufficient('staff');

		$posted_at = '<em class="post-date">';
		if ($Post->is_request){
			$isRequester = Auth::$signed_in && $Post->requested_by === Auth::$user->id;
			$isReserver = Auth::$signed_in && $Post->reserved_by === Auth::$user->id;
			$overdue = Permission::sufficient('member') && $Post->isOverdue();

			$posted_at .= "Requested $permalink";
			if (Auth::$signed_in && ($isStaff || $isRequester || $isReserver))
				$posted_at .= ' by '.($isRequester ? "<a href='/@".Auth::$user->name."'>You</a>" : User::find($Post->requested_by)->getProfileLink());
		}
		else {
			$overdue = false;
			$posted_at .= "Reserved $permalink";
		}
		$posted_at .= '</em>';

		$hide_reserved_status = $Post->reserved_by === null || ($overdue && !$isReserver && !$isStaff);
		if ($Post->reserved_by !== null){
			$reserved_by = $overdue && !$isReserver ? ' by '.$Post->reserver->getProfileLink() : '';
			$reserved_at = $Post->is_request && $Post->reserved_at !== null && !($hide_reserved_status && Permission::insufficient('staff'))
				? "<em class='reserve-date'>Reserved <strong>".Time::tag($Post->reserved_at)."</strong>$reserved_by</em>"
				: '';
			if ($Post->finished){
				$approved = $Post->lock;
				if ($deviation_promises)
					$Image = "<div class='image deviation'><div class='post-deviation-promise' data-post='{$Post->getID()}' data-viewonly='$view_only'></div></div>";
				else $Image = $Post->getFinishedImage($view_only, $cachebust);
				$finished_at = !empty($Post->finished_at)
					? "<em class='finish-date'>Finished <strong>".Time::tag($Post->finished_at).'</strong></em>'
					: '';
				$locked_at = '';
				if ($approved){
					/** @var $LogEntry array */
					$LogEntry = DB::$instance->querySingle(
						"SELECT l.timestamp, l.initiator
						FROM log__post_lock pl
						LEFT JOIN log l ON l.reftype = 'post_lock' AND l.refid = pl.entryid
						WHERE type = ? AND id = ?
						ORDER BY pl.entryid ASC
						LIMIT 1", [$Post->kind, $Post->id]
					);
					$approverIsNotReserver = isset($LogEntry['initiator']) && $LogEntry['initiator'] !== $Post->reserved_by;
					$approvedby = $isStaff && isset($LogEntry['initiator'])
						? ' by '.(
							$approverIsNotReserver
							? (
								$Post->is_request && $LogEntry['initiator'] === $Post->requested_by
								? 'the requester'
								: User::find($LogEntry['initiator'])->getProfileLink()
							)
							: 'the reserver'
						)
						: '';
					$locked_at = $approved ? "<em class='approve-date'>Approved <strong>".Time::tag(strtotime($LogEntry['timestamp']))."</strong>$approvedby</em>" : '';
				}
				$Image .= $post_label.$posted_at.$reserved_at.$finished_at.$locked_at;
				if (!empty($Post->fullsize))
					$Image .= "<a href='{$Post->fullsize}' class='original color-green' target='_blank' rel='noopener'><span class='typcn typcn-link'></span> Original image</a>";
			}
			else $Image .= $post_label.$posted_at.$reserved_at;
		}
		else $Image .= $post_label.$posted_at;

		if ($overdue && ($isStaff || $isReserver))
			$Image .= self::CONTESTABLE;

		if ($Post->broken)
			$Image .= self::BROKEN;

		$break = $Post->broken ? 'class="admin-break"' : '';
		return "<li id='$ID' $break>$Image".self::_getPostActions($Post, $view_only ? $postlink : false, $hide_reserved_status).'</li>';
	}

	/**
	 * List ltem generator function for reservation suggestions
	 * This function assumes that the post it's being used for is not reserved or it can be contested.
	 *
	 * @param Request $Request
	 *
	 * @return string
	 */
	public static function getSuggestionLi(Request $Request):string {
		$escapedLabel = CoreUtils::aposEncode($Request->label);
		$label = self::_getPostLabel($Request);
		$time_ago = Time::tag($Request->posted);
		$cat = Posts::REQUEST_TYPES[$Request->type];
		$reserve = Permission::sufficient('member') ? self::getPostReserveButton($Request, null, false) : "<div><a href='{$Request->toLink()}' class='btn blue typcn typcn-arrow-forward'>View on episode page</a></div>";
		return <<<HTML
<li id="request-{$Request->id}">
	<div class="image screencap">
		<a href="{$Request->fullsize}" target="_blank" rel="noopener">
			<img src="{$Request->fullsize}" alt="{$escapedLabel}">
		</a>
	</div>
	$label
	<em class="post-date">Requested <a href="{$Request->toLink()}">$time_ago</a> under {$Request->toAnchor()}</em>
	<em class="category">Category: {$cat}</em>
	$reserve
</li>
HTML;

	}

	/**
	 * @param Post      $Post
	 * @param User|null $reservedBy
	 * @param bool      $view_only
	 *
	 * @return string
	 */
	public static function getPostReserveButton(Post $Post, $reservedBy, $view_only):string {
		if (empty($reservedBy))
			return Permission::sufficient('member') && !$view_only ? "<button class='reserve-request typcn typcn-user-add'>Reserve</button>" : '';
		else {
			$dAlink = $reservedBy->getProfileLink(User::LINKFORMAT_FULL);
			$vectorapp = $reservedBy->getVectorAppClassName();
			if (!empty($vectorapp))
				$vectorapp .= "' title='Uses ".$reservedBy->getVectorAppReadableName().' to make vectors';
			return "<div class='reserver$vectorapp'>$dAlink</div>";
		}
	}

	/**
	 * @param Post $Post
	 *
	 * @return string
	 */
	private static function _getPostLabel(Post $Post):string {
		return !empty($Post->label) ? '<span class="label'.(strpos($Post->label,'"') !== false?' noquotes':'').'">'.$Post->processLabel().'</span>' : '';
	}

	/**
	 * Generate HTML for post action buttons
	 *
	 * @param Request|Reservation $Post
	 * @param false|string        $view_only            Only show the "View" button
     *                                                  Contains HREF attribute of button if string
	 * @param bool                $hide_reserver_status
	 *
	 * @return string
	 */
	private static function _getPostActions($Post, $view_only, bool $hide_reserver_status = true):string {
		$By = $hide_reserver_status ? false : $Post->reserver;
		$requestedByUser = $Post->is_request && Auth::$signed_in && $Post->requested_by === Auth::$user->id;
		$isNotReserved = empty($By);
		$sameUser = Auth::$signed_in && $Post->reserved_by === Auth::$user->id;
		$CanEdit = (empty($Post->lock) && Permission::sufficient('staff')) || Permission::sufficient('developer') || ($requestedByUser && $isNotReserved);
		$Buttons = [];

		$HTML = self::getPostReserveButton($Post, $By, $view_only);
		if (!empty($Post->reserved_by)){
			$staffOrSameUser = ($sameUser && Permission::sufficient('member')) || Permission::sufficient('staff');
			if (!$Post->finished){
				if (!$sameUser && Permission::sufficient('member') && $Post->isTransferable() && !$Post->isOverdue())
					$Buttons[] = ['user-add darkblue pls-transfer', 'Take on'];
				if ($staffOrSameUser){
					$Buttons[] = ['user-delete red cancel', 'Cancel Reservation'];
					$Buttons[] = ['attachment green finish', ($sameUser ? "I'm" : 'Mark as').' finished'];
				}
			}
			if ($Post->finished && !$Post->lock){
				if (Permission::sufficient('staff'))
					$Buttons[] = [(empty($Post->preview)?'trash delete-only red':'media-eject orange').' unfinish', empty($Post->preview)?'Delete':'Unfinish'];
				if ($staffOrSameUser)
					$Buttons[] = ['tick green check', 'Check'];
			}
		}

		if (empty($Post->lock) && empty($Buttons) && (Permission::sufficient('staff') || ($requestedByUser && $isNotReserved)))
			$Buttons[] = ['trash red delete', 'Delete'];
		if ($CanEdit)
			array_splice($Buttons,0,0, [['pencil darkblue edit', 'Edit']]);
		if ($Post->lock && Permission::sufficient('staff'))
			$Buttons[] = ['lock-open orange unlock', 'Unlock'];

		$HTML .= "<div class='actions'>";
		if (!$view_only)
			$Buttons[] = ['export blue share', 'Share'];
		if (!empty($Buttons)){
			if ($view_only)
				$HTML .="<div><a href='$view_only' class='btn blue typcn typcn-arrow-forward'>View</a></div>";
			else {
				$regularButton = count($Buttons) <3;
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
	 * Approves a specific post and optionally notifies it's author
	 *
	 * @param string $type         request/reservation
	 * @param int    $id           post id
	 * @param string $notifyUserID id of user to notify
	 *
	 * @return array
	 */
	public static function approve($type, $id, $notifyUserID = null){
		if (!DB::$instance->where('id', $id)->update("{$type}s", ['lock' => true]))
			Response::dbError();

		$postdata = [
			'type' => $type,
			'id' => $id
		];
		Logs::logAction('post_lock',$postdata);
		if (!empty($notifyUserID))
			Notification::send($notifyUserID, 'post-approved', $postdata);

		return $postdata;
	}

	public static function checkReserveAs(&$update){
		if (Permission::sufficient('developer')){
			$reserve_as = Posts::validatePostAs();
			if ($reserve_as !== null){
				$User = Users::get($reserve_as, 'name');
				if (empty($User))
					Response::fail('User to reserve as does not exist');
				if (!isset($_POST['screwit']) && !Permission::sufficient('member', $User->role))
					Response::fail('The specified user does not have permission to reserve posts, continue anyway?', ['retry' => true]);

				$update['reserved_by'] = $User->id;
			}
		}
	}

	public static function validateImageURL(){
		return (new Input('image_url','string', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Please provide an image URL.',
			]
		]))->out();
	}

	public static function validatePostAs(){
		return Users::validateName('post_as', [
			Input::ERROR_INVALID => '"Post as" username (@value) is invalid',
		]);
	}

	public static function validateReservedAt(){
		return (new Input('reserved_at','timestamp', [
			Input::IS_OPTIONAL => true,
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_INVALID => '"Reserved at" timestamp (@value) is invalid',
			]
		]))->out();
	}

	public static function validateFinishedAt(){
		return (new Input('finished_at','timestamp', [
			Input::IS_OPTIONAL => true,
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_INVALID => '"Finished at" timestamp (@value) is invalid',
			]
		]))->out();
	}
}
