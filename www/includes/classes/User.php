<?php

	use Exceptions\cURLRequestException;

	class User {
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
		 * @throws Exception
		 * @return array|null
		 */
		static function Get($value, $coloumn = 'id', $dbcols = null){
			global $Database;

			if ($coloumn === "token"){
				$Auth = $Database->where('token', $value)->getOne('sessions');

				if (empty($Auth)) return null;
				$coloumn = 'id';
				$value = $Auth['user'];
			}

			if ($coloumn === 'id' && !empty(self::$_USER_CACHE[$value]))
				return self::$_USER_CACHE[$value];

			$User = $Database->where($coloumn, $value)->getOne('users',$dbcols);

			if (empty($User) && $coloumn === 'name')
				$User = self::Fetch($value, $dbcols);

			if (!empty($User['role']))
				$User['rolelabel'] = Permission::$ROLES_ASSOC[$User['role']];

			if (empty($dbcols) && !empty($User) && isset($Auth))
				$User['Session'] = $Auth;

			if (isset($User['id']))
				self::$_USER_CACHE[$User['id']] = $User;

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
		 * @return array|null
		 */
		function Fetch($username, $dbcols = null){
			global $Database, $USERNAME_REGEX;

			if (!$USERNAME_REGEX->match($username))
				return null;

			try {
				$userdata = DeviantArt::Request('user/whois', null, array('usernames[0]' => $username));
			}
			catch (cURLRequestException $e){
				return false;
			}

			if (empty($userdata['results'][0]))
				return null;

			$userdata = $userdata['results'][0];
			$ID = strtolower($userdata['userid']);

			$userExists = $Database->where('id', $ID)->has('users');

			$insert = array(
				'name' => $userdata['username'],
				'avatar_url' => URL::MakeHttps($userdata['usericon']),
			);
			if (!$userExists)
				$insert['id'] = $ID;

			if (!($userExists ? $Database->where('id', $ID)->update('users', $insert) : $Database->insert('users',$insert)))
				throw new Exception('Saving user data failed'.(Permission::Sufficient('developer')?': '.$Database->getLastError():''));

			if (!$userExists)
				Log::Action('userfetch',array('userid' => $insert['id']));

			return self::Get($insert['name'], 'name', $dbcols);
		}

		/**
		 * Renders the user card
		 *
		 * @param bool $showAvatar
		 */
		static function RenderCard($showAvatar = false){
			global $signedIn, $currentUser;
			if ($signedIn){
				$avatar = $currentUser['avatar_url'];
				$username = self::GetProfileLink($currentUser);
				$rolelabel = $currentUser['rolelabel'];
				$Avatar = $showAvatar ? self::GetAvatarWrap($currentUser) : '';
			}
			else {
				$avatar = GUEST_AVATAR;
				$username = 'Curious Pony';
				$rolelabel = 'Guest';
				$Avatar = $showAvatar
					? self::GetAvatarWrap(array(
						'avatar_url' => $avatar,
						'name' => $username,
						'rolelabel' => $rolelabel,
						'guest' => true,
					))
					: '';
			}

			echo "<div class='usercard'>$Avatar<span class='un'>$username</span><span class='role'>$rolelabel</span></div>";
		}

		/**
		 * Renders avatar wrapper for a specific user
		 *
		 * @param array $User
		 *
		 * @return string
		 */
		static function GetAvatarWrap($User){
			$vectorapp = User::GetVectorAppClassName($User);
			return "<div class='avatar-wrap$vectorapp'><img src='{$User['avatar_url']}' class='avatar' alt='avatar'></div>";
		}

		const
			LINKFORMAT_FULL = 0,
			LINKFORMAT_TEXT = 1,
			LINKFORMAT_URL = 2;

		/**
		 * Local profile link generator
		 *
		 * @param array $User
		 * @param int $format
		 *
		 * @throws Exception
		 * @return string
		 */
		static function GetProfileLink($User, $format = self::LINKFORMAT_TEXT){
			if (!is_array($User))
				throw new Exception('$User is not an array');

			$Username = $User['name'];
			$avatar = $format == self::LINKFORMAT_FULL ? "<img src='{$User['avatar_url']}' class='avatar' alt='avatar'> " : '';

			return "<a href='/@$Username' class='da-userlink".($format == self::LINKFORMAT_FULL ? ' with-avatar':'')."'>$avatar<span class='name'>$Username</span></a>";
		}

		/**
		 * DeviantArt profile link generator
		 *
		 * @param array $User
		 * @param int $format
		 *
		 * @return string
		 */
		static function GetDALink($User, $format = self::LINKFORMAT_FULL){
			if (!is_array($User)){
				trigger_error('$User is not an array');
				if (Permission::Sufficient('developer'))
					var_dump($User);
			}

			$Username = $User['name'];
			$username = strtolower($Username);
			$link = "http://$username.deviantart.com/";
			if ($format === self::LINKFORMAT_URL) return $link;

			$avatar = $format == self::LINKFORMAT_FULL ? "<img src='{$User['avatar_url']}' class='avatar' alt='avatar'> " : '';
			return "<a href='$link' class='da-userlink'>$avatar<span class='name'>$Username</span></a>";
		}

		/**
		 * Update a user's role
		 *
		 * @param array $targetUser
		 * @param string $newgroup
		 *
		 * @return bool
		 */
		static function UpdateRole($targetUser, $newgroup){
			global $Database;
			$response = $Database->where('id', $targetUser['id'])->update('users',array('role' => $newgroup));

			if ($response) Log::Action('rolechange',array(
				'target' => $targetUser['id'],
				'oldrole' => $targetUser['role'],
				'newrole' => $newgroup
			));

			return $response;
		}

		/**
		 * Check maximum simultaneous reservation count
		 *
		 * @param bool $return_as_bool
		 *
		 * @return bool|null
		 */
		static function ReservationLimitCheck(bool $return_as_bool = false){
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
				array($currentUser['id'])
			);

			$overTheLimit = isset($reservations['count']) && $reservations['count'] >= 4;
			if ($return_as_bool)
				return $overTheLimit;
			if ($overTheLimit)
				Response::Fail("You've already reserved {$reservations['count']} images, and you can't have more than 4 pending reservations at a time. You can review your reservations on your <a href='/user'>Account page</a>, finish at least one of them before trying to reserve another image.");
		}

		/**
		 * Checks if a user is a club member
		 * (currently only works for recently added members, does not deal with old members or admins)
		 *
		 * @param int|string $Username
		 *
		 * @return bool
		 */
		static function IsClubMember($Username){
			$RecentlyJoined = HTTP::LegitimateRequest('http://mlp-vectorclub.deviantart.com/modals/memberlist/');

			return !empty($RecentlyJoined['response'])
				&& regex_match(new RegExp('<a class="[a-z ]*username" href="http://'.strtolower($Username).'.deviantart.com/">'.USERNAME_PATTERN.'</a>'), $RecentlyJoined['response']);
		}

		/**
		 * Parse session array for user page
		 *
		 * @param array $Session
		 * @param bool $current
		 */
		static function RenderSessionLi($Session, $current = false){
			$browserClass = CoreUtils::BrowserNameToClass($Session['browser_name']);
			$browserTitle = !empty($Session['browser_name']) ? "{$Session['browser_name']} {$Session['browser_ver']}" : 'Unrecognized browser';
			$platform = !empty($Session['platform']) ? "<span class='platform'>on <strong>{$Session['platform']}</strong></span>" : '';

			$signoutText = !$current ? 'Delete' : 'Sign out';
			$buttons = "<button class='typcn remove ".(!$current?'typcn-trash red':'typcn-arrow-back')."' data-sid='{$Session['id']}'>$signoutText</button>";
			if (Permission::Sufficient('developer') && !empty($Session['user_agent'])){
				$buttons .= "<br><button class='darkblue typcn typcn-eye useragent' data-agent='".CoreUtils::AposEncode($Session['user_agent'])."'>UA</button>".
					"<a class='btn orange typcn typcn-chevron-right' href='/browser/{$Session['id']}'>Debug</a>";
			}

			$firstuse = Time::Tag($Session['created']);
			$lastuse = !$current ? 'Last used: '.Time::Tag($Session['lastvisit']) : '<em>Current session</em>';
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
		static function Authenticate(){
			global $Database, $signedIn, $currentUser, $Color, $color;
			CSRFProtection::Detect();

			if (!POST_REQUEST && isset($_GET['CSRF_TOKEN']))
				HTTP::Redirect(CSRFProtection::RemoveParamFromURL($_SERVER['REQUEST_URI']));

			if (!Cookie::Exists('access'))
				return;
			$authKey = Cookie::Get('access');
			if (!empty($authKey)){
				if (!regex_match(new RegExp('^[a-f\d]+$','iu'), $authKey)){
					$oldAuthKey = $authKey;
					$authKey = bin2hex($authKey);
					$Database->where('token', sha1($oldAuthKey))->update('sessions',array( 'token' => sha1($authKey) ));
					Cookie::Set('access', $authKey, time() + Time::$IN_SECONDS['year'], Cookie::HTTPONLY);
				}
				$currentUser = User::Get(sha1($authKey),'token');
			}

			if (!empty($currentUser)){
				if ($currentUser['role'] === 'ban')
					$Database->where('id', $currentUser['id'])->delete('sessions');
				else {
					if (strtotime($currentUser['Session']['expires']) < time()){
						try {
							DeviantArt::GetToken($currentUser['Session']['refresh'], 'refresh_token');
							$tokenvalid = true;
						}
						catch (cURLRequestException $e){
							$Database->where('id', $currentUser['Session']['id'])->delete('sessions');
							trigger_error("Session refresh failed for {$currentUser['name']} ({$currentUser['id']}) | {$e->getMessage()} (HTTP {$e->getCode()})", E_USER_WARNING);
						}
					}
					else $tokenvalid = true;

					if ($tokenvalid){
						$signedIn = true;
						if (time() - strtotime($currentUser['Session']['lastvisit']) > Time::$IN_SECONDS['minute']){
							$lastVisitTS = date('c');
							if ($Database->where('id', $currentUser['Session']['id'])->update('sessions', array('lastvisit' => $lastVisitTS)))
								$currentUser['Session']['lastvisit'] = $lastVisitTS;
						}

						$_PrefersColour = array(
							'Pirill-Poveniy' => true,
							'itv-canterlot' => true,
						);
						if (isset($_PrefersColour[$currentUser['name']])){
							$Color = 'Colour';
							$color = 'colour';
						}
					}
				}
			}
			else Cookie::Delete('access', Cookie::HTTPONLY);
		}

		static $PROFILE_SECTION_PRIVACY_LEVEL = array(
			'developer' => "<span class='typcn typcn-cog color-red' title='Visible to: developer'></span>",
			'public' => "<span class='typcn typcn-world color-blue' title='Visible to: public'></span>",
			'staff' => "<span class='typcn typcn-lock-closed' title='Visible to: you & group administrators'></span>",
			'private' => "<span class='typcn typcn-lock-closed color-green' title='Visible to: you'></span>",
		);

		static function GetPendingReservationsHTML($UserID, $sameUser, &$YouHave = null){
			global $Database;

			$YouHave = $sameUser?'You have':'This user has';
			$PrivateSection = $sameUser?User::$PROFILE_SECTION_PRIVACY_LEVEL['staff']:'';

			$cols = "id, season, episode, preview, label, posted";
			$PendingReservations = $Database->where('reserved_by', $UserID)->where('deviation_id IS NULL')->get('reservations',null,$cols);
			$PendingRequestReservations = $Database->where('reserved_by', $UserID)->where('deviation_id IS NULL')->get('requests',null,"$cols, reserved_at, true as requested_by");
			$TotalPending = count($PendingReservations)+count($PendingRequestReservations);
			$hasPending = $TotalPending > 0;
			$HTML = '';
			if (Permission::Sufficient('staff') || $sameUser){
				$pendingCountReadable = ($hasPending>0?"<strong>$TotalPending</strong>":'no');
				$posts = CoreUtils::MakePlural('reservation', $TotalPending);
				$HTML .= <<<HTML
<section class='pending-reservations'>
<h2>{$PrivateSection}Pending reservations</h2>
					<span>$YouHave $pendingCountReadable pending $posts
HTML;
				if ($hasPending)
					$HTML .= " which ha".($TotalPending!==1?'ve':'s')."n't been marked as finished yet";
				$HTML .= ".";
				if ($sameUser)
					$HTML .= " Please keep in mind that the global limit is 4 at any given time. If you reach the limit, you can't reserve any more images until you finish or cancel some of your pending reservations.";
				$HTML .= "</span>";

				if ($hasPending){
					$Posts = array_merge(
						Posts::GetReservationsSection($PendingReservations, RETURN_ARRANGED)['unfinished'],
						array_filter(array_values(Posts::GetRequestsSection($PendingRequestReservations, RETURN_ARRANGED)['unfinished']))
					);
					usort($Posts, function($a, $b){
						$a = strtotime($a['posted']);
						$b = strtotime($b['posted']);

						return -($a < $b ? -1 : ($a === $b ? 0 : 1));
					});
					$LIST = '';
					foreach ($Posts as $p){
						list($link,$page) = Posts::GetLink($p);
						$label = !empty($p['label']) ? "<span class='label'>{$p['label']}</span>" : '';
						$is_request = isset($p['rq']);
						$reservation_time_known = !empty($p['reserved_at']);
						$posted = Time::Tag($is_request && $reservation_time_known ? $p['reserved_at'] : $p['posted']);
						$PostedAction = $is_request && !$reservation_time_known ? 'Posted' : 'Reserved';

						$LIST .= <<<HTML
<li>
	<div class='image screencap'>
		<a href='$link'><img src='{$p['preview']}'></a>
	</div>
	$label
	<em>$PostedAction under <a href='$link'>$page</a> $posted</em>
	<div>
		<a href='$link' class='btn blue typcn typcn-arrow-forward'>View</a>
		<button class='red typcn typcn-user-delete cancel'>Cancel</button>
	</div>
</li>
HTML;
					}
					$HTML .= "<ul>$LIST</ul>";
				}
				$HTML .= "</section>";
			}
			return $HTML;
		}
		
		static function GetVectorAppClassName($User){
			$pref = UserPrefs::Get('p_vectorapp', $User['id']);
			return !empty($pref) ? " app-$pref" : '';
		}

		static function ValidateName($key, $errors, $optional, $method_get = false){
			return (new Input($key,'username',array(
				Input::IS_OPTIONAL => true,
				Input::METHOD_GET => $method_get,
				Input::CUSTOM_ERROR_MESSAGES => $errors ?? array(
					Input::ERROR_MISSING => 'Username (@value) is missing',
					Input::ERROR_INVALID => 'Username (@value) is invalid',
				)
			)))->out();
		}
	}
