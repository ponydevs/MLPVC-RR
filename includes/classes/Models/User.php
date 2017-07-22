<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Appearances;
use App\CachedFile;
use App\CoreUtils;
use App\DB;
use App\DeviantArt;
use App\Exceptions\NoPCGSlotsException;
use App\Logs;
use App\Models\Logs\Banish;
use App\Models\Logs\Unbanish;
use App\Models\Logs\DANameChange;
use App\Pagination;
use App\Permission;
use App\Posts;
use App\Time;
use App\UserPrefs;
use App\Users;

/**
 * @inheritdoc
 * @property string         $role
 * @property string         $rolelabel        (Via magic method)
 * @property DateTime       $signup_date
 * @property Session[]      $sessions
 * @property Notification[] $notifications
 * @property Event[]        $submitted_events
 * @property Event[]        $finalized_events
 * @property Appearance[]   $pcg_appearances
 * @property DiscordMember  $discord_member
 * @property Log[]          $logs
 * @property DANameChange[] $name_changes
 * @property Banish[]       $banishments
 * @property Unbanish[]     $unbanishments
 * @method static User find(...$args)
 */
class User extends AbstractUser implements LinkableInterface {
	static $table_name = 'users';

	public static $has_many = [
		['sessions', 'order' => 'lastvisit desc'],
		['notifications', 'foreign_key' => 'user'],
		['submitted_events', 'class' => 'Event', 'foreign_key' => 'submitted_by'],
		['finalized_events', 'class' => 'Event', 'foreign_key' => 'finalized_by'],
		['pcg_appearances', 'class' => 'Appearance', 'foreign_key' => 'owner_id'],
		['logs', 'class' => 'Logs\Log', 'foreign_key' => 'initiator'],
		['name_changes', 'class' => 'Logs\DANameChange', 'order' => 'entryid asc'],
		['banishments', 'class' => 'Logs\Banish', 'foreign_key' => 'target_id', 'order' => 'entryid desc'],
		['unbanishments', 'class' => 'Logs\Unbanish', 'foreign_key' => 'target_id', 'order' => 'entryid desc'],
	];
	public static $has_one = [
		['discord_member'],
	];
	public static $validates_size_of = [
		['name', 'within' => [1, 20]],
		['role', 'maximum' => 10],
		['avatar_url', 'maximum' => 255],
	];
	public static $validates_format_of = [
		['name', 'with' => '/^'.USERNAME_PATTERN.'$/'],
	];

	public function get_rolelabel(){
		return Permission::ROLES_ASSOC[$this->role] ?? 'Curious Pony';
	}

	public function toURL():string {
		return "/@$this->name";
	}

	const WITH_AVATAR = true;

	/**
	 * Local profile link generator
	 *
	 * @param bool $with_avatar
	 *
	 * @return string
	 */
	public function toAnchor(bool $with_avatar = false):string {
		$avatar = $with_avatar ? "<img src='{$this->avatar_url}' class='avatar' alt='avatar'> " : '';

		return "<a href='{$this->toURL()}' class='da-userlink local".($with_avatar ? ' with-avatar':'')."'>$avatar<span class='name'>{$this->name}</span></a>";
	}

	public function toDALink(){
		return 'http://'.strtolower($this->name).'.deviantart.com/';
	}

	/**
	 * DeviantArt profile link generator
	 *
	 * @param bool $with_avatar
	 *
	 * @return string
	 */
	public function toDAAnchor(bool $with_avatar = false):string {
		$link = $this->toDALink();

		$avatar = $with_avatar ? "<img src='{$this->avatar_url}' class='avatar' alt='avatar'> " : '';
		$withav = $with_avatar ? ' with-avatar' : '';
		return "<a href='$link' class='da-userlink$withav'>$avatar<span class='name'>{$this->name}</span></a>";
	}

	/**
	 * Returns avatar wrapper for user
	 *
	 * @param string $vectorapp
	 *
	 * @return string
	 */
	public function getAvatarWrap(string $vectorapp = ''):string {
		if (empty($vectorapp))
			$vectorapp = $this->getVectorAppClassName();
		return "<div class='avatar-wrap$vectorapp'><img src='{$this->avatar_url}' class='avatar' alt='avatar'></div>";
	}

	public function getVectorAppClassName():string {
		$pref = UserPrefs::get('p_vectorapp', $this);

		return !empty($pref) ? " app-$pref" : '';
	}

	public function getVectorAppReadableName():string {
		$pref = UserPrefs::get('p_vectorapp', $this);

		return CoreUtils::$VECTOR_APPS[$pref] ?? 'unrecognized application';
	}

	public function getVectorAppIcon():string {
		$vectorapp = UserPrefs::get('p_vectorapp', $this);
		if (empty($vectorapp))
			return '';

		return "<img class='vectorapp-logo' src='/img/vapps/$vectorapp.svg' alt='$vectorapp logo' title='".$this->getVectorAppReadableName()." user'>";
	}

	public function getPendingReservationCount():int {
		$PendingReservations = DB::$instance->query(
			'SELECT (SELECT COUNT(*) FROM requests WHERE reserved_by = :uid AND deviation_id IS NULL)+(SELECT COUNT(*) FROM reservations WHERE reserved_by = :uid AND deviation_id IS NULL) as amount',
			['uid' => $this->id]
		);
		return $PendingReservations['amount'] ?? 0;
	}

	/**
	 * Update a user's role
	 *
	 * @param string $newgroup
	 * @param bool   $skip_log
	 *
	 * @return bool
	 * @throws \RuntimeException
	 */
	 public function updateRole(string $newgroup, bool $skip_log = false):bool {
	    $this->role = $newgroup;
	    $response = $this->save();

		if ($response && !$skip_log){
			Logs::logAction('rolechange', [
				'target' => $this->id,
				'oldrole' => $this->role,
				'newrole' => $newgroup
			]);
		}

		return (bool)$response;
	}

	/**
	 * Checks if a user is a club member
	 *
	 * @return bool
	 */
	public function isClubMember(){
		return $this->getClubRole() !== null;
	}

	/**
	 * @return null|string
	 */
	public function getClubRole():?string {
		return DeviantArt::getClubRole($this);
	}

	/**
	 * Get the number of requests finished by the user that have been accepted to the gallery
	 *
	 * @param bool $exclude_own Exclude requests made by the user
	 *
	 * @return int
	 */
	public function getApprovedFinishedRequestCount(bool $exclude_own = false):int {
		if ($exclude_own)
			DB::$instance->where('requested_by', $this->id, '!=');

		return $this->getApprovedFinishedRequestContributions();
	}

	public function getPCGAppearances(Pagination $Pagination = null, bool $countOnly = false){
		$limit = isset($Pagination) ? $Pagination->getLimit() : null;
		if (!$countOnly)
			DB::$instance->orderBy('order');
		$return = Appearances::get(null, $limit, $this->id, $countOnly ? Appearances::COUNT_COL : '*');
		return $countOnly ? (int)($return[0]['cnt'] ?? 0) : $return;
	}

	/**
	 * TODO Introduce a table and allow manually adding/removing slots + locking slot gains
	 *
	 * @param bool $throw
	 * @param bool $returnArray
	 *
	 * @return int|array
	 */
	public function getPCGAvailableSlots(bool $throw = true, bool $returnArray = false){
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

	const CONTRIB_CACHE_DURATION = Time::IN_SECONDS['hour'];

	public function getCachedContributions():array {
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
		$cols = $count ? 'COUNT(*) as cnt' : 'c.appearance_id, c.favme';
		$query =
			"SELECT $cols
			FROM cutiemarks c
			LEFT JOIN cached_deviations d ON d.id = c.favme
			LEFT JOIN appearances p ON p.id = c.appearance_id
			WHERE d.author = ? AND p.owner_id IS NULL";

		if ($count)
			return DB::$instance->querySingle($query, [$this->name])['cnt'];

		if ($pagination)
			$query .= ' ORDER BY p.order ASC '.$pagination->getLimitString();

		return Cutiemark::find_by_sql($query, [$this->name]);
	}

	/**
	 * @param string     $table      Specifies which post table to use for fetching
	 * @param bool       $count      Returns the count if true
	 * @param Pagination $pagination Pagination object used for getting the LIMIT part of the query
	 * @return int|array
	 */
	private function _getPostContributions(string $table, bool $count = true, Pagination $pagination = null){
		if ($table === 'requests')
			DB::$instance->where('requested_by', $this->id);
		else DB::$instance->where('reserved_by', $this->id);

		if ($count)
			return DB::$instance->count($table);

		$limit = isset($pagination) ? $pagination->getLimit() : null;
		return DB::$instance->orderBy(($table === 'requests' ? 'requested_at' : 'reserved_at'),'DESC')->get($table,$limit);
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
		if ($count)
			return DB::$instance->querySingle(
				'SELECT
					(SELECT COUNT(*) FROM requests WHERE reserved_by = :userid && deviation_id IS NOT NULL)
					+
					(SELECT COUNT(*) FROM reservations WHERE reserved_by = :userid && deviation_id IS NOT NULL)
				as cnt', ['userid' => $this->id])['cnt'];

		$cols = 'id, label, reserved_by, reserved_by, reserved_at, finished_at, preview, lock, season, episode, deviation_id';
		/** @noinspection SqlInsertValues */
		$query =
			"SELECT * FROM (
				SELECT $cols, requested_by, requested_at, requested_at as posted FROM requests WHERE reserved_by = :userid AND deviation_id IS NOT NULL
				UNION ALL
				SELECT $cols, null as requested_by, null as requested_at, reserved_at as posted FROM reservations WHERE reserved_by = :userid AND deviation_id IS NOT NULL
			) t";
		if ($pagination)
			$query .= ' ORDER BY posted DESC '.$pagination->getLimitString();
		return DB::$instance->query($query, ['userid' => $this->id]);
	}

	/**
	 * @param bool       $count       Returns the count if true
	 * @param Pagination $pagination  Pagination object used for getting the LIMIT part of the query
	 *
	 * @return int|array
	 */
	public function getApprovedFinishedRequestContributions(bool $count = true, Pagination $pagination = null){

		DB::$instance->where('deviation_id IS NOT NULL')->where('reserved_by',$this->id)->where('lock',1);

		if ($count)
			return DB::$instance->count('requests');

		$limit = isset($pagination) ? $pagination->getLimit() : null;
		return DB::$instance->orderBy('finished_at','DESC')->get('requests',$limit);
	}

	private function _getContributions():array {
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
		$brokenVid = DB::$instance->querySingle(
			'SELECT COUNT(v.entryid) as cnt
			FROM log l
			LEFT JOIN log__video_broken v ON l.refid = v.entryid
			WHERE l.initiator = ?', [$this->id])['cnt'];
		if ($brokenVid > 0)
			$contribs[] = [$brokenVid, 'broken video', 'reported'];

		// Broken video reports
		$approvedPosts = DB::$instance->querySingle(
			'SELECT COUNT(p.entryid) as cnt
			FROM log l
			LEFT JOIN log__post_lock p ON l.refid = p.entryid
			WHERE l.initiator = ?', [$this->id])['cnt'];
		if ($approvedPosts > 0)
			$contribs[] = [$approvedPosts, 'post', 'marked approved'];

		return $contribs;
	}

	public function isDiscordMember():bool {
		return $this->getDiscordIdentity() instanceof DiscordMember;
	}

	public function getDiscordIdentity():?DiscordMember {
		return $this->discord_member;
	}

	/**
	 * @param Event $event
	 * @param string $cols
	 *
	 * @return EventEntry[]
	 */
	public function getEntriesFor(Event $event, string $cols = '*'):?array {


		return DB::$instance->where('submitted_by', $this->id)->where('eventid', $event->id)->get('events__entries',null,$cols);
	}

	const YOU_HAVE = [
		1 => 'You have',
		0 => 'This user has',
	];

	/**
	 * @param bool $requests
	 *
	 * @return array
	 */
	private function _getPendingPostsArgs(bool $requests):array {
		return [
			'all', [
				'conditions' => [
					'reserved_by = ? AND deviation_id IS NULL',
					$this->id,
				],
			],
		];
	}

	/**
	 * @return Reservation[] All reservations from the databae that the user posted but did not finish yet
	 */
	private function _getPendingReservations(){
		return Reservation::find(...$this->_getPendingPostsArgs(false));
	}

	/**
	 * @return Request[] All requests from the databae that the user reserved but did not finish yet
	 */
	private function _getPendingRequestReservations(){
		return Request::find(...$this->_getPendingPostsArgs(true));
	}

	/**
	 * @param bool $sameUser
	 * @param bool $isMember
	 *
	 * @return string
	 */
	public function getPendingReservationsHTML($sameUser, $isMember = true):string {
		$visitorStaff = Permission::sufficient('staff');
		$staffVisitingMember = $visitorStaff && $isMember;
		$YouHave = self::YOU_HAVE[(int)$sameUser];
		$PrivateSection = $sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:'';

		if ($staffVisitingMember || ($isMember && $sameUser)){
			$cols = '';
			$PendingReservations = $this->_getPendingReservations();
			$PendingRequestReservations = $this->_getPendingRequestReservations();
			$TotalPending = count($PendingReservations)+count($PendingRequestReservations);
			$hasPending = $TotalPending > 0;
		}
		else {
			$TotalPending = 0;
			$hasPending = false;
		}
		$HTML = '';
		if ($staffVisitingMember || $sameUser){
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
					$HTML .= ' which ha'.($TotalPending!==1?'ve':'s').'n’t been marked as finished yet';
				$HTML .= '.';
				if ($sameUser)
					$HTML .= ' Please keep in mind that the global limit is 4 at any given time. If you reach the limit, you can’t reserve any more images until you finish or cancel some of your pending reservations.';
				$HTML .= '</span>';

				if ($hasPending){
					/** @var $Posts Post[] */
					$Posts = array_merge(
						Posts::getReservationsSection($PendingReservations, RETURN_ARRANGED)['unfinished'],
						array_filter(array_values(Posts::getRequestsSection($PendingRequestReservations, RETURN_ARRANGED)['unfinished']))
					);
					usort($Posts, function(Post $a, Post $b){
						$a = strtotime($a->posted);
						$b = strtotime($b->posted);

						return $b <=> $a;
					});
					$LIST = '';
					foreach ($Posts as $Post){
						$postLink = $Post->toURL();
						$postAnchor = $Post->toAnchor();
						$label = !empty($Post->label) ? "<span class='label'>{$Post->label}</span>" : '';
						$actionCond = $Post->is_request && !empty($Post->reserved_at);
						$posted = Time::tag($actionCond ? $Post->reserved_at : $Post->posted);
						$PostedAction = $actionCond ? 'Reserved' : 'Posted';
						$contestable = $Post->isOverdue() ? Posts::CONTESTABLE : '';
						$broken = $Post->broken ? Posts::BROKEN : '';
						$fixbtn = $Post->broken ? "<button class='darkblue typcn typcn-spanner fix'>Fix</button>" : '';

						$LIST .= <<<HTML
<li>
	<div class='image screencap'>
		<a href='$postLink'><img src='{$Post->preview}'></a>
	</div>
	$label
	<em>$PostedAction under $postAnchor $posted</em>
	$contestable
	$broken
	<div>
		$fixbtn
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
				$HTML .= '<p>Reservations are a way to allow Club Members to claim requests on the site as well as claim screenshots of their own, in order to reduce duplicate submissions to the group. You can use the button above to get random requests from the site that you can draw as practice, or to potentially submit along with your application to the club.</p>';
			}

			$HTML .= '</section>';
		}
		return $HTML;
	}

	const NOT_APPROVED_POST_COLS = 'id, season, episode, deviation_id';

	private function _getNotApprovedPostArgs(){
		return [
			'all', [
				'conditions' => [
					'reserved_by = ? AND deviation_id IS NOT NULL AND "lock" IS NOT true',
					$this->id,
				],
			],
		];
	}

	/**
	 * @return Request[] All requests that have been finished by the user but not yet accepted into the club
	 */
	private function _getNotApprovedRequests(){
		return Request::find(...$this->_getNotApprovedPostArgs());
	}

	/**
	 * @return Reservation[] All reservations that have been finished by the user but not yet accepted into the club
	 */
	private function _getNotApprovedReservations(){
		return Reservation::find(...$this->_getNotApprovedPostArgs());
	}

	public function getAwaitingApprovalHTML(bool $sameUser, bool $wrap = WRAP):string {
		$cols = 'id, season, episode, deviation_id';
		/** @var $AwaitingApproval \App\Models\Post[] */
		$AwaitingApproval = array_merge(
			$this->_getNotApprovedRequests(),
			$this->_getNotApprovedReservations()
		);
		$AwaitCount = count($AwaitingApproval);
		$them = $AwaitCount!==1?'them':'it';
		$YouHave = self::YOU_HAVE[(int)$sameUser];
		$privacy = $sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['public']:'';
		$HTML = "<h2>{$privacy}Vectors waiting for approval</h2>";
		if ($sameUser)
			$HTML .= '<p>After you finish an image and submit it to the group gallery, an admin will check your vector and may ask you to fix some issues on your image, if any. After an image is accepted to the gallery, it can be marked as "approved", which gives it a green check mark, indicating that it’s most likely free of any errors.</p>';
		$youHaveAwaitCount = "$YouHave ".(!$AwaitCount?'no':"<strong>$AwaitCount</strong>");
		$images = CoreUtils::makePlural('image', $AwaitCount);
		$append = !$AwaitCount
			? '.'
			: ', listed below.'.(
				$sameUser
				? " Please submit $them to the group gallery as soon as possible to have $them spot-checked for any issues. As stated in the rules, the goal is to add finished images to the group gallery, making $them easier to find for everyone.".(
					$AwaitCount>10
					? ' You seem to have a large number of images that have not been approved yet, please submit them to the group soon if you haven’t already.'
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
				$url = "http://fav.me/{$Post->deviation_id}";
				$postLink = $Post->toURL();
				$postAnchor = $Post->toAnchor();
				$checkBtn = Permission::sufficient('member') ? "<button class='green typcn typcn-tick check'>Check</button>" : '';

				$HTML .= <<<HTML
<li id="{$Post->getID()}">
	<div class="image deviation">
		<div class="post-deviation-promise" data-post="{$Post->getID()}"></div>
	</div>
	<span class="label hidden"><a href="$url" target="_blank" rel="noopener"></a></span>
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

		return $wrap ? "<section class='awaiting-approval'>$HTML</section>" : $HTML;
	}
}
