<?php

namespace App;

use App\Models\Episode;
use App\Models\Notification;
use App\Models\PCGSlotHistory;
use App\Models\Post;
use App\Models\Request;
use App\Models\Reservation;
use App\Models\User;
use App\Exceptions\MismatchedProviderException;

class Posts {
	public const
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
				'order' => 'finished_at asc, requested_at asc',
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
				'order' => 'finished_at asc, reserved_at asc',
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
				SELECT $cols, type, requested_by, requested_at AS posted_at FROM requests
				WHERE requested_at > NOW() - INTERVAL '20 DAYS'
				UNION ALL
				SELECT $cols, null AS type, null AS requested_by, reserved_at AS posted_at FROM reservations
				WHERE reserved_at > NOW() - INTERVAL '20 DAYS'
			) t
			ORDER BY posted_at DESC
			LIMIT 20");

		$HTML = '';
		foreach ($RecentPosts as $Post){
			$is_request = !empty($Post['requested_by']);
			$className = '\\App\\Models\\'.($is_request ? 'Request' : 'Reservation');
			if (!$is_request)
				unset($Post['requested_by'], $Post['type']);
			/** @var $post Post */
			$post = new $className($Post);
			$HTML .= $post->getLi(true, false, LAZYLOAD);
		}
		return $wrap ? "<ul>$HTML</ul>" : $HTML;
	}

	/**
	 * POST data validator function used when creating/editing posts
	 *
	 * @param string                   $thing  "request"/"reservation"
	 * @param array|object             $target Array or object to output the checked data into
	 * @param Request|Reservation|null $Post   Optional, exsting post to compare new data against
	 */
	public static function checkPostDetails($thing, &$target, $Post = null){
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
				CoreUtils::set($target, 'label', $label);
			}
		}
		else if (!$editing && $thing !== 'reservation')
			Response::fail('Description cannot be empty');
		else CoreUtils::set($target, 'label', null);

		if ($thing === 'request'){
			$type = (new Input('type',function($value){
				if (!isset(self::REQUEST_TYPES[$value]))
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
				CoreUtils::set($target,'type',$type);

			if (Permission::sufficient('developer')){
				$reserved_at = self::validateReservedAt();
				if (isset($reserved_at)){
					if ($reserved_at !== strtotime($Post->reserved_at))
						CoreUtils::set($target,'reserved_at',date('c', $reserved_at));
				}
				else CoreUtils::set($target,'reserved_at',null);
			}
		}

		if (Permission::sufficient('developer')){
			$posted = (new Input('posted_at','timestamp', [
				Input::IS_OPTIONAL => true,
				Input::CUSTOM_ERROR_MESSAGES => [
					Input::ERROR_INVALID => '"Posted" timestamp (@value) is invalid',
				]
			]))->out();
			if (isset($posted) && $posted !== strtotime($Post->posted_at))
				CoreUtils::set($target,'posted_at',date('c', $posted));

			$finished_at = self::validateFinishedAt();
			if (isset($finished_at)){
				if ($finished_at !== strtotime($Post->finished_at))
					CoreUtils::set($target,'finished_at',date('c', $finished_at));
			}
			else CoreUtils::set($target,'finished_at',null);
		}
	}

	/**
	 * Check image URL in POST request
	 *
	 * @param string    $image_url
	 * @param Post|null $Post      Existing post for comparison
	 *
	 * @return ImageProvider
	 */
	public static function checkImage($image_url, $Post = null){
		try {
			$Image = new ImageProvider($image_url);
		}
		catch (\Exception $e){ Response::fail($e->getMessage()); }


		foreach (self::TYPES as $type){
			/** @noinspection DisconnectedForeachInstructionInspection */
			if ($Post !== null)
				DB::$instance->where('id',$Post->id,'!=');
			/** @var $UsedUnder Post */
			$UsedUnder = DB::$instance->where('preview',$Image->preview)->getOne("{$type}s");
			if (!empty($UsedUnder))
				Response::fail('This exact image has already been used for a '.$UsedUnder->toAnchor($type,null,true).' under '.$UsedUnder->ep->toAnchor());
		}

		return $Image;
	}

	/**
	 * Checks the image which allows a post to be finished
	 *
	 * @param string|null $ReserverID
	 *
	 * @return array
	 */
	public static function checkPostFinishingImage($ReserverID = null){
		$deviation = (new Input('deviation','string', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Please specify a deviation URL',
			]
		]))->out();
		try {
			$Image = new ImageProvider($deviation, ImageProvider::PROV_DEVIATION);

			foreach (self::TYPES as $what){
				if (DB::$instance->where('deviation_id', $Image->id)->has("{$what}s"))
					Response::fail("This exact deviation has already been marked as the finished version of a different $what");
			}

			$return = ['deviation_id' => $Image->id];
			$Deviation = DeviantArt::getCachedDeviation($Image->id);
			if (!empty($Deviation->author)){
				$Author = Users::get($Deviation->author, 'name');

				if (empty($Author))
					Response::fail("Could not fetch local user data for username: $Deviation->author");

				if (!isset($_POST['allow_overwrite_reserver']) && !empty($ReserverID) && $Author->id !== $ReserverID){
					$sameUser = Auth::$user->id === $ReserverID;
					$person = $sameUser ? 'you' : 'the user who reserved this post';
					Response::fail("You've linked to an image which was not submitted by $person. If this was intentional, press Continue to proceed with marking the post finished <b>but</b> note that it will make {$Author->name} the new reserver.".($sameUser
							? "<br><br>This means that you'll no longer be able to interact with this post until {$Author->name} or an administrator cancels the reservation on it."
							: ''), ['retry' => true]);
				}

				$return['reserved_by'] = $Author->id;
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

		if (!empty($Requests) && \is_array($Requests)){
			foreach ($Requests as $Request){
				$HTML = !$returnArranged ? $Request->getLi(false, false, $lazyload) : $Request;

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

		if (Permission::sufficient('user') && UserPrefs::get('a_postreq', Auth::$user)){
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

		if (\is_array($Reservations)){
			foreach ($Reservations as $Reservation){
				$k = ($Reservation->finished?'':'un').'finished';
				if (!$returnArranged)
					$Arranged[$k] .= $Reservation->getLi(false, false, $lazyload);
				else $Arranged[$k][] = $Reservation;
			}
		}

		if ($returnArranged)
			return $Arranged;

		if (Permission::sufficient('member') && UserPrefs::get('a_postres', Auth::$user)){
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
		$Type = strtoupper($type[0]).mb_substr($type,1);
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
				<input type="text" name="image_url" pattern="^.{2,255}$" required>
			</label>
			<div class="img-preview">
				<div class="notice info">
					<p>Please click the <strong class="color-red"><span class="typcn typcn-arrow-repeat"></span> Check image</strong> button (at the end of the form) after providing an URL to get a preview & verify that the link is correct.</p>
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
				<input type="text" name="post_as" pattern="^\s*$UNP\s*$" maxlength="20" placeholder="Username" spellcheck="false">
			</label>
			<label>
				<span>$Type timestamp</span>
				<input type="text" name="posted_at" placeholder="time()" spellcheck="false" autocomplete="off">
			</label>

HTML;
		}

		$HTML .= <<<HTML
		</div>
		
		<button class="green submit" disabled>Submit $type</button>
		<button type="button" class="check-img red typcn typcn-arrow-repeat">Check image</button>
		<button type="reset">Cancel</button>
	</form>
HTML;
		return $HTML;
	}

	/**
	 * @param Post      $Post
	 * @param User|null $sent_by
	 * @param string    $cols
	 *
	 * @return Notification[]|null
	 */
	public static function getTransferAttempts(Post $Post, ?User $sent_by = null, $cols = 'read_at,sent_at'){
		if ($Post->reserved_by !== null)
			DB::$instance->where('recipient_id', $Post->reserved_by);
		if (!empty($sent_by))
			DB::$instance->where("data->>'user'", $sent_by->id);
		return DB::$instance
			->where('type', 'post-passon')
			->where("data->>'type'", $Post->kind)
			->where("data->>'id'", $Post->id)
			->orderBy('sent_at', NEWEST_FIRST)
			->get('notifications',null,$cols);
	}

	public const TRANSFER_ATTEMPT_CLEAR_REASONS = [
		'del' => 'the post was deleted',
		'snatch' => 'the post was reserved by someone else',
		'deny' => 'the post was transferred to someone else',
		'perm' => 'the previous reserver could no longer act on the post',
		'free' => 'the post became free for anyone to reserve',
	];

	/**
	 * @param Post      $Post
	 * @param string    $reason
	 * @param User|null $sent_by
	 *
	 * @throws \InvalidArgumentException
	 */
	public static function clearTransferAttempts(Post $Post, string $reason, ?User $sent_by = null){
		if (empty(self::TRANSFER_ATTEMPT_CLEAR_REASONS[$reason]))
			throw new \InvalidArgumentException("Invalid clear reason $reason");

		DB::$instance->where('read_at IS NULL');
		$TransferAttempts = self::getTransferAttempts($Post, $sent_by, 'id,data');
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

	public const CONTESTABLE = "<strong class='color-blue contest-note' title=\"Because this request was reserved more than 3 weeks ago it’s now available for other members to reserve\"><span class='typcn typcn-info-large'></span> Can be contested</strong>";
	public const BROKEN = "<strong class='color-orange broken-note' title=\"The full size preview of this post was deemed unavailable and it is now marked as broken\"><span class='typcn typcn-plug'></span> Deemed broken</strong>";

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
		$label = $Request->getLabelHTML();
		$time_ago = Time::tag($Request->posted_at);
		$cat = self::REQUEST_TYPES[$Request->type];
		$reserve = Permission::sufficient('member')
			? self::getPostReserveButton($Request->reserver, false, true)
			: "<div><a href='{$Request->toURL()}' class='btn blue typcn typcn-arrow-forward'>View on episode page</a></div>";
		return <<<HTML
<li id="request-{$Request->id}">
	<div class="image screencap">
		<a href="{$Request->fullsize}" target="_blank" rel="noopener">
			<img src="{$Request->fullsize}" alt="{$escapedLabel}">
		</a>
	</div>
	$label
	<em class="post-date">Requested <a href="{$Request->toURL()}">$time_ago</a> under {$Request->toAnchor()}</em>
	<em class="category">Category: {$cat}</em>
	$reserve
</li>
HTML;

	}

	/**
	 * @param User|null   $reservedBy
	 * @param bool|string $view_only
	 * @param bool        $forceAvailable
	 * @param bool        $enablePromises
	 *
	 * @return string
	 */
	public static function getPostReserveButton($reservedBy, $view_only, bool $forceAvailable = false, bool $enablePromises = false):string {
		if (empty($reservedBy) || $forceAvailable)
			return Permission::sufficient('member') && $view_only === false && UserPrefs::get('a_reserve', Auth::$user) ? "<button class='reserve-request typcn typcn-user-add'>Reserve</button>" : '';

		$dAlink = $reservedBy->toAnchor(User::WITH_AVATAR, $enablePromises);
		$vectorapp = $reservedBy->getVectorAppClassName();
		if (!empty($vectorapp))
			$vectorapp .= "' title='Uses ".$reservedBy->getVectorAppReadableName().' to make vectors';
		return "<div class='reserver$vectorapp'>$dAlink</div>";
	}

	/**
	 * @param Request|Reservation $post
	 * @param bool                $view_only
	 * @param bool                $cachebust_url
	 * @param bool                $enablePromises
	 *
	 * @return string
	 */
	public static function getLi($post, bool $view_only, bool $cachebust_url, bool $enablePromises):string {
		$ID = $post->getID();
		$alt = !empty($post->label) ? CoreUtils::aposEncode($post->label) : '';
		$postlink = $post->toURL();
		$ImageLink = $view_only ? $postlink : $post->fullsize;
		$cachebust = $cachebust_url ? '?t='.time() : '';
		$HTML = "<div class='image screencap'>".(
			$enablePromises
				? "<div class='post-image-promise' data-href='$ImageLink' data-src='{$post->preview}$cachebust'></div>"
				: "<a href='$ImageLink'><img src='{$post->preview}$cachebust' alt='$alt'></a>"
			).'</div>';
		$post_label = $post->getLabelHTML();
		$permalink = "<a href='$postlink'>".Time::tag($post->posted_at).'</a>';
		$isStaff = Permission::sufficient('staff');

		$posted_at = '<em class="post-date">';
		if ($post->is_request){
			$isRequester = Auth::$signed_in && $post->requested_by === Auth::$user->id;
			$isReserver = Auth::$signed_in && $post->reserved_by === Auth::$user->id;
			$displayOverdue = Permission::sufficient('member') && $post->isOverdue();

			$posted_at .= "Requested $permalink";
			if (Auth::$signed_in && ($isStaff || $isRequester || $isReserver)){
				$posted_at .= ' by '.($isRequester ? "<a href='/@".Auth::$user->name."'>You</a>" : $post->requester->toAnchor());
			}
		}
		else {
			$displayOverdue = false;
			$posted_at .= "Reserved $permalink";
		}
		$posted_at .= '</em>';

		$hide_reserved_status = $post->reserved_by === null || ($displayOverdue && !$isReserver && !$isStaff);
		if ($post->reserved_by !== null){
			$reserved_by = $displayOverdue && !$isReserver ? ' by '.$post->reserver->toAnchor() : '';
			$reserved_at = $post->is_request && $post->reserved_at !== null && !($hide_reserved_status && Permission::insufficient('staff'))
				? "<em class='reserve-date'>Reserved <strong>".Time::tag($post->reserved_at)."</strong>$reserved_by</em>"
				: '';
			if ($post->finished){
				$approved = $post->lock;
				if ($enablePromises){
					$view_only_promise = $view_only ? "data-viewonly='$view_only'" : '';
					$HTML = "<div class='image deviation'><div class='post-deviation-promise' data-post='{$post->getID()}' $view_only_promise></div></div>";
				}
				else $HTML = $post->getFinishedImage($view_only, $cachebust);
				$finished_at = $post->finished_at !== null
					? "<em class='finish-date'>Finished <strong>".Time::tag($post->finished_at).'</strong></em>'
					: '';
				$locked_at = '';
				if ($approved){
					$LogEntry = $post->approval_entry;
					if (!empty($LogEntry)){
						$approverIsNotReserver = $LogEntry->initiator !== null && $LogEntry->initiator !== $post->reserved_by;
						$approvedby = $isStaff && $LogEntry->initiator !== null
							? ' by '.(
								$approverIsNotReserver
								? (
									$post->is_request && $LogEntry->initiator === $post->requested_by
									? 'the requester'
									: $LogEntry->actor->toAnchor()
								)
								: 'the reserver'
							)
							: '';
						$locked_at = $approved ? "<em class='approve-date'>Approved <strong>".Time::tag($LogEntry->timestamp)."</strong>$approvedby</em>" : '';
					}
					else $locked_at = '<em class="approve-date">Approval data unavilable</em>';
				}
				$post_type = $post->is_request ? '<em>Posted in the <strong>'.self::REQUEST_TYPES[$post->type].'</strong> section</em>' : '';
				$HTML .= $post_label.$posted_at.$post_type.$reserved_at.$finished_at.$locked_at;
				if (!empty($post->fullsize)){
					$HTML .= "<span><a href='{$post->fullsize}' class='original color-green' target='_blank' rel='noopener'><span class='typcn typcn-link'></span> Original image</a></span>";
				}
			}
			else $HTML .= $post_label.$posted_at.$reserved_at;
		}
		else $HTML .= $post_label.$posted_at;

		if ($displayOverdue && ($isStaff || $isReserver))
			$HTML .= self::CONTESTABLE;

		if ($post->broken)
			$HTML .= self::BROKEN;

		$break = $post->broken ? 'class="admin-break"' : '';

		return "<li id='$ID' $break>$HTML".$post->getActionsHTML($view_only ? $postlink : false, $hide_reserved_status, $enablePromises).'</li>';
	}

	/**
	 * Approves a specific post and optionally notifies it's author
	 *
	 * @param string $type         request/reservation
	 * @param int    $id           post id
	 */
	public static function approve($type, $id){
		if (!DB::$instance->where('id', $id)->update("{$type}s", ['lock' => true]))
			Response::dbError();

		$postdata = [
			'type' => $type,
			'id' => $id
		];
		Logs::logAction('post_lock',$postdata);

		/** @var $Post Post */
		$Post = DB::$instance->where('id', $id)->getOne("{$type}s");
		if (UserPrefs::get('a_pcgearn', $Post->reserver)){
			PCGSlotHistory::record($Post->reserver->id, 'post_approved', null, [
				'type' => $Post->kind,
				'id' => $Post->id,
			]);
			$Post->reserver->syncPCGSlotCount();
		}

		if ($Post->reserved_by !== Auth::$user->id)
			Notification::send($Post->reserved_by, 'post-approved', $postdata);
	}

	public static function checkReserveAs(&$update){
		if (Permission::sufficient('developer')){
			$reserve_as = self::validatePostAs();
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

	public static function validateImageURL():string {
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

	public static function validatePostedAt(){
		return (new Input('posted_at','timestamp', [
			Input::IS_OPTIONAL => true,
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_INVALID => '"Posted at" timestamp (@value) is invalid',
			]
		]))->out();
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
