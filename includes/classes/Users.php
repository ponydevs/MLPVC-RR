<?php

namespace App;

use App\Models\Logs\DANameChange;
use App\Models\Post;
use App\Models\Request;
use App\Models\Reservation;
use App\Models\Session;
use App\Models\User;
use App\Exceptions\CURLRequestException;

class Users {
	// Global cache for storing user details
	public static $_USER_CACHE = [];
	public static $_PREF_CACHE = [];

	/**
	 * User Information Retriever
	 * --------------------------
	 * Gets a single row from the 'users' database where $column is equal to $value
	 * Returns null if user is not found and false if user data could not be fetched
	 *
	 * @param string $value
	 * @param string $column
	 *
	 * @throws \Exception
	 * @return User|null|false
	 */
	public static function get($value, $column = 'id'){
		if ($column === 'id')
			return User::find($value);

		if ($column === 'name' && !empty(self::$_USER_CACHE[$value]))
			return self::$_USER_CACHE[$value];

		$User = DB::$instance->where($column, $value)->getOne('users');

		if (empty($User) && $column === 'name')
			$User = self::fetch($value);

		if (isset($User->name))
			self::$_USER_CACHE[$User->name] = $User;

		return $User;
	}

	/**
	 * User Information Fetching
	 * -------------------------
	 * Fetch user info from dA upon request to nonexistent user
	 *
	 * @param string $username
	 *
	 * @return User|null|false
	 * @throws \Exception
	 */
	public static function fetch($username){
		global $USERNAME_REGEX;

		if (!$USERNAME_REGEX->match($username))
			return null;

		$oldName = DANameChange::find_by_old($username);
		if (!empty($oldName))
			return $oldName->user;

		try {
			$userdata = DeviantArt::request('user/whois', null, ['usernames[0]' => $username]);
		}
		catch (CURLRequestException $e){
			return false;
		}

		if (empty($userdata['results'][0]))
			return false;

		$userdata = $userdata['results'][0];
		$ID = strtolower($userdata['userid']);

		/** @var $DBUser User */
		$DBUser = DB::$instance->where('id', $ID)->getOne('users','name');
		$userExists = !empty($DBUser);

		$insert = [
			'name' => $userdata['username'],
			'avatar_url' => URL::makeHttps($userdata['usericon']),
		];
		if (!$userExists)
			$insert['id'] = $ID;

		$clubRole = DeviantArt::getClubRoleByName($userdata['username']);
		if (!empty($clubRole))
			$insert['role'] = $clubRole;

		if (!($userExists ? DB::$instance->where('id', $ID)->update('users', $insert) : DB::$instance->insert('users',$insert)))
			throw new \Exception('Saving user data failed'.(Permission::sufficient('developer')?': '.DB::$instance->getLastError():''));

		if (!$userExists)
			Logs::logAction('userfetch', ['userid' => $insert['id']]);
		$names = [$username];
		if ($userExists && $DBUser->name !== $username)
			$names[] = $DBUser->name;
		foreach ($names as $name){
			if (strcasecmp($name,$insert['name']) !== 0){
				Logs::logAction('da_namechange', [
					'old' => $name,
					'new' => $insert['name'],
					'user_id' => $ID,
				], Logs::FORCE_INITIATOR_WEBSERVER);
			}
		}

		return self::get($insert['name'], 'name');
	}

	/**
	 * Check maximum simultaneous reservation count
	 *
	 * @param bool $return_as_bool
	 *
	 * @return bool|null
	 */
	public static function reservationLimitExceeded(bool $return_as_bool = false){
		$reservations = DB::$instance->querySingle(
			'SELECT
			(
				(SELECT
				 COUNT(*) as "count"
				 FROM reservations res
				 WHERE res.reserved_by = u.id AND res.deviation_id IS NULL)
				+(SELECT
				  COUNT(*) as "count"
				  FROM requests req
				  WHERE req.reserved_by = u.id AND req.deviation_id IS NULL)
			) as "count"
			FROM users u WHERE u.id = ?',
			[Auth::$user->id]
		);

		$overTheLimit = isset($reservations['count']) && $reservations['count'] >= 4;
		if ($return_as_bool)
			return $overTheLimit;
		if ($overTheLimit)
			Response::fail("You've already reserved {$reservations['count']} images, and you can't have more than 4 pending reservations at a time. You can review your reservations on your <a href='/user'>Account page</a>, finish at least one of them before trying to reserve another image.");
	}

	/**
	 * Parse session array for user page
	 *
	 * @param Session $Session
	 * @param bool $current
	 */
	public static function renderSessionLi(Session $Session, bool $current = false){
		$browserClass = CoreUtils::browserNameToClass($Session->browser_name);
		$browserTitle = !empty($Session->browser_name) ? "{$Session->browser_name} {$Session->browser_ver}" : 'Unrecognized browser';
		$platform = !empty($Session->platform) ? "<span class='platform'>on <strong>{$Session->platform}</strong></span>" : '';

		$signoutText = !$current ? 'Delete' : 'Sign out';
		$buttons = "<button class='typcn remove ".(!$current?'typcn-trash red':'typcn-arrow-back')."'>$signoutText</button>";
		if (Permission::sufficient('developer') && !empty($Session->user_agent)){
			$buttons .= "<button class='darkblue typcn typcn-eye useragent' data-agent='".CoreUtils::aposEncode($Session->user_agent)."'>UA</button>".
				"<a class='btn link typcn typcn-chevron-right' href='/about/browser/{$Session->id}'>Debug</a>";
		}

		$firstuse = Time::tag($Session->created);
		$lastuse = !$current ? 'Last used: '.Time::tag($Session->last_visit) : '<em>Current session</em>';
		echo <<<HTML
<li class="browser-$browserClass" id="session-{$Session->id}">
<span class="browser">$browserTitle</span>
$platform
<div class='button-block'>$buttons</div>
<span class="created">Created: $firstuse</span>
<span class="used">$lastuse</span>
</li>
HTML;
	}

	/**
	 * Check authentication cookie and set Auth class static properties
	 *
	 * @throws \InvalidArgumentException
	 */
	public static function authenticate(){
		if (Auth::$signed_in)
			return;

		if (!Cookie::exists('access')){
			Auth::$session = Session::newGuestSession();
			return;
		}
		$authKey = Cookie::get('access');
		if (!empty($authKey)){
			Auth::$session = Session::find_by_token(CoreUtils::sha256($authKey));
			if (Auth::$session !== null)
				Auth::$user = Auth::$session->user;
			else Auth::$session = Session::newGuestSession();

			if (Auth::$user === null)
				Auth::$session->last_visit = date('c');
		}

		if (!empty(Auth::$user->id)){
			// TODO When re-implementing banning, this could be re-used
			/* if ($ban_condition)
				Session::table()->delete(['user_id' => Auth::$user->id]);
			else { */
				Auth::$signed_in = true;
				Auth::$session->registerVisit();
				if (Auth::$session->expired)
					Auth::$session->refreshAccessToken();
			//}
		}
		else if (Auth::$session === null)
			Cookie::delete('access', Cookie::HTTPONLY);
	}

	public const PROFILE_SECTION_PRIVACY_LEVEL = [
		'developer' => "<span class='typcn typcn-cog color-red' title='Visible to: developer'></span>",
		'public' => "<span class='typcn typcn-world color-blue' title='Visible to: public'></span>",
		'staff' => "<span class='typcn typcn-lock-closed' title='Visible to: you & group staff'></span>",
		'staffOnly' => "<span class='typcn typcn-lock-closed color-red' title='Visible to: group staff'></span>",
		'private' => "<span class='typcn typcn-lock-closed color-green' title='Visible to: you'></span>",
	];

	public const YOU_HAVE = [
		1 => 'You have',
		0 => 'This user has',
	];

	/**
	 * @param User $User
	 * @param bool $sameUser
	 * @param bool $isMember
	 *
	 * @return string
	 */
	public static function getPendingReservationsHTML($User, $sameUser, $isMember = true):string {
		return $User->getPendingReservationsHTML($sameUser, $isMember);
	}

	public static function getPersonalColorGuideHTML(User $User, bool $sameUser, bool $wrap = WRAP):string {
		$sectionIsPrivate = UserPrefs::get('p_hidepcg', $User);
		if ($sectionIsPrivate && (!$sameUser && Permission::insufficient('staff')))
			return '';

		$privacy = $sameUser ? self::PROFILE_SECTION_PRIVACY_LEVEL[$sectionIsPrivate ? 'staff' : 'public'] : '';
		$whatBtn = $sameUser ? ' <button class="personal-cg-say-what typcn typcn-info-large darkblue">What?</button>':'';
		$HTML = "<h2>{$privacy}Personal Color Guide{$whatBtn}</h2>";

		$showPrivate = $sameUser || Permission::sufficient('staff');
		if ($showPrivate){
			$ThisUser = $sameUser?'You':'This user';
			$availPoints = $User->getPCGAvailablePoints(false);
			$remainPoints = 10-($availPoints % 10);
			$nSlots = CoreUtils::makePlural('remaining slot',floor($availPoints/10),PREPEND_NUMBER);
			$nRequests = CoreUtils::makePlural('approved request',$remainPoints,PREPEND_NUMBER);
			$has = $sameUser?'have':'has';
			$is = $sameUser?'are':'is';
			$HTML .= "<div class='personal-cg-progress'><p>$ThisUser $has $nSlots and $is $nRequests away from getting another.</p></div>";
		}

		$PersonalColorGuides = $User->pcg_appearances;
		$hasPCG = count($PersonalColorGuides) > 0;
		if ($sameUser || $hasPCG){
			$el = $hasPCG ? 'ul' : 'div';
			$HTML .= "<$el class='personal-cg-appearances'>";
			if ($hasPCG)
				foreach ($PersonalColorGuides as $p)
					$HTML .= '<li>'.$p->toAnchorWithPreview().'</li>';
			else $HTML .= "You haven't added any appearances to your Personal Color Guide yet.";
			$HTML .= "</$el>";
		}
		$Action = $sameUser ? 'Manage' : 'View';
		$slothistbtn = $User->getPCGPointHistoryButtonHTML($showPrivate);
		$giftslotbtn = $User->getPCGSlotGiftButtonHTML();
		$givepointsbtn = $User->getPCGPointGiveButtonHTML();
		$HTML .= <<<HTML
<div class="button-block">
	<a href='/@{$User->name}/cg' class='btn link typcn typcn-arrow-forward'>$Action Personal Color Guide</a>
	$slothistbtn
	$giftslotbtn
	$givepointsbtn
</div>
HTML;
		$HTML .= '';

		return $wrap ? "<section class='personal-cg'>$HTML</section>" : $HTML;
	}

	public static function calculatePersonalCGNextSlot(int $postcount):int {
		return 10-($postcount % 10);
	}

	public static function validateName($key, $errors = null, $method_get = false, $silent_fail = false):?string {
		return (new Input($key,'username', [
			Input::IS_OPTIONAL => true,
			Input::SOURCE => $method_get ? 'GET' : 'POST',
			Input::SILENT_FAILURE => $silent_fail,
			Input::NO_LOGGING => $silent_fail,
			Input::CUSTOM_ERROR_MESSAGES => $errors ?? [
				Input::ERROR_MISSING => 'Username (@value) is missing',
				Input::ERROR_INVALID => 'Username (@value) is invalid',
			]
		]))->out();
	}

	public static function getContributionsHTML(User $user, bool $sameUser):string {
		$contribs = $user->getCachedContributions();
		if (empty($contribs))
			return '';

		$privacy = $sameUser? self::PROFILE_SECTION_PRIVACY_LEVEL['public']:'';
		$cachedur = User::CONTRIB_CACHE_DURATION / Time::IN_SECONDS['hour'];
		$cachedur = CoreUtils::makePlural('hour', $cachedur, PREPEND_NUMBER);
		$HTML = "<section class='contributions'><h2>{$privacy}Contributions <span class='typcn typcn-info-large' title='This data is updated every {$cachedur}'></h2>\n<ul>";
		foreach ($contribs as $key => $contrib)
			$HTML .= self::_processContrib($key, $user, ...$contrib);
		return "$HTML</ul></section>";
	}
	private static function _processContrib($key, User $user, int $amount, string $what_singular, ?string $append = null):string {
		$what = CoreUtils::makePlural($what_singular, $amount).(!empty($append)?" $append":'');
		$item = "<span class='amt'>$amount</span> <span class='expl'>$what</span>";
		$canSee = $key !== 'requests' || $user->id === (Auth::$user->id ?? null) || Permission::sufficient('staff');
		if (!is_numeric($key) && $canSee){
			$userlink = $user->toURL();
			$item = "<a href='{$userlink}/contrib/$key'>$item</a>";
		}
		return "<li>$item</li>";
	}

	//const NOPE = '<em>Nope</em>';
	public const NOPE = '<span class="typcn typcn-times"></span>';

	private static function _contribItemFinished(Post $item):string {
		if ($item->deviation_id === null)
			return self::NOPE;
		$HTML = "<div class='deviation-promise' data-favme='{$item->deviation_id}'></div>";
		if ($item->finished_at !== null){
			$finished_at = Time::tag($item->finished_at);
			$HTML .= "<div class='finished-at-ts'><span class='typcn typcn-time'></span> $finished_at</div>";
		}
		return $HTML;
	}
	private static function _contribItemApproved(Post $item):string {
		if (empty($item->lock))
			return self::NOPE;

		$HTML = '<span class="color-green typcn typcn-tick"></span>';
		$approval_entry = $item->approval_entry;
		if ($approval_entry !== null){
			if (Permission::sufficient('staff')){
				$approved_by = $approval_entry->actor->toAnchor();
				$HTML .= "<div class='approved-by'><span class='typcn typcn-user'></span> $approved_by</div>";
			}
			$approved_at = Time::tag($approval_entry->timestamp);
			$HTML .= "<div class='approved-at-ts'><span class='typcn typcn-time'></span> $approved_at</div>";
		}
		return $HTML;
	}

	public static function getContributionListHTML(string $type, ?array $data, bool $wrap = WRAP):string {
		switch ($type){
			case 'cms-provided':
				$TABLE = <<<HTML
<th>Appearance</th>
<th>Deviation</th>
HTML;
			break;
			case 'requests':
				$TABLE = <<<HTML
<th>Post</th>
<th>Posted <span class="typcn typcn-arrow-sorted-down" title="Newest first"></span></th>
<th>Reserved?</th>
<th>Finished?</th>
<th>Approved?</th>
HTML;
			break;
			case 'reservations':
				$TABLE = <<<HTML
<th>Post</th>
<th>Posted <span class="typcn typcn-arrow-sorted-down" title="Newest first"></span></th>
<th>Finished?</th>
<th>Approved?</th>
HTML;
			break;
			case 'finished-posts':
				$TABLE = <<<HTML
<th>Post</th>
<th>Posted <span class="typcn typcn-arrow-sorted-down" title="Newest first"></span></th>
<th>Reserved</th>
<th>Deviation</th>
<th>Approved?</th>
HTML;
			break;
			case 'fulfilled-requests':
				$TABLE = <<<HTML
<th>Post</th>
<th>Posted</th>
<th>Finished <span class="typcn typcn-arrow-sorted-down" title="Newest first"></span></th>
<th>Deviation</th>
HTML;
			break;
			default:
				throw new \Exception(__METHOD__.": Missing table heading definitions for type $type");
		}
		$TABLE = "<thead><tr>$TABLE</tr></thead>";

		foreach ($data as $item){
			switch ($type){
				case 'cms-provided':
					/** @var $item \App\Models\Cutiemark */
					$appearance = $item->appearance;
					$preview = $appearance->toAnchorWithPreview();
					$deviation = $item->favme !== null ? "<div class='deviation-promise' data-favme='{$item->favme}'></div>" : self::NOPE;

					$TR = <<<HTML
<td class="pony-link">$preview</td>
<td>$deviation</td>
HTML;

				break;
				case 'requests':
					/** @var $item Request */
					$preview = $item->toAnchorWithPreview();
					$posted = Time::tag($item->requested_at);
					if ($item->reserved_by !== null){
						$reserved_by = $item->reserver->toAnchor();
						$reserved_at = Time::tag($item->reserved_at);
						$reserved = "<span class='typcn typcn-user' title='By'></span> $reserved_by<br><span class='typcn typcn-time'></span> $reserved_at";
					}
					else $reserved = self::NOPE;
					$finished = self::_contribItemFinished($item);
					$approved = self::_contribItemApproved($item);
					$TR = <<<HTML
<td>$preview</td>
<td>$posted</td>
<td class="by-at">$reserved</td>
<td>$finished</td>
<td class="approved">$approved</td>
HTML;
				break;
				case 'reservations':
					/** @var $item Request */
					$preview = $item->toAnchorWithPreview();
					$posted = Time::tag($item->reserved_at);
					$finished = self::_contribItemFinished($item);
					$approved = self::_contribItemApproved($item);
					$TR = <<<HTML
<td>$preview</td>
<td>$posted</td>
<td>$finished</td>
<td class="approved">$approved</td>
HTML;
				break;
				case 'finished-posts':
					/** @var $item Request|Reservation */
					$preview = $item->toAnchorWithPreview();
					$posted_by = ($item->is_request ? $item->requester : $item->reserver)->toAnchor();
					$posted_at = Time::tag($item->posted_at);
					$posted = "<span class='typcn typcn-user' title='By'></span> $posted_by<br><span class='typcn typcn-time'></span> $posted_at";
					if ($item->is_request){
						$posted = "<td class='by-at'>$posted</td>";
						$reserved = '<td>'.Time::tag($item->reserved_at).'</td>';
					}
					else {
						$posted = "<td colspan='2'>$posted</td>";
						$reserved = '';
					}
					$finished = self::_contribItemFinished($item);
					$approved = self::_contribItemApproved($item);
					$TR = <<<HTML
<td>$preview</td>
$posted
$reserved
<td>$finished</td>
<td class="approved">$approved</td>
HTML;
				break;
				case 'fulfilled-requests':
					/** @var $item Request */
					$preview = $item->toAnchorWithPreview();
					$posted_by = $item->requester->toAnchor();
					$requested_at = Time::tag($item->requested_at);
					$posted = "<span class='typcn typcn-user' title='By'></span> $posted_by<br><span class='typcn typcn-time'></span> $requested_at";
					$finished = $item->finished_at === null ? '<span class="typcn typcn-time missing-time" title="Time data missing"></span>' : Time::tag($item->finished_at);
					$deviation = "<div class='deviation-promise' data-favme='{$item->deviation_id}'></div>";
					$TR = <<<HTML
<td>$preview</td>
<td class='by-at'>$posted</td>
<td>$finished</td>
<td>$deviation</td>
HTML;
				break;
				default:
					$TR = '';
			}

			$TABLE .= "<tr>$TR</tr>";
		}

		return $wrap ? "<table id='contribs'>$TABLE</table>" : $TABLE;
	}
}
