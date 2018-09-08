<?php

namespace App;

use App\Models\Logs\DANameChange;
use App\Models\Post;
use App\Models\Session;
use App\Models\User;
use App\Exceptions\CURLRequestException;

class Users {
	public const RESERVATION_LIMIT = 4;

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

		$user = DB::$instance->where($column, $value)->getOne('users');

		if (empty($user) && $column === 'name'){
			global $USERNAME_REGEX;
			if ($USERNAME_REGEX->match($value)){
				$user = self::fetch([$value]);
			}
		}

		if (isset($user->name))
			self::$_USER_CACHE[$user->name] = $user;

		return $user;
	}

	/**
	 * User Information Fetching
	 * -------------------------
	 * Fetch user info from DeviantArt's API
	 * If multiple usernames are passed as an array it will not return a value
	 *
	 * @param string[] $usernames
	 *
	 * @return User|null|false|void
	 * @throws \Exception
	 */
	public static function fetch($usernames){
		global $USERNAME_REGEX;

		$count = \count($usernames);
		if ($count < 1)
			throw new \RuntimeException('No usernames specified');
		else if ($count > 50)
			throw new \RuntimeException("Too many usernames specified ($count)");
		$single_user = $count === 1;
		$fetch_index = 0;
		$fetch_params = [];
		foreach ($usernames as $un){
			if ($single_user){
				$old_name = DANameChange::find_by_old($un);
				if (!empty($old_name))
					return $old_name->user;
			}

			$fetch_params["usernames[{$fetch_index}]"] = $un;
			$fetch_index++;
		}

		try {
			$userdata = DeviantArt::request('user/whois', null, $fetch_params);
		}
		catch (CURLRequestException $e){
			if ($single_user)
				return false;
			else throw new \RuntimeException('Failed to fetch users: '.$e->getMessage()."\nStack trace:\n".$e->getTraceAsString());
		}

		if ($single_user && empty($userdata['results'][0]))
			return false;

		foreach ($userdata['results'] as $userdata){
			$id = strtolower($userdata['userid']);

			$db_user = User::find($id);
			$user_exists = !empty($db_user);
			if (!$user_exists)
				$db_user = new User([ 'id' => $id ]);

			$db_user->name = $userdata['username'];
			$db_user->avatar_url = URL::makeHttps($userdata['usericon']);

			$club_role = DeviantArt::getClubRoleByName($userdata['username']);
			if (!empty($club_role))
				$db_user->role = $club_role;

			if (!$db_user->save())
				throw new \RuntimeException('Saving user data failed'.(Permission::sufficient('developer')?': '.DB::$instance->getLastError():''));

			if (!$user_exists)
				Logs::logAction('userfetch', ['userid' => $db_user->id]);
			$names = \array_slice($usernames, 0);
			if ($user_exists && $db_user->name !== $usernames[0])
				$names[] = $db_user->name;
			foreach ($names as $name){
				if (strcasecmp($name, $db_user->name) !== 0){
					Logs::logAction('da_namechange', [
						'old' => $name,
						'new' => $db_user->name,
						'user_id' => $id,
					], Logs::FORCE_INITIATOR_WEBSERVER);
				}
			}
			if ($single_user)
				return $db_user;
		}
	}

	/**
	 * Check maximum simultaneous reservation count
	 *
	 * @param bool $return_as_bool
	 *
	 * @return bool|null
	 */
	public static function checkReservationLimitReached(bool $return_as_bool = false){
		$resserved_count = DB::$instance
			->where('reserved_by', Auth::$user->id)
			->where('deviation_id IS NULL')
			->count('posts');

		$overTheLimit = !empty($resserved_count) && $resserved_count >= self::RESERVATION_LIMIT;
		if ($return_as_bool)
			return $overTheLimit;
		if ($overTheLimit)
			Response::fail("You've already reserved {$resserved_count} images, and you can't have more than 4 pending reservations at a time. You can review your reservations on your <a href='/user'>Account page</a>, finish at least one of them before trying to reserve another image.");
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
		if (Cookie::exists('access')){
			Auth::$session = Session::find_by_token(CoreUtils::sha256(Cookie::get('access')));
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
			Cookie::delete('access', Cookie::HTTP_ONLY);
	}

	public const PROFILE_SECTION_PRIVACY_LEVEL = [
		'developer' => "<span class='typcn typcn-cog color-red' title='Visible to: developer'></span>",
		'public' => "<span class='typcn typcn-world color-blue' title='Visible to: public'></span>",
		'staff' => "<span class='typcn typcn-lock-closed' title='Visible to: you & group staff'></span>",
		'staffOnly' => "<span class='typcn typcn-lock-closed color-red' title='Visible to: group staff'></span>",
		'private' => "<span class='typcn typcn-lock-closed color-green' title='Visible to: you'></span>",
	];

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

	public static function getContributionsCacheDuration(string $unit = 'hour'):string {
		$cache_dur = User::CONTRIB_CACHE_DURATION / Time::IN_SECONDS[$unit];
		return CoreUtils::makePlural($unit, $cache_dur, PREPEND_NUMBER);
	}

	//const NOPE = '<em>Nope</em>';
	public const NOPE = '<span class="typcn typcn-times"></span>';

	private static function _contribItemFinished(Post $item):string {
		if ($item->deviation_id === null)
			return self::NOPE;
		$HTML = "<div class='deviation-promise image-promise' data-favme='{$item->deviation_id}'></div>";
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
					$deviation = $item->favme !== null ? "<div class='deviation-promise image-promise' data-favme='{$item->favme}'></div>" : self::NOPE;

					$TR = <<<HTML
<td class="pony-link">$preview</td>
<td>$deviation</td>
HTML;

				break;
				case 'requests':
					/** @var $item Post */
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
					/** @var $item Post */
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
					/** @var $item Post */
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
					/** @var $item Post */
					$preview = $item->toAnchorWithPreview();
					$posted_by = $item->requester->toAnchor();
					$requested_at = Time::tag($item->requested_at);
					$posted = "<span class='typcn typcn-user' title='By'></span> $posted_by<br><span class='typcn typcn-time'></span> $requested_at";
					$finished = $item->finished_at === null ? '<span class="typcn typcn-time missing-time" title="Time data missing"></span>' : Time::tag($item->finished_at);
					$deviation = "<div class='deviation-promise image-promise' data-favme='{$item->deviation_id}'></div>";
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
