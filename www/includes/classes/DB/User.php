<?php

namespace DB;

use Log;
use UserPrefs;

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
			$this->rolelabel = \Permission::$ROLES_ASSOC[$this->role];
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
	 * @throws Exception
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
		$href = empty($this->fake) ? " href='$link'" : '';
		return "<a$href class='da-userlink'>$avatar<span class='name'>$Username</span></a>";
	}

	/**
	 * Returns avatar wrapper for user
	 *
	 * @return string
	 */
	function getAvatarWrap():string {
		$vectorapp = $this->getVectorAppClassName();
		return "<div class='avatar-wrap$vectorapp'><img src='{$this->avatar_url}' class='avatar' alt='avatar'></div>";
	}

	function getVectorAppClassName():string {
		$pref = \UserPrefs::Get('p_vectorapp', $this->id);

		return !empty($pref) ? " app-$pref" : '';
	}

	function getVectorAppName():string {
		$pref = \UserPrefs::Get('p_vectorapp', $this->id);

		return \CoreUtils::$VECTOR_APPS[$pref] ?? 'unrecognized application';
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
			Log::Action('rolechange', array(
				'target' => $this->id,
				'oldrole' => $this->role,
				'newrole' => $newgroup
			));
		}

		return (bool)$response;
	}
}
