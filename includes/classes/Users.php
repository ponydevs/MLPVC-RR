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
	 * Gets a single row from the 'users' database
	 *  where $coloumn is equal to $value
	 * Returns null if user is not found
	 *
	 * If $cols is set, only specified coloumns
	 *  will be fetched
	 *
	 * @param string $value
	 * @param string $coloumn
	 *
	 * @throws \Exception
	 * @return User|null|false
	 */
	public static function get($value, $coloumn = 'id'){
		if ($coloumn === 'id')
			return User::find($value);

		if ($coloumn === 'name' && !empty(self::$_USER_CACHE[$value]))
			return self::$_USER_CACHE[$value];

		$User = DB::$instance->where($coloumn, $value)->getOne('users');

		if (empty($User) && $coloumn === 'name')
			$User = self::fetch($value);

		if (isset($User->name))
			self::$_USER_CACHE[$User->name] = $User;

		return $User;
	}

	/**
	 * User Information Fetching
	 * -------------------------
	 * Fetch user info from dA upon request to nonexistant user
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
			Response::fail("You've already reserved {$reservations['count']} images, and you can’t have more than 4 pending reservations at a time. You can review your reservations on your <a href='/user'>Account page</a>, finish at least one of them before trying to reserve another image.");
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
		$buttons = "<button class='typcn remove ".(!$current?'typcn-trash red':'typcn-arrow-back')."' data-sid='{$Session->id}'>$signoutText</button>";
		if (Permission::sufficient('developer') && !empty($Session->user_agent)){
			$buttons .= "<br><button class='darkblue typcn typcn-eye useragent' data-agent='".CoreUtils::aposEncode($Session->user_agent)."'>UA</button>".
				"<a class='btn link typcn typcn-chevron-right' href='/browser/{$Session->id}'>Debug</a>";
		}

		$firstuse = Time::tag($Session->created);
		$lastuse = !$current ? 'Last used: '.Time::tag($Session->lastvisit) : '<em>Current session</em>';
		echo <<<HTML
<li class="browser-$browserClass" id="session-{$Session->id}">
<span class="browser">$browserTitle</span>
$platform$buttons
<span class="created">Created: $firstuse</span>
<span class="used">$lastuse</span>
</li>
HTML;
	}

	/**
	 * Check authentication cookie and set global
	 *
	 * @throws \InvalidArgumentException
	 */
	public static function authenticate(){
		CSRFProtection::detect();

		if (!POST_REQUEST && isset($_GET['CSRF_TOKEN']))
			HTTP::redirect(CSRFProtection::removeParamFromURL($_SERVER['REQUEST_URI']));

		if (!Cookie::exists('access'))
			return;
		$authKey = Cookie::get('access');
		if (!empty($authKey)){
			Auth::$session = Session::find_by_token(CoreUtils::sha256($authKey));
			if (Auth::$session !== null)
				Auth::$user = Auth::$session->user;
		}

		if (!empty(Auth::$user->id)){
			if (Auth::$user->role === 'ban')
				Session::table()->delete(['user' => Auth::$user->id]);
			else {
				if (!Auth::$session->expired)
					$tokenvalid = true;
				else {
					$tokenvalid = false;
					try {
						DeviantArt::refreshAccessToken();
						$tokenvalid = true;
					}
					catch (CURLRequestException $e){
						Auth::$session->delete();
						trigger_error('Session refresh failed for '.Auth::$user->name.' ('.Auth::$user->id.") | {$e->getMessage()} (HTTP {$e->getCode()})", E_USER_WARNING);
					}
				}

				if ($tokenvalid){
					Auth::$signed_in = true;
					if (time() - strtotime(Auth::$session->lastvisit) > Time::IN_SECONDS['minute']){
						Auth::$session->lastvisit = date('c');
						Auth::$session->save();
					}
				}
			}
		}
		else Cookie::delete('access', Cookie::HTTPONLY);
	}

	const PROFILE_SECTION_PRIVACY_LEVEL = [
		'developer' => "<span class='typcn typcn-cog color-red' title='Visible to: developer'></span>",
		'public' => "<span class='typcn typcn-world color-blue' title='Visible to: public'></span>",
		'staff' => "<span class='typcn typcn-lock-closed' title='Visible to: you & group staff'></span>",
		'staffonly' => "<span class='typcn typcn-lock-closed color-red' title='Visible to: group staff'></span>",
		'private' => "<span class='typcn typcn-lock-closed color-green' title='Visible to: you'></span>",
	];

	const YOU_HAVE = [
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

	public static function getPersonalColorGuideHTML(User $User, bool $sameUser):string {

		$sectionIsPrivate = UserPrefs::get('p_hidepcg', $User);
		if ($sectionIsPrivate && (!$sameUser && Permission::insufficient('staff')))
			return '';

		$HTML = '';
		$privacy = $sameUser ? Users::PROFILE_SECTION_PRIVACY_LEVEL[$sectionIsPrivate ? 'staff' : 'public'] : '';
		$whatBtn = $sameUser ? ' <button class="personal-cg-say-what typcn typcn-info-large darkblue">What?</button>':'';
		$HTML .= <<<HTML
<section class="personal-cg">
	<h2>{$privacy}Personal Color Guide{$whatBtn}</h2>
HTML;

		$UsedSlotCount = $User->getPCGAppearances(null,true);
		$ThisUser = $sameUser?'You':'This user';
		$showPrivate = $sameUser || Permission::sufficient('staff');
		/** @var $pcgLimits array */
		$pcgLimits = $User->getPCGAvailableSlots(false, true);
		$nSlots = CoreUtils::makePlural('slot',$pcgLimits['totalslots'],PREPEND_NUMBER);
		if ($showPrivate){
			$ApprovedFinishedRequests = $pcgLimits['postcount'];
			$SlotCount = $pcgLimits['totalslots'];
			$ToNextSlot = Users::calculatePersonalCGNextSlot($ApprovedFinishedRequests);

			$has = $sameUser?'have':'has';
			$nRequests = CoreUtils::makePlural('request',$ApprovedFinishedRequests,PREPEND_NUMBER);
			$grants = 'grant'.($ApprovedFinishedRequests!==1?'':'s');
			$them = $sameUser?'you':'them';
			$forStaff = Permission::sufficient('staff', $User->role) ? ' (staff members get a free slot)' : '';
			$isnt = $ApprovedFinishedRequests !== 1 ? "aren't" : "isn't";
			$their = $sameUser ? 'your' : 'their';
			$privateStatus = "$ThisUser $has finished $nRequests (that $isnt $their own) on the site, which $grants $them $nSlots$forStaff. ";
		}
		else $privateStatus = '';
		$unused = $UsedSlotCount === 0;
		$is = ($sameUser?'are':'is').($unused&&!$showPrivate?'n\'t':'');
		$goal = $sameUser?" and $is ".CoreUtils::makePlural('request',$ToNextSlot,PREPEND_NUMBER).' away from getting another':'';
		$publicStatus = "$ThisUser $is currently using ".($showPrivate ? CoreUtils::makePlural('slot',$UsedSlotCount,PREPEND_NUMBER) : ($UsedSlotCount===0?'any of their slots':"$UsedSlotCount out of their $nSlots"))."$goal.";
		$HTML .= <<<HTML
	<div class="personal-cg-progress">
		<p>$privateStatus$publicStatus</p>
	</div>
HTML;
		$PersonalColorGuides = $User->pcg_appearances;
		if (count($PersonalColorGuides) > 0 || $sameUser){
			$HTML .= "<ul class='personal-cg-appearances'>";
			foreach ($PersonalColorGuides as $p)
				$HTML .= '<li>'.$p->toAnchorWithPreview().'</li>';
			$HTML .= '</ul>';
		}
		$Action = $sameUser ? 'Manage' : 'View';
		$HTML .= "<p><a href='/@{$User->name}/cg' class='btn link typcn typcn-arrow-forward'>$Action Personal Color Guide</a></p>";
		$HTML .= '</section>';

		return $HTML;
	}

	public static function calculatePersonalCGNextSlot(int $postcount):int {
		return 10-($postcount % 10);
	}

	public static function validateName($key, $errors, $method_get = false, $silent_fail = false){
		return (new Input($key,'username', [
			Input::IS_OPTIONAL => true,
			Input::METHOD_GET => $method_get,
			Input::SILENT_FAILURE => $silent_fail,
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

		$privacy = $sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['public']:'';
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

	private static function _contribItemFinished(Post $item):string {
		return $item->deviation_id !== null ? "<div class='deviation-promise' data-favme='{$item->deviation_id}'></div>" : '<em>Nope</em>';
	}
	private static function _contribItemApproved(Post $item):string {
		return !empty($item->lock) ? '<span class="color-green typcn typcn-tick"></span>' : '<em>Nope</em>';
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
					$deviation = "<div class='deviation-promise' data-favme='{$item->favme}'></div>";

					$TR = <<<HTML
<td class="pony-link">$preview</td>
<td>$deviation</td>
HTML;

				break;
				case 'requests':
					/** @var $item Request */
					$preview = $item->toAnchorWithPreview();
					$posted = Time::tag($item->requested_at);
					$isreserved = $item->reserved_by !== null;
					if ($isreserved){
						$reserved_by = $item->reserver->toAnchor();
						$reserved_at = Time::tag($item->reserved_at);
						$reserved = "<span class='typcn typcn-user' title='By'></span> $reserved_by<br><span class='typcn typcn-time'></span> $reserved_at";
					}
					else $reserved = '<em>Nope</em>';
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
					$posted_at = Time::tag($item->posted);
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
					$finished = Time::tag($item->finished_at);
					$deviation = "<div class='deviation-promise' data-favme='{$item->deviation_id}'></div>";
					$TR = <<<HTML
<td>$preview</td>
<td class='by-at'>$posted</td>
<td>$finished</td>
<td>$deviation</td>
HTML;
				break;
			}

			$TABLE .= "<tr>$TR</tr>";
		}

		return $wrap ? "<table id='contribs'>$TABLE</table>" : $TABLE;
	}
}
