<?php

namespace App\Models;

use App\Appearances;
use App\Exceptions\NoPCGSlotsException;
use App\HTTP;
use App\CoreUtils;
use App\JSON;
use App\Logs;
use App\Pagination;
use App\Permission;
use App\RegExp;
use App\Response;
use App\Time;
use App\UserPrefs;
use App\Users;

class User extends AbstractUser {
	/** @var string */
	public
		$id,
		$name,
		$role,
		$avatar_url,
		$signup_date,
		$rolelabel;

	/** @param array|object */
	public function __construct($iter = null){
		parent::__construct($this, $iter);

		if (!empty($this->role))
			$this->rolelabel = Permission::ROLES_ASSOC[$this->role];
	}

	const
		LINKFORMAT_FULL = 0,
		LINKFORMAT_TEXT = 1,
		LINKFORMAT_URL  = 2;

	/**
	 * Local profile link generator
	 *
	 * @param int $format
	 *
	 * @throws \Exception
	 *
	 * @return string
	 */
	function getProfileLink(int $format = self::LINKFORMAT_TEXT):string {
		$Username = $this->name;
		$avatar = $format == self::LINKFORMAT_FULL ? "<img src='{$this->avatar_url}' class='avatar' alt='avatar'> " : '';

		return "<a href='/@$Username' class='da-userlink".($format == self::LINKFORMAT_FULL ? ' with-avatar':'')."'>$avatar<span class='name'>$Username</span></a>";
	}

	/**
	 * DeviantArt profile link generator
	 *
	 * @param int $format
	 *
	 * @return string
	 */
	function getDALink(int $format = self::LINKFORMAT_FULL):string {
		$Username = $this->name;
		$username = strtolower($Username);
		$link = "http://$username.deviantart.com/";
		if ($format === self::LINKFORMAT_URL) return $link;

		$avatar = $format == self::LINKFORMAT_FULL ? "<img src='{$this->avatar_url}' class='avatar' alt='avatar'> " : '';
		$withav = $format == self::LINKFORMAT_FULL ? ' with-avatar' : '';
		return "<a href='$link' class='da-userlink$withav'>$avatar<span class='name'>$Username</span></a>";
	}

	/**
	 * Returns avatar wrapper for user
	 *
	 * @param string $vectorapp
	 *
	 * @return string
	 */
	function getAvatarWrap(string $vectorapp = ''):string {
		if (empty($vectorapp))
			$vectorapp = $this->getVectorAppClassName();
		return "<div class='avatar-wrap$vectorapp'><img src='{$this->avatar_url}' class='avatar' alt='avatar'></div>";
	}

	function getVectorAppClassName():string {
		$pref = UserPrefs::get('p_vectorapp', $this->id);

		return !empty($pref) ? " app-$pref" : '';
	}

	function getVectorAppName():string {
		$pref = UserPrefs::get('p_vectorapp', $this->id);

		return CoreUtils::$VECTOR_APPS[$pref] ?? 'unrecognized application';
	}

	function getPendingReservationCount():int {
		global $Database;
		$PendingReservations = $Database->rawQuery(
			'SELECT (SELECT COUNT(*) FROM requests WHERE reserved_by = :uid && deviation_id IS NULL)+(SELECT COUNT(*) FROM reservations WHERE reserved_by = :uid && deviation_id IS NULL) as amount',
			array('uid' => $this->id)
		);
		return $PendingReservations['amount'] ?? 0;
	}

	/**
	 * Update a user's role
	 *
	 * @param string $newgroup
	 *
	 * @return bool
	 */
	 function updateRole(string $newgroup):bool {
		global $Database;
		$response = $Database->where('id', $this->id)->update('users', array('role' => $newgroup));

		if ($response){
			Logs::logAction('rolechange', array(
				'target' => $this->id,
				'oldrole' => $this->role,
				'newrole' => $newgroup
			));
		}

		return (bool)$response;
	}

	/**
	 * Checks if a user is a club member
	 * (currently only works for recently added members, does not deal with old members or admins)
	 *
	 * @return bool
	 */
	function isClubMember(){
		$RecentlyJoined = HTTP::legitimateRequest('http://mlp-vectorclub.deviantart.com/modals/memberlist/');

		return !empty($RecentlyJoined['response'])
			&& preg_match(new RegExp('<a class="[a-z ]*username" href="http://'.strtolower($this->name).'.deviantart.com/">'.USERNAME_PATTERN.'</a>'), $RecentlyJoined['response']);
	}

	/**
	 * Get the number of requests finished by the user that have been accepted to the gallery
	 *
	 * @param bool $exclude_own Exclude requests made by the user
	 *
	 * @return int
	 */
	function getApprovedFinishedRequestCount(bool $exclude_own = false):int {
		global $Database;

		if ($exclude_own)
			$Database->where('requested_by', $this->id, '!=');

		return $Database->where('deviation_id IS NOT NULL')->where('reserved_by',$this->id)->where('lock',1)->count('requests');
	}

	/**
	 * Get the number of posts finished by the user
	 *
	 * @return int
	 */
	function getFinishedPostCount():int {
		global $Database;

		return $Database->rawQuerySingle(
			'SELECT
				(SELECT COUNT(*) FROM requests WHERE reserved_by = :userid && deviation_id IS NOT NULL)
				+
				(SELECT COUNT(*) FROM reservations WHERE reserved_by = :userid && deviation_id IS NOT NULL)
			as cnt', array('userid' => $this->id))['cnt'];
	}

	function getPCGAppearances(Pagination $Pagination = null, bool $countOnly = false){
		global $Database;

		$limit = isset($Pagination) ? $Pagination->getLimit() : null;
		if (!$countOnly)
			$Database->orderBy('order','ASC');
		$return = Appearances::get(null, $limit, $this->id, $countOnly ? 'COUNT(*) as cnt' : '*');
		return $countOnly ? intval($return[0]['cnt'] ?? 0) : $return;
	}

	/**
	 * @param bool $throw
	 * @param bool $returnArray
	 *
	 * @return int|array
	 */
	function getPCGAvailableSlots(bool $throw = true, bool $returnArray = false){
		$postcount = $this->getApprovedFinishedRequestCount(true);
		$totalslots = floor($postcount/10);
		if (Permission::sufficient('staff', $this->role))
			$totalslots++;
		if ($totalslots === 0){
			if ($throw)
				throw new NoPCGSlotsException();
			return $returnArray ? [
				'postcount' => $postcount,
				'totalslots' => 0,
				'available' => 0,
			] :0;
		}
		$usedSlots = $this->getPCGAppearances(null, true);

		$available = $totalslots-$usedSlots;
		if (!$returnArray)
			return $available;
		return [
			'postcount' => $postcount,
			'totalslots' => $totalslots,
			'available' => $available,
		];
	}

	const CONTRIB_CHACHE_DURATION = 12*Time::IN_SECONDS['hour'];

	function getCachedContributions():array {
		$CachePath = APPATH."../fs/contribs/{$this->id}.json";
		if (file_exists($CachePath) && filemtime($CachePath) > time() - self::CONTRIB_CHACHE_DURATION)
			return JSON::decode(file_get_contents($CachePath));

		$data = $this->_getContributions();
		CoreUtils::createUploadFolder($CachePath);
		file_put_contents($CachePath, JSON::encode($data));
		return $data;
	}

	private function _getContributions():array {
		global $Database;

		$contribs = [];

		// Cutie mark vectors submitted
		$cmContrib = $Database->rawQuerySingle(
			'SELECT COUNT(*) as cnt
			FROM cutiemarks c
			LEFT JOIN "cached-deviations" d ON d.id = c.favme
			WHERE d.author = ?', array($this->name))['cnt'];
		if ($cmContrib > 0)
			$contribs[] = [$cmContrib, 'cutie mark vector', 'provided'];

		// Requests posted
		$reqPost = $Database->where('requested_by', $this->id)->count('requests');
		if ($reqPost > 0)
			$contribs[] = [$reqPost, 'request', 'posted'];

		// Reservations posted
		$resPost = $Database->where('reserved_by', $this->id)->count('reservations');
		if ($resPost > 0)
			$contribs[] = [$resPost, 'reservation', 'posted'];

		// Finished post count
		$finPost = $this->getFinishedPostCount();
		if ($finPost > 0)
			$contribs[] = [$finPost, 'post', 'finished'];

		// Finished requests
		$reqFin = $this->getApprovedFinishedRequestCount();
		if ($reqFin > 0)
			$contribs[] = [$reqFin, 'request', 'fulfilled'];

		// Broken video reports
		$brokenVid = $Database->rawQuerySingle(
			'SELECT COUNT(v.entryid) as cnt
			FROM log l
			LEFT JOIN log__video_broken v ON l.refid = v.entryid
			WHERE l.initiator = ?',array($this->id))['cnt'];
		if ($brokenVid > 0)
			$contribs[] = [$brokenVid, 'broken video', 'reported'];

		// Broken video reports
		$approvedPosts = $Database->rawQuerySingle(
			'SELECT COUNT(p.entryid) as cnt
			FROM log l
			LEFT JOIN log__post_lock p ON l.refid = p.entryid
			WHERE l.initiator = ?',array($this->id))['cnt'];
		if ($approvedPosts > 0)
			$contribs[] = [$approvedPosts, 'post', 'marked approved'];

		return $contribs;
	}

	public function isDiscordMember(){
		global $Database;

		return $Database->where('userid', $this->id)->has('discord-members');
	}

	public function getDiscordIdentity():?AbstractUser {
		global $Database;

		return DiscordMember::of($this);
	}

	/**
	 * @param Event $event
	 * @param string $cols
	 *
	 * @return EventEntry[]
	 */
	public function getEntriesFor(Event $event, string $cols = '*'):?array {
		global $Database;

		return $Database->where('submitted_by', $this->id)->where('eventid', $event->id)->get('events__entries',null,$cols);
	}
}
