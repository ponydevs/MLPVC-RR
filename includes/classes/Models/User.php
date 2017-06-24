<?php

namespace App\Models;

use App\Appearances;
use App\CachedFile;
use App\DeviantArt;
use App\Exceptions\NoPCGSlotsException;
use App\CoreUtils;
use App\Logs;
use App\Pagination;
use App\Permission;
use App\Time;
use App\UserPrefs;

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
		$url = "/@$Username";
		if ($format === self::LINKFORMAT_URL)
			return $url;
		$avatar = $format == self::LINKFORMAT_FULL ? "<img src='{$this->avatar_url}' class='avatar' alt='avatar'> " : '';

		return "<a href='$url' class='da-userlink".($format == self::LINKFORMAT_FULL ? ' with-avatar':'')."'>$avatar<span class='name'>$Username</span></a>";
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

	function getVectorAppReadableName():string {
		$pref = UserPrefs::get('p_vectorapp', $this->id);

		return CoreUtils::$VECTOR_APPS[$pref] ?? 'unrecognized application';
	}

	function getVectorAppIcon():string {
		$vectorapp = UserPrefs::get('p_vectorapp', $this->id);
		if (empty($vectorapp))
			return '';

		return "<img class='vectorapp-logo' src='/img/vapps/$vectorapp.svg' alt='$vectorapp logo' title='".$this->getVectorAppReadableName()." user'>";
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
	 *
	 * @return bool
	 */
	function isClubMember(){
		return DeviantArt::getClubRole($this) !== null;
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

		return $this->getApprovedFinishedRequestContributions();
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

	const CONTRIB_CACHE_DURATION = 12* Time::IN_SECONDS['hour'];

	function getCachedContributions():array {
		$cache = CachedFile::init(FSPATH."contribs/{$this->id}.json", self::CONTRIB_CACHE_DURATION);
		if (!$cache->expired())
			return $cache->read();

		$data = $this->_getContributions();
		$cache->update($data);
		return $data;
	}

	/**
	 * @param bool       $count      Returns the count if true
	 * @param Pagination $pagination Pagination object used for getting the LIMIT part of the query
	 * @return int|array
	 */
	public function getCMContributions(bool $count = true, Pagination $pagination = null){
		global $Database;

		$cols = $count ? 'COUNT(*) as cnt' : 'c.ponyid, c.favme';
		$query =
			"SELECT $cols
			FROM cutiemarks c
			LEFT JOIN \"cached-deviations\" d ON d.id = c.favme
			LEFT JOIN appearances p ON p.id = c.ponyid
			WHERE d.author = ? && p.owner IS NULL";

		if ($count)
			return $Database->rawQuerySingle($query, array($this->name))['cnt'];

		if ($pagination)
			$query .= ' ORDER BY p.order ASC '.$pagination->getLimitString();
		return $Database->setClass(Cutiemark::class)->rawQuery($query, array($this->name));

	}

	/**
	 * @param string     $table      Specifies which post table to use for fetching
	 * @param bool       $count      Returns the count if true
	 * @param Pagination $pagination Pagination object used for getting the LIMIT part of the query
	 * @return int|array
	 */
	private function _getPostContributions(string $table, bool $count = true, Pagination $pagination = null){
		global $Database;

		if ($table === 'requests')
			$Database->where('requested_by', $this->id);
		else $Database->where('reserved_by', $this->id);

		if ($count)
			return $Database->count($table);

		$limit = isset($pagination) ? $pagination->getLimit() : null;
		return $Database->orderBy('posted','DESC')->get($table,$limit);
	}

	/**
	 * @param bool       $count      Returns the count if true
	 * @param Pagination $pagination Pagination object used for getting the LIMIT part of the query
	 * @return int|array
	 */
	public function getRequestContributions(bool $count = true, Pagination $pagination = null){
		return $this->_getPostContributions('requests', $count, $pagination);
	}

	/**
	 * @param bool       $count      Returns the count if true
	 * @param Pagination $pagination Pagination object used for getting the LIMIT part of the query
	 * @return int|array
	 */
	public function getReservationContributions(bool $count = true, Pagination $pagination = null){
		return $this->_getPostContributions('reservations', $count, $pagination);
	}

	/**
	 * @param bool       $count      Returns the count if true
	 * @param Pagination $pagination Pagination object used for getting the LIMIT part of the query
	 * @return int|array
	 */
	public function getFinishedPostContributions(bool $count = true, Pagination $pagination = null){
		global $Database;

		if ($count)
			return $Database->rawQuerySingle(
				'SELECT
					(SELECT COUNT(*) FROM requests WHERE reserved_by = :userid && deviation_id IS NOT NULL)
					+
					(SELECT COUNT(*) FROM reservations WHERE reserved_by = :userid && deviation_id IS NOT NULL)
				as cnt', array('userid' => $this->id))['cnt'];

		$cols = "id, label, posted, reserved_by, preview, lock, season, episode, deviation_id";
		/** @noinspection SqlInsertValues */
		$query =
			"SELECT * FROM (
				SELECT $cols, requested_by, reserved_at FROM requests WHERE reserved_by = :userid && deviation_id IS NOT NULL
				UNION ALL
				SELECT $cols, null as requested_by, posted as reserved_at FROM reservations WHERE reserved_by = :userid && deviation_id IS NOT NULL
			) t";
		if ($pagination)
			$query .= ' ORDER BY posted DESC '.$pagination->getLimitString();
		return $Database->rawQuery($query, array('userid' => $this->id));
	}

	/**
	 * @param bool       $count      Returns the count if true
	 * @param Pagination $pagination Pagination object used for getting the LIMIT part of the query
	 * @return int|array
	 */
	public function getApprovedFinishedRequestContributions(bool $count = true, Pagination $pagination = null){
		global $Database;

		$Database->where('deviation_id IS NOT NULL')->where('reserved_by',$this->id)->where('lock',1);

		if ($count)
			return $Database->count('requests');

		$limit = isset($pagination) ? $pagination->getLimit() : null;
		return $Database->orderBy('finished_at','DESC')->get('requests',$limit);
	}

	private function _getContributions():array {
		global $Database;

		$contribs = [];

		// Cutie mark vectors submitted
		$cmContrib = $this->getCMContributions();
		if ($cmContrib > 0)
			$contribs['cms-provided'] = [$cmContrib, 'cutie mark vector', 'provided'];

		// Requests posted
		$reqPost = $this->getRequestContributions();
		if ($reqPost > 0)
			$contribs['requests'] = [$reqPost, 'request', 'posted'];

		// Reservations posted
		$resPost = $this->getReservationContributions();
		if ($resPost > 0)
			$contribs['reservations'] = [$resPost, 'reservation', 'posted'];

		// Finished post count
		$finPost = $this->getFinishedPostContributions();
		if ($finPost > 0)
			$contribs['finished-posts'] = [$finPost, 'post', 'finished'];

		// Finished requests
		$reqFin = $this->getApprovedFinishedRequestContributions();
		if ($reqFin > 0)
			$contribs['fulfilled-requests'] = [$reqFin, 'request', 'fulfilled'];

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
