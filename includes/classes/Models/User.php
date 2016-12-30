<?php

namespace App\Models;

use App\Appearances;
use App\Exceptions\NoPCGSlotsException;
use App\HTTP;
use App\Models\AbstractFillable;
use App\CoreUtils;
use App\Logs;
use App\Pagination;
use App\Permission;
use App\RegExp;
use App\UserPrefs;
use App\Users;

class User extends AbstractFillable {
	/** @var string */
	public
		$id,
		$name,
		$role,
		$avatar_url,
		$signup_date,
		$rolelabel;
	/** @var bool */
	public $fake = false;
	// TODO Update when Session class is made
	/** @var array */
	public $Session;

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

	static $FAKE_USER = array(
		'name' => 'null',
		'avatar_url' => '/img/blank-pixel.png',
		'fake' => true,
	);

	/**
	 * Checks if $User is empty and fills it with fake data if it is
	 *
	 * @param string $method
	 */
	private function _checkEmptyUser(string $method){
		if (empty($this->name) && empty($this->avatar_url)){
			error_log("\$User is empty, fake user data used in $method.\nValue: ".var_export($this, true)."\nBacktrace:\n".((new \Exception)->getTraceAsString()));
			foreach (self::$FAKE_USER as $k => $v)
				$this->$k = $v;
		}
	}

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
		$this->_checkEmptyUser(__METHOD__);

		$Username = $this->name;
		$avatar = $format == self::LINKFORMAT_FULL ? "<img src='{$this->avatar_url}' class='avatar' alt='avatar'> " : '';
		$href = empty($this->fake) ? " href='/@$Username'" : '';

		return "<a$href class='da-userlink".($format == self::LINKFORMAT_FULL ? ' with-avatar':'')."'>$avatar<span class='name'>$Username</span></a>";
	}

	/**
	 * DeviantArt profile link generator
	 *
	 * @param int $format
	 *
	 * @return string
	 */
	function getDALink(int $format = self::LINKFORMAT_FULL):string {
		$this->_checkEmptyUser(__METHOD__);

		$Username = $this->name;
		$username = strtolower($Username);
		$link = "http://$username.deviantart.com/";
		if ($format === self::LINKFORMAT_URL) return $link;

		$avatar = $format == self::LINKFORMAT_FULL ? "<img src='{$this->avatar_url}' class='avatar' alt='avatar'> " : '';
		$withav = $format == self::LINKFORMAT_FULL ? ' with-avatar' : '';
		$href = empty($this->fake) ? "href='$link'" : '';
		return "<a $href class='da-userlink$withav'>$avatar<span class='name'>$Username</span></a>";
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

	function getPendingReservationCount(){
		global $Database;
		$PendingReservations = $Database->rawQuery(
			'SELECT (SELECT COUNT(*) FROM requests WHERE reserved_by = :uid && deviation_id IS NULL)+(SELECT COUNT(*) FROM reservations WHERE reserved_by = :uid && deviation_id IS NULL) as amount',
			array('uid' => $this->id)
		);
		$TotalPending = $PendingReservations['amount'] ?? 0;
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
	 * Get the number of posts finished by the user that have been accepted to the gallery
	 */
	function getApprovedFinishedRequestCount(){
		global $Database;

		return $Database->where('deviation_id IS NOT NULL')->where('reserved_by',$this->id)->where('lock',1)->count('requests');
	}

	function getPCGAppearances(Pagination $Pagination = null, bool $countOnly = false){
		global $Database;

		$limit = isset($Pagination) ? $Pagination->getLimit() : null;
		if (!$countOnly)
			$Database->orderBy('order','ASC');
		$return = Appearances::get(null, $limit, $this->id,$countOnly ? 'COUNT(*) as cnt' : '*');
		return $countOnly ? ($return['cnt'] ?? 0) : $return;
	}

	/**
	 * @param bool $throw
	 * @param bool $returnArray
	 *
	 * @return int|array
	 */
	function getPCGAvailableSlots(bool $throw = true, bool $returnArray = false){
		$postcount = $this->getApprovedFinishedRequestCount();
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
}
