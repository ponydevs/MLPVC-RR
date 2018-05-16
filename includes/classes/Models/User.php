<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Appearances;
use App\Auth;
use App\CachedFile;
use App\CoreUtils;
use App\DB;
use App\DeviantArt;
use App\Exceptions\NoPCGSlotsException;
use App\GlobalSettings;
use App\Logs;
use App\Models\Logs\DANameChange;
use App\Models\Logs\Log;
use App\NavBreadcrumb;
use App\Pagination;
use App\Permission;
use App\Posts;
use App\RegExp;
use App\Response;
use App\Time;
use App\UserPrefs;
use App\Users;

/**
 * @inheritdoc
 * @property string           $role
 * @property DateTime         $signup_date
 * @property Session[]        $sessions         (Via relations)
 * @property Notification[]   $notifications    (Via relations)
 * @property Event[]          $submitted_events (Via relations)
 * @property Event[]          $finalized_events (Via relations)
 * @property Appearance[]     $pcg_appearances  (Via relations)
 * @property DiscordMember    $discord_member   (Via relations)
 * @property Log[]            $logs             (Via relations)
 * @property DANameChange[]   $name_changes     (Via relations)
 * @property PCGSlotHistory[] $pcg_slot_history (Via relations)
 * @property string           $role_label       (Via magic method)
 * @property string           $avatar_provider  (Via magic method)
 * @method static User find(...$args)
 */
class User extends AbstractUser implements LinkableInterface {
	public static $table_name = 'users';

	public static $has_many = [
		['sessions', 'order' => 'last_visit desc'],
		['notifications', 'foreign_key' => 'user'],
		['submitted_events', 'class' => 'Event', 'foreign_key' => 'submitted_by'],
		['finalized_events', 'class' => 'Event', 'foreign_key' => 'finalized_by'],
		['pcg_appearances', 'class' => 'Appearance', 'foreign_key' => 'owner_id'],
		['logs', 'class' => 'Logs\Log', 'foreign_key' => 'initiator'],
		['name_changes', 'class' => 'Logs\DANameChange', 'order' => 'entryid asc'],
		['pcg_slot_history', 'class' => 'PCGSlotHistory', 'order' => 'created desc, id desc'],
	];
	public static $has_one = [
		['discord_member'],
	];

	public function get_role_label(){
		return Permission::ROLES_ASSOC[$this->role] ?? 'Curious Pony';
	}

	public const AVATAR_PROVIDERS = [
		'deviantart' => 'DeviantArt',
		'discord' => 'Discord',
	];

	public function get_avatar_provider(){
		return UserPrefs::get('p_avatarprov', $this);
	}

	public function set_avatar_provider(string $value){
		try {
			$newvalue = UserPrefs::process('p_avatarprov', $value);
		}
		catch (\Exception $e){ Response::fail('Preference value error: '.$e->getMessage()); }

		if ($newvalue === 'discord' && $this->discord_member === null)
			Response::fail("You must <a href='{$this->toURL()}#discord-connect'>link your account</a> to use the Discord avatar provider");

		return UserPrefs::set('p_avatarprov', $newvalue, $this);
	}

	public function get_avatar_url(){
		return $this->avatar_provider === 'deviantart' ? $this->read_attribute('avatar_url') : $this->discord_member->avatar_url;
	}

	public function toURL():string {
		return "/@$this->name";
	}

	public const WITH_AVATAR = true;

	/**
	 * Local profile link generator
	 *
	 * @param bool $with_avatar
	 * @param bool $enablePromises
	 *
	 * @return string
	 */
	public function toAnchor(bool $with_avatar = false, bool $enablePromises = false):string {
		$avatar = $with_avatar ? (
			$enablePromises
			? "<div class='user-avatar-promise avatar image-promise' data-src='{$this->avatar_url}'></div>"
			: "<img src='{$this->avatar_url}' class='avatar' alt='avatar'> "
		) : '';

		return "<a href='{$this->toURL()}' class='da-userlink local".($with_avatar ? " with-avatar provider-{$this->avatar_provider}":'')."'>$avatar<span class='name'>{$this->name}</span></a>";
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

		$avatar = $with_avatar ? "<img src='{$this->read_attribute('avatar_url')}' class='avatar' alt='avatar'> " : '';
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
		return "<div class='avatar-wrap provider-{$this->avatar_provider}$vectorapp' data-for='{$this->name}'><img src='{$this->avatar_url}' class='avatar' alt='avatar'></div>";
	}

	public function getVectorAppClassName():string {
		$pref = UserPrefs::get('p_vectorapp', $this);

		return !empty($pref) ? " app-$pref" : '';
	}

	public function getVectorAppReadableName():string {
		$pref = UserPrefs::get('p_vectorapp', $this);

		return CoreUtils::VECTOR_APPS[$pref] ?? 'unrecognized application';
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
	 * @param string $newrole
	 * @param bool   $skip_log
	 *
	 * @return bool
	 * @throws \RuntimeException
	 */
	public function updateRole(string $newrole, bool $skip_log = false):bool {
		$oldrole = (string)$this->role;
		$this->role = $newrole;
		$response = $this->save();

		if ($response && !$skip_log){
			Logs::logAction('rolechange', [
				'target' => $this->id,
				'oldrole' => $oldrole,
				'newrole' => $newrole
			]);
		}
		$oldrole_is_staff = Permission::sufficient('staff', $oldrole);
		$newrole_is_staff = Permission::sufficient('staff', $newrole);
		if ($oldrole_is_staff && !$newrole_is_staff){
			PCGSlotHistory::record($this->id, 'staff_leave');
			$this->syncPCGSlotCount();
		}
		else if (!$oldrole_is_staff && $newrole_is_staff){
			PCGSlotHistory::record($this->id, 'staff_join');
			$this->syncPCGSlotCount();
		}

		return $response;
	}

	/**
	 * Checks if a user is a club member
	 *
	 * @return bool
	 */
	public function isClubMember():bool {
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

	public function getPCGAppearanceCount():int {
		$return = Appearances::get(null, null, $this->id, Appearances::COUNT_COL);
		return ($return[0]['cnt'] ?? 0);
	}

	/**
	 * @param Pagination $Pagination
	 *
	 * @return Appearance[]|null
	 */
	public function getPCGAppearances(Pagination $Pagination = null):?array {
		$limit = isset($Pagination) ? $Pagination->getLimit() : null;
		DB::$instance->orderBy('order');
		return Appearances::get(null, $limit, $this->id, '*');
	}

	/**
	 * @return int
	 */
	public function getPCGSlotHistoryEntryCount():int {
		return DB::$instance->where('user_id', $this->id)->count(PCGSlotHistory::$table_name);
	}

	/**
	 * @param Pagination $Pagination
	 *
	 * @return PCGSlotHistory[]
	 */
	public function getPCGSlotHistoryEntries(Pagination $Pagination = null):?array {
		$limit = isset($Pagination) ? $Pagination->getLimit() : null;
		DB::$instance->orderBy('created','desc')->orderBy('id','desc');
		return DB::$instance->where('user_id', $this->id)->get(PCGSlotHistory::$table_name, $limit);
	}

	/**
	 * @param bool $throw
	 *
	 * @return int
	 */
	public function getPCGAvailablePoints(bool $throw = true):int {
		$slotcount = UserPrefs::get('pcg_slots', $this, true);
		if ($slotcount === null)
			$this->recalculatePCGSlotHistroy();

		$slotcount = (int)UserPrefs::get('pcg_slots', $this, true);
		if ($throw && $slotcount === 0)
			throw new NoPCGSlotsException();
		return $slotcount;
	}

	public function syncPCGSlotCount(){
		UserPrefs::set('pcg_slots', PCGSlotHistory::sum($this->id), $this);
	}

	public function recalculatePCGSlotHistroy(){
		# Wipe old entries
		DB::$instance->where('user_id', $this->id)->delete(PCGSlotHistory::$table_name);

		# Free slot for everyone
		PCGSlotHistory::record($this->id, 'free_trial', null, null, strtotime('2017-12-16T13:36:59Z'));

		# Grant points for approver requests
		DB::$instance->where('requested_by', $this->id, '!=');
		/** @var $posts Request[] */
		$posts = $this->getApprovedFinishedRequestContributions(false);
		if (!empty($posts))
			foreach ($posts as $post){
				PCGSlotHistory::record($this->id, 'post_approved', null, [
					'type' => $post->kind,
					'id' => $post->id,
				], $post->approval_entry->timestamp);
			}

		# Take slots for existing appearances
		foreach ($this->pcg_appearances as $appearance){
			PCGSlotHistory::record($this->id, 'appearance_add', null, [
				'id' => $appearance->id,
				'label' => $appearance->label,
			], $appearance->added);
		}

		# Take slots for sent gifts
		/** @var $sentGifts PCGSlotGift[] */
		$sentGifts = DB::$instance
			->setModel(PCGSlotGift::class)
			->query(
				'SELECT DISTINCT * FROM pcg_slot_gifts
				WHERE refunded_by IS NULL AND rejected = FALSE AND sender_id = ?', [$this->id]);
		foreach ($sentGifts as $gift){
			PCGSlotHistory::record($this->id, 'gift_sent', $gift->amount, [
				'gift_id' => $gift->id,
			], $gift->created_at);
		}

		# Give slots for received gifts
		/** @var $receivedGifts PCGSlotGift[] */
		$receivedGifts = DB::$instance
			->setModel(PCGSlotGift::class)
			->query(
				'SELECT DISTINCT * FROM pcg_slot_gifts
				WHERE claimed = TRUE AND receiver_id = ?', [$this->id]);
		foreach ($receivedGifts as $gift){
			PCGSlotHistory::record($this->id, 'gift_accepted', $gift->amount, [
				'gift_id' => $gift->id,
			], $gift->updated_at);
		}

		# Apply manual point grants
		$grantedPoints = PCGPointGrant::find('all', [ 'conditions' => ['receiver_id' => $this->id] ]);
		foreach ($grantedPoints as $grantedPoint)
			$grantedPoint->make_related_entries(false);

		$this->syncPCGSlotCount();
	}

	public const CONTRIB_CACHE_DURATION = Time::IN_SECONDS['hour'];

	public function getCachedContributions():array {
		$cache = CachedFile::init(FSPATH."contribs/{$this->id}.json.gz", self::CONTRIB_CACHE_DURATION);
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
			LEFT JOIN appearances p ON p.id = c.appearance_id
			WHERE c.contributor_id = ? AND p.owner_id IS NULL";

		if ($count)
			return DB::$instance->querySingle($query, [$this->id])['cnt'];

		if ($pagination)
			$query .= " GROUP BY $cols ORDER BY MIN(p.order) ASC ".$pagination->getLimitString();

		return Cutiemark::find_by_sql($query, [$this->id]);
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
				SELECT $cols, requested_by, requested_at, requested_at as posted_at FROM requests WHERE reserved_by = :userid AND deviation_id IS NOT NULL
				UNION ALL
				SELECT $cols, null as requested_by, null as requested_at, reserved_at as posted_at FROM reservations WHERE reserved_by = :userid AND deviation_id IS NOT NULL
			) t";
		if ($pagination)
			$query .= ' ORDER BY posted_at DESC '.$pagination->getLimitString();
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

	public function boundToDiscordMember():bool {
		return $this->discord_member instanceof DiscordMember;
	}

	public function isDiscordLinked():bool {
		return  $this->boundToDiscordMember() && $this->discord_member->isLinked();
	}

	public function isDiscordServerMember(bool $recheck = false):bool {
		return $this->isDiscordLinked() && $this->discord_member->isServerMember($recheck);
	}

	/**
	 * @param Event $event
	 * @param string $cols
	 *
	 * @return EventEntry[]
	 */
	public function getEntriesFor(Event $event, string $cols = '*'):?array {
		return DB::$instance->where('submitted_by', $this->id)->where('event_id', $event->id)->get(EventEntry::$table_name,null,$cols);
	}

	public const YOU_HAVE = [
		1 => 'You have',
		0 => 'This user has',
	];

	/**
	 * @return array
	 */
	private function _getPendingPostsArgs():array {
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
		return Reservation::find(...$this->_getPendingPostsArgs());
	}

	/**
	 * @return Request[] All requests from the databae that the user reserved but did not finish yet
	 */
	private function _getPendingRequestReservations(){
		return Request::find(...$this->_getPendingPostsArgs());
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
			$PendingReservations = $this->_getPendingReservations();
			$PendingRequestReservations = $this->_getPendingRequestReservations();
			$TotalPending = \count($PendingReservations)+ \count($PendingRequestReservations);
			$hasPending = $TotalPending > 0;
		}
		else {
			$TotalPending = 0;
			$hasPending = false;
		}
		$HTML = '';
		if ($staffVisitingMember || $sameUser){
			$gamble = $TotalPending < 4 && $sameUser ? ' <button id="suggestion" class="btn orange typcn typcn-lightbulb"><span>Suggestion</span></button>' : '';
			$HTML .= <<<HTML
<section class='pending-reservations'>
<h2>{$PrivateSection}Pending reservations$gamble</h2>
HTML;

			if ($isMember){
				$pendingCountReadable = ($hasPending>0?"<strong>$TotalPending</strong>":'no');
				$posts = CoreUtils::makePlural('reservation', $TotalPending);
				$HTML .= "<span>$YouHave $pendingCountReadable pending $posts";
				if ($hasPending)
					$HTML .= ' which ha'.($TotalPending!==1?'ve':'s')."n't been marked as finished yet";
				$HTML .= '.';
				if ($sameUser)
					$HTML .= " Please keep in mind that the global limit is 4 at any given time. If you reach the limit, you can't reserve any more images until you finish or cancel some of your pending reservations.";
				$HTML .= '</span>';

				if ($hasPending){
					/** @var $Posts Post[] */
					$Posts = array_merge(
						Posts::getReservationsSection($PendingReservations, RETURN_ARRANGED)['unfinished'],
						array_filter(array_values(Posts::getRequestsSection($PendingRequestReservations, RETURN_ARRANGED)['unfinished']))
					);
					usort($Posts, function(Post $a, Post $b){
						$a = strtotime($a->posted_at);
						$b = strtotime($b->posted_at);

						return $b <=> $a;
					});
					$LIST = '';
					foreach ($Posts as $Post){
						$postLink = $Post->toURL();
						$postAnchor = $Post->toAnchor();
						$label = !empty($Post->label) ? "<span class='label'>{$Post->label}</span>" : '';
						$actionCond = $Post->is_request && !empty($Post->reserved_at);
						$posted = Time::tag($actionCond ? $Post->reserved_at : $Post->posted_at);
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

	public const NOT_APPROVED_POST_COLS = 'id, season, episode, deviation_id';

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
		/** @var $AwaitingApproval \App\Models\Post[] */
		$AwaitingApproval = array_merge(
			$this->_getNotApprovedRequests(),
			$this->_getNotApprovedReservations()
		);
		$AwaitCount = \count($AwaitingApproval);
		$them = $AwaitCount!==1?'them':'it';
		$YouHave = self::YOU_HAVE[(int)$sameUser];
		$privacy = $sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['public']:'';
		$HTML = "<h2>{$privacy}Vectors waiting for approval</h2>";
		if ($sameUser)
			$HTML .= "<p>After you finish an image and submit it to the group gallery, an admin will check your vector and may ask you to fix some issues on your image, if any. After an image is accepted to the gallery, it can be marked as \"approved\", which gives it a green check mark, indicating that it's most likely free of any errors.</p>";
		$youHaveAwaitCount = "$YouHave ".(!$AwaitCount?'no':"<strong>$AwaitCount</strong>");
		$images = CoreUtils::makePlural('image', $AwaitCount);
		$append = !$AwaitCount
			? '.'
			: ', listed below.'.(
				$sameUser
				? " Please submit $them to the group gallery as soon as possible to have $them spot-checked for any issues. As stated in the rules, the goal is to add finished images to the group gallery, making $them easier to find for everyone.".(
					$AwaitCount>10
					? " You seem to have a large number of images that have not been approved yet, please submit them to the group soon if you haven't already."
					: ''
				)
				:''
			).'</p><p>You can click the <strong class="color-green"><span class="typcn typcn-tick"></span> Check</strong> button below the '.CoreUtils::makePlural('image',$AwaitCount).' in case we forgot to click it ourselves after accepting it.';
		$HTML .= <<<HTML
			<p>{$youHaveAwaitCount} $images waiting to be submitted to and/or approved by the group$append</p>
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
		<div class="post-deviation-promise image-promise" data-post="{$Post->getID()}"></div>
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

	public function getPCGBreadcrumb($active = false){
		return (new NavBreadcrumb('Users', '/users'))->setEnabled(Permission::sufficient('staff'))->setChild(
			(new NavBreadcrumb($this->name, $this->toURL()))->setChild(
				(new NavBreadcrumb('Personal Color Guide', ($this->canVisitorSeePCG() ? $this->toURL().'/cg' : null)))->setActive($active)
			)
		);
	}

	public function getPCGPointHistoryButtonHTML(?bool $showPrivate = null):string {
		if ($showPrivate === null)
			$showPrivate = (Auth::$signed_in && Auth::$user->id === $this->id) || Permission::sufficient('staff');
		return $showPrivate ? "<a href='/@{$this->name}/cg/point-history' class='btn link typcn typcn-eye'>Point history</a>" : '';
	}

	public function canVisitorSeePCG():bool {
		$isStaff = Permission::sufficient('staff');
		$isHiddenByUser = UserPrefs::get('p_hidepcg', $this);
		$isOwner = Auth::$signed_in && Auth::$user->id === $this->id;
		return $isStaff || $isOwner || !$isHiddenByUser;
	}

	public function maskedRole():string {
		return $this->role !== 'developer' ? $this->role : GlobalSettings::get('dev_role_label');
	}

	public function maskedRoleLabel():string {
		return Permission::ROLES_ASSOC[$this->maskedRole()];
	}

	public function getPCGSlotGiftButtonHTML():string {
		if (!Auth::$signed_in || Auth::$user->id === $this->id)
			return '';

		return "<button class='btn green typcn typcn-gift gift-pcg-slots'>Gift slots</a>";
	}

	public function getPCGPointGiveButtonHTML():string {
		if (Permission::insufficient('staff'))
			return '';

		return "<button class='btn darkblue typcn typcn-plus give-pcg-points'>Give points</a>";
	}

	/**
	 * @return PCGSlotGift[]
	 */
	public function getPendingPCGSlotGifts(){
		return DB::$instance->setModel(PCGSlotGift::class)->query(
			'SELECT * FROM pcg_slot_gifts
			WHERE refunded_by IS NULL AND rejected = FALSE AND claimed = FALSE AND receiver_id = ?
			ORDER BY created_at ASC', [$this->id]);
	}
}
