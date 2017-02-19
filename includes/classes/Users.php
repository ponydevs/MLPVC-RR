<?php

namespace App;

use App\Models\Post;
use App\Models\User;
use App\Exceptions\CURLRequestException;

class Users {
	// Global cache for storing user details
	static $_USER_CACHE = array();
	static $_PREF_CACHE = array();

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
	 * @param string $dbcols
	 *
	 * @throws \Exception
	 * @return User|null|false
	 */
	static function get($value, $coloumn = 'id', $dbcols = null){
		global $Database;

		if ($coloumn === "token"){
			$Auth = $Database->where('token', $value)->getOne('sessions');

			if (empty($Auth))
				return null;
			$coloumn = 'id';
			$value = $Auth['user'];
		}

		if ($coloumn === 'id' && !empty(self::$_USER_CACHE[$value]))
			return self::$_USER_CACHE[$value];

		$User = $Database->where($coloumn, $value)->getOne('users',$dbcols);

		if (empty($User) && $coloumn === 'name')
			$User = self::fetch($value, $dbcols);

		if (empty($dbcols) && !empty($User) && isset($Auth))
			$User->Session = $Auth;

		if (isset($User->id))
			self::$_USER_CACHE[$User->id] = $User;

		return $User;
	}

	/**
	 * User Information Fetching
	 * -------------------------
	 * Fetch user info from dA upon request to nonexistant user
	 *
	 * @param string $username
	 * @param string $dbcols
	 *
	 * @return User|null|false
	 */
	function fetch($username, $dbcols = null){
		global $Database, $USERNAME_REGEX;

		if (!$USERNAME_REGEX->match($username))
			return null;

		$oldName = $Database->where('old', $username)->getOne('log__da_namechange','id');
		if (!empty($oldName))
			return self::get($oldName['id'], 'id', $dbcols);

		try {
			$userdata = DeviantArt::request('user/whois', null, array('usernames[0]' => $username));
		}
		catch (CURLRequestException $e){
			return false;
		}

		if (empty($userdata['results'][0]))
			return false;

		$userdata = $userdata['results'][0];
		$ID = strtolower($userdata['userid']);

		/** @var $DBUser User */
		$DBUser = $Database->where('id', $ID)->getOne('users','name');
		$userExists = !empty($DBUser);

		$insert = array(
			'name' => $userdata['username'],
			'avatar_url' => URL::makeHttps($userdata['usericon']),
		);
		if (!$userExists)
			$insert['id'] = $ID;

		if (!($userExists ? $Database->where('id', $ID)->update('users', $insert) : $Database->insert('users',$insert)))
			throw new \Exception('Saving user data failed'.(Permission::sufficient('developer')?': '.$Database->getLastError():''));

		if (!$userExists)
			Logs::logAction('userfetch',array('userid' => $insert['id']));
		$names = array($username);
		if ($userExists && $DBUser->name !== $username)
			$names[] = $DBUser->name;
		foreach ($names as $name){
			if (strcasecmp($name,$insert['name']) !== 0){
				if (UserPrefs::get('discord_token',$ID) === 'true')
					UserPrefs::set('discord_token','',$ID);
				Logs::logAction('da_namechange',array(
					'old' => $name,
					'new' => $insert['name'],
					'id' => $ID,
				), Logs::FORCE_INITIATOR_WEBSERVER);
			}
		}

		return self::get($insert['name'], 'name', $dbcols);
	}

	/**
	 * Renders the user card
	 *
	 * @param bool $showAvatar
	 */
	static function renderCard($showAvatar = false){
		global $signedIn, $currentUser;
		if ($signedIn){
			$avatar = $currentUser->avatar_url;
			$username = $currentUser->getProfileLink();
			$rolelabel = $currentUser->rolelabel;
			$Avatar = $showAvatar ? $currentUser->getAvatarWrap() : '';
		}
		else {
			$avatar = GUEST_AVATAR;
			$username = 'Curious Pony';
			$rolelabel = 'Guest';
			$Avatar = $showAvatar
				? (new User(array(
					'avatar_url' => $avatar,
					'name' => $username,
					'rolelabel' => $rolelabel,
					'guest' => true,
				)))->getAvatarWrap()
				: '';
		}

		echo "<div class='usercard'>$Avatar<span class='un'>$username</span><span class='role'>$rolelabel</span></div>";
	}

	/**
	 * Check maximum simultaneous reservation count
	 *
	 * @param bool $return_as_bool
	 *
	 * @return bool|null
	 */
	static function reservationLimitExceeded(bool $return_as_bool = false){
		global $Database, $currentUser;

		$reservations = $Database->rawQuerySingle(
			'SELECT
			(
				(SELECT
				 COUNT(*) as "count"
				 FROM reservations res
				 WHERE res.reserved_by = u.id && res.deviation_id IS NULL)
				+(SELECT
				  COUNT(*) as "count"
				  FROM requests req
				  WHERE req.reserved_by = u.id && req.deviation_id IS NULL)
			) as "count"
			FROM users u WHERE u.id = ?',
			array($currentUser->id)
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
	 * @param array $Session
	 * @param bool $current
	 */
	static function renderSessionLi($Session, $current = false){
		$browserClass = CoreUtils::browserNameToClass($Session['browser_name']);
		$browserTitle = !empty($Session['browser_name']) ? "{$Session['browser_name']} {$Session['browser_ver']}" : 'Unrecognized browser';
		$platform = !empty($Session['platform']) ? "<span class='platform'>on <strong>{$Session['platform']}</strong></span>" : '';

		$signoutText = !$current ? 'Delete' : 'Sign out';
		$buttons = "<button class='typcn remove ".(!$current?'typcn-trash red':'typcn-arrow-back')."' data-sid='{$Session['id']}'>$signoutText</button>";
		if (Permission::sufficient('developer') && !empty($Session['user_agent'])){
			$buttons .= "<br><button class='darkblue typcn typcn-eye useragent' data-agent='".CoreUtils::aposEncode($Session['user_agent'])."'>UA</button>".
				"<a class='btn orange typcn typcn-chevron-right' href='/browser/{$Session['id']}'>Debug</a>";
		}

		$firstuse = Time::tag($Session['created']);
		$lastuse = !$current ? 'Last used: '.Time::tag($Session['lastvisit']) : '<em>Current session</em>';
		echo <<<HTML
<li class="browser-$browserClass" id="session-{$Session['id']}">
<span class="browser">$browserTitle</span>
$platform$buttons
<span class="created">Created: $firstuse</span>
<span class="used">$lastuse</span>
</li>
HTML;
	}

	/**
	 * Check authentication cookie and set global
	 */
	static function authenticate(){
		global $Database, $signedIn, $currentUser, $Color, $color;
		CSRFProtection::detect();

		if (!POST_REQUEST && isset($_GET['CSRF_TOKEN']))
			HTTP::redirect(CSRFProtection::removeParamFromURL($_SERVER['REQUEST_URI']));

		if (!Cookie::exists('access'))
			return;
		$authKey = Cookie::get('access');
		if (!empty($authKey)){
			if (!preg_match(new RegExp('^[a-f\d]+$','iu'), $authKey)){
				$oldAuthKey = $authKey;
				$authKey = bin2hex($authKey);
				$Database->where('token', sha1($oldAuthKey))->update('sessions',array( 'token' => sha1($authKey) ));
				Cookie::set('access', $authKey, time() + Time::IN_SECONDS['year'], Cookie::HTTPONLY);
			}
			$currentUser = Users::get(sha1($authKey),'token');
		}

		if (!empty($currentUser)){
			if ($currentUser->role === 'ban')
				$Database->where('id', $currentUser->id)->delete('sessions');
			else {
				if (isset($currentUser->Session['expires'])){
					if (strtotime($currentUser->Session['expires']) < time()){
						$tokenvalid = false;
						try {
							DeviantArt::getToken($currentUser->Session['refresh'], 'refresh_token');
							$tokenvalid = true;
						}
						catch (CURLRequestException $e){
							$Database->where('id', $currentUser->Session['id'])->delete('sessions');
							trigger_error("Session refresh failed for {$currentUser->name} ({$currentUser->id}) | {$e->getMessage()} (HTTP {$e->getCode()})", E_USER_WARNING);
						}
					}
					else $tokenvalid = true;
				}
				else $tokenvalid = false;

				if ($tokenvalid){
					$signedIn = true;
					if (time() - strtotime($currentUser->Session['lastvisit']) > Time::IN_SECONDS['minute']){
						$lastVisitTS = date('c');
						if ($Database->where('id', $currentUser->Session['id'])->update('sessions', array('lastvisit' => $lastVisitTS)))
							$currentUser->Session['lastvisit'] = $lastVisitTS;
					}

					$_PrefersColour = array(
						'Pirill-Poveniy' => true,
						'itv-canterlot' => true,
					);
					if (isset($_PrefersColour[$currentUser->name])){
						$Color = 'Colour';
						$color = 'colour';
					}
				}
			}
		}
		else Cookie::delete('access', Cookie::HTTPONLY);
	}

	const PROFILE_SECTION_PRIVACY_LEVEL = array(
		'developer' => "<span class='typcn typcn-cog color-red' title='Visible to: developer'></span>",
		'public' => "<span class='typcn typcn-world color-blue' title='Visible to: public'></span>",
		'staff' => "<span class='typcn typcn-lock-closed' title='Visible to: you & group administrators'></span>",
		'private' => "<span class='typcn typcn-lock-closed color-green' title='Visible to: you'></span>",
	);

	const YOU_HAVE = [
		true => 'You have',
		false => 'This user has',
	];

	static function getPendingReservationsHTML($UserID, $sameUser, $isMember = true){
		global $Database, $currentUser;

		$YouHave = self::YOU_HAVE[$sameUser];
		$PrivateSection = $sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:'';

		if ($isMember){
			$cols = "id, season, episode, preview, label, posted, reserved_by";
			$PendingReservations = $Database->where('reserved_by', $UserID)->where('deviation_id IS NULL')->get('reservations',null,$cols);
			$PendingRequestReservations = $Database->where('reserved_by', $UserID)->where('deviation_id IS NULL')->get('requests',null,"$cols, reserved_at, true as requested_by");
			$TotalPending = count($PendingReservations)+count($PendingRequestReservations);
			$hasPending = $TotalPending > 0;
		}
		else $TotalPending = 0;
		$HTML = '';
		if (Permission::sufficient('staff') || $sameUser){
			$gamble = $TotalPending < 4 && $sameUser ? ' <button id="suggestion" class="btn orange typcn typcn-lightbulb">Suggestion</button>' : '';
			$HTML .= <<<HTML
<section class='pending-reservations'>
<h2>{$PrivateSection}Pending reservations$gamble</h2>
HTML;

			if ($isMember){
				$pendingCountReadable = ($hasPending>0?"<strong>$TotalPending</strong>":'no');
				$posts = CoreUtils::makePlural('reservation', $TotalPending);
				$HTML .= "<span>$YouHave $pendingCountReadable pending $posts";
				if ($hasPending)
					$HTML .= " which ha".($TotalPending!==1?'ve':'s')."n’t been marked as finished yet";
				$HTML .= ".";
				if ($sameUser)
					$HTML .= " Please keep in mind that the global limit is 4 at any given time. If you reach the limit, you can’t reserve any more images until you finish or cancel some of your pending reservations.";
				$HTML .= "</span>";

				if ($hasPending){
					/** @var $Posts Post[] */
					$Posts = array_merge(
						Posts::getReservationsSection($PendingReservations, RETURN_ARRANGED)['unfinished'],
						array_filter(array_values(Posts::getRequestsSection($PendingRequestReservations, RETURN_ARRANGED)['unfinished']))
					);
					usort($Posts, function(Post $a, Post $b){
						$a = strtotime($a->posted);
						$b = strtotime($b->posted);

						return -($a < $b ? -1 : ($a === $b ? 0 : 1));
					});
					$LIST = '';
					foreach ($Posts as $Post){
						$postLink = $Post->toLink($_);
						$postAnchor = $Post->toAnchor(null, $_);
						$label = !empty($Post->label) ? "<span class='label'>{$Post->label}</span>" : '';
						$actionCond = $Post->isRequest && !empty($Post->reserved_at);
						$posted = Time::tag($actionCond ? $Post->reserved_at : $Post->posted);
						$PostedAction = $actionCond ? 'Reserved' : 'Posted';
						$contestable = $Post->isOverdue() ? Posts::CONTESTABLE : '';

						$LIST .= <<<HTML
	<li>
	<div class='image screencap'>
		<a href='$postLink'><img src='{$Post->preview}'></a>
	</div>
	$label
	<em>$PostedAction under $postAnchor $posted</em>$contestable
	<div>
		<a href='$postLink' class='btn blue typcn typcn-arrow-forward'>View</a>
		<button class='red typcn typcn-user-delete cancel'>Cancel</button>
	</div>
	</li>
HTML;
						// Clearing variable set via reference by the toLink method call
						unset($_);
					}
					$HTML .= "<ul>$LIST</ul>";
				}
			}
			else {
				$HTML .= "<p>Reservations are a way to allow Club Members to claim requests on the site as well as claim screenshots of their own, in order to reduce duplicates. If you don't plan on becoming a member you can still use the button above to get random requests from the site that you can draw as practice, or potentially to submit along with your application.</p>";
			}

			$HTML .= "</section>";
		}
		return $HTML;
	}

	static function getPersonalColorGuideHTML(User $User, bool $sameUser):string {
		global $Database;
		$UserID = $User->id;
		$sectionIsPrivate = UserPrefs::get('p_hidepcg', $UserID);
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
		$pcgLimits = $User->getPCGAvailableSlots(false, true);
		$nSlots = CoreUtils::makePlural('slot',$pcgLimits['totalslots'],PREPEND_NUMBER);
		if ($showPrivate){
			$ApprovedFinishedRequests = $pcgLimits['postcount'];
			$SlotCount = $pcgLimits['totalslots'];
			$ToNextSlot = Users::calculatePersonalCGNextSlot($ApprovedFinishedRequests);

			$has = $sameUser?'have':'has';
			$nApprovedRequests = CoreUtils::makePlural('approved request',$ApprovedFinishedRequests,PREPEND_NUMBER);
			$grants = 'grant'.($ApprovedFinishedRequests!=1?'':'s');
			$them = $sameUser?'you':'them';
			$forStaff = Permission::sufficient('staff', $User->role) ? ' (staff members get a free slot)' : '';
			$privateStatus = "$ThisUser currently $has $nApprovedRequests on the site, which $grants $them $nSlots$forStaff. ";
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
		$PersonalColorGuides = $Database->where('owner',$UserID)->orderBy('order')->get('appearances');
		if (count($PersonalColorGuides) > 0 || $sameUser){
			$HTML .= "<ul class='personal-cg-appearances'>";
			foreach ($PersonalColorGuides as $p)
				$HTML .= "<li>".Appearances::getLinkWithPreviewHTML($p).'</li>';
			$HTML .= "</ul>";
		}
		$Action = $sameUser ? 'Manage' : 'View';
		$HTML .= "<p><a href='/@{$User->name}/cg' class='btn darkblue typcn typcn-arrow-forward'>$Action Personal Color Guide</a></p>";
		$HTML .= '</section>';

		return $HTML;
	}

	static function calculatePersonalCGSlots(int $postcount):int {
		return floor($postcount/10);
	}

	static function calculatePersonalCGNextSlot(int $postcount):int {
		return 10-($postcount % 10);
	}

	static function validateName($key, $errors, $method_get = false){
		return (new Input($key,'username',array(
			Input::IS_OPTIONAL => true,
			Input::METHOD_GET => $method_get,
			Input::CUSTOM_ERROR_MESSAGES => $errors ?? array(
				Input::ERROR_MISSING => 'Username (@value) is missing',
				Input::ERROR_INVALID => 'Username (@value) is invalid',
			)
		)))->out();
	}

	static function getAwaitingApprovalHTML(User $User, $sameUser):string {
		if (Permission::insufficient('member', $User->role))
			HTTP::statusCode(404, AND_DIE);

		global $Database;
		$cols = "id, season, episode, deviation_id";
		/** @var $AwaitingApproval \App\Models\Post[] */
		$AwaitingApproval = array_merge(
			$Database
				->where('reserved_by', $User->id)
				->where('deviation_id IS NOT NULL')
				->where('"lock" IS NOT TRUE')
				->get('reservations',null,$cols),
			$Database
				->where('reserved_by', $User->id)
				->where('deviation_id IS NOT NULL')
				->where('"lock" IS NOT TRUE')
				->get('requests',null,$cols)
		);
		$AwaitCount = count($AwaitingApproval);
		$them = $AwaitCount!==1?'them':'it';
		$YouHave = self::YOU_HAVE[$sameUser];
		$privacy = $sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['public']:'';
		$HTML = "<h2>{$privacy}Vectors waiting for approval</h2>";
		if ($sameUser)
			$HTML .= "<p>After you finish an image and submit it to the group gallery, an admin will check your vector and may ask you to fix some issues on your image, if any. After an image is accepted to the gallery, it can be marked as \"approved\", which gives it a green check mark, indicating that it’s most likely free of any errors.</p>";
		$youHaveAwaitCount = "$YouHave ".(!$AwaitCount?'no':"<strong>$AwaitCount</strong>");
		$images = CoreUtils::makePlural('image', $AwaitCount);
		$append = !$AwaitCount
			? '.'
			: ", listed below.".(
				$sameUser
				? " Please submit $them to the group gallery as soon as possible to have $them spot-checked for any issues. As stated in the rules, the goal is to add finished images to the group gallery, making $them easier to find for everyone.".(
					$AwaitCount>10
					? " You seem to have a large number of images that have not been approved yet, please submit them to the group soon if you haven’t already."
					: ''
				)
				:''
			).'</p><p>You can click the <strong class="color-green"><span class="typcn typcn-tick"></span> Check</strong> button below the '.CoreUtils::makePlural('image',$AwaitCount).' in case we forgot to click it ourselves after accepting it.';
		$HTML .= <<<HTML
			<p>{$youHaveAwaitCount} $images waiting to be submited to and/or approved by the group$append</p>
HTML;
		if ($AwaitCount){
			$HTML .= '<ul id="awaiting-deviations">';
			foreach ($AwaitingApproval as $Post){
				$deviation = DeviantArt::getCachedDeviation($Post->deviation_id);
				$url = "http://{$deviation['provider']}/{$deviation['id']}";
				unset($_);
				$postLink = $Post->toLink($_);
				$postAnchor = $Post->toAnchor(null, $_);
				$checkBtn = Permission::sufficient('member') ? "<button class='green typcn typcn-tick check'>Check</button>" : '';

				$HTML .= <<<HTML
<li id="{$Post->getID()}">
	<div class="image deviation">
		<a href="$url" target="_blank">
			<img src="{$deviation['preview']}" alt="{$deviation['title']}">
		</a>
	</div>
	<span class="label"><a href="$url" target="_blank">{$deviation['title']}</a></span>
	<em>Posted under $postAnchor</em>
	<div>
		<a href='$postLink' class='btn blue typcn typcn-arrow-forward'>View</a>
		$checkBtn
	</div>
</li>
HTML;
			}
			$HTML .= '</ul>';
		}

		return $HTML;
	}
}
