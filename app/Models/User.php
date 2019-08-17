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
use App\Response;
use App\Time;
use App\Twig;
use App\UserPrefs;

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
class User extends AbstractUser implements Linkable {
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

  public function get_role_label() {
    return Permission::ROLES_ASSOC[$this->role] ?? 'Curious Pony';
  }

  public const AVATAR_PROVIDERS = [
    'deviantart' => 'DeviantArt',
    'discord' => 'Discord',
  ];

  public function get_avatar_provider() {
    return UserPrefs::get('p_avatarprov', $this);
  }

  public function set_avatar_provider(string $value) {
    try {
      $newvalue = UserPrefs::process('p_avatarprov', $value);
    }
    catch (\Exception $e){
      Response::fail('Preference value error: '.$e->getMessage());
    }

    if ($newvalue === 'discord' && $this->discord_member === null)
      Response::fail("You must <a href='{$this->toURL()}#discord-connect'>link your account</a> to use the Discord avatar provider");

    return UserPrefs::set('p_avatarprov', $newvalue, $this);
  }

  public function get_avatar_url() {
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
   * @param bool $enable_promises
   * @param bool $with_vector_app_icon
   *
   * @return string
   */
  public function toAnchor(bool $with_avatar = false, bool $enable_promises = false, bool $with_vector_app_icon = false):string {
    return Twig::$env->render('user/_anchor.html.twig', [
      'user' => $this,
      'with_avatar' => $with_avatar,
      'enable_promises' => $enable_promises,
      'with_vector_app_icon' => $with_vector_app_icon,
    ]);
  }

  public function toDALink() {
    return 'https://www.deviantart.com/'.strtolower($this->name);
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
    $for = $this->id !== null ? "data-for='{$this->id}'" : '';

    return "<div class='avatar-wrap provider-{$this->avatar_provider}$vectorapp' $for><img src='{$this->avatar_url}' class='avatar' alt='avatar'></div>";
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
        'newrole' => $newrole,
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
    DB::$instance->orderBy('created', 'desc')->orderBy('id', 'desc');

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

  public function syncPCGSlotCount() {
    UserPrefs::set('pcg_slots', PCGSlotHistory::sum($this->id), $this);
  }

  public function recalculatePCGSlotHistroy() {
    # Wipe old entries
    DB::$instance->where('user_id', $this->id)->delete(PCGSlotHistory::$table_name);

    # Free slot for everyone
    PCGSlotHistory::record($this->id, 'free_trial', null, null, strtotime('2017-12-16T13:36:59Z'));

    # Grant points for approved requests
    DB::$instance->where('requested_by', $this->id, '!=');
    /** @var $posts Post[] */
    $posts = $this->getApprovedFinishedRequestContributions(false);
    if (!empty($posts))
      foreach ($posts as $post){
        PCGSlotHistory::record($this->id, 'post_approved', null, [
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
    $grantedPoints = PCGPointGrant::find('all', ['conditions' => ['receiver_id' => $this->id]]);
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
   *
   * @return int|array
   */
  public function getCMContributions(bool $count = true, Pagination $pagination = null) {
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
   * @param bool       $requests   Boolean indicating whether we want to get requests (true) or reservations (false)
   * @param bool       $count      Returns the count if true
   * @param Pagination $pagination Pagination object used for getting the LIMIT part of the query
   *
   * @return int|Post[]
   */
  private function _getPostContributions(bool $requests, bool $count = true, Pagination $pagination = null) {
    if ($requests)
      DB::$instance->where('requested_by', $this->id);
    else DB::$instance->where('reserved_by', $this->id)->where('requested_by IS NULL');

    if ($count)
      return DB::$instance->count('posts');

    $limit = isset($pagination) ? $pagination->getLimit() : null;

    return DB::$instance->orderBy(($requests ? 'requested_at' : 'reserved_at'), 'DESC')->get('posts', $limit);
  }

  /**
   * @param bool       $count      Returns the count if true
   * @param Pagination $pagination Pagination object used for getting the LIMIT part of the query
   *
   * @return int|Post[]
   */
  public function getRequestContributions(bool $count = true, Pagination $pagination = null) {
    return $this->_getPostContributions(true, $count, $pagination);
  }

  /**
   * @param bool       $count      Returns the count if true
   * @param Pagination $pagination Pagination object used for getting the LIMIT part of the query
   *
   * @return int|Post[]
   */
  public function getReservationContributions(bool $count = true, Pagination $pagination = null) {
    return $this->_getPostContributions(false, $count, $pagination);
  }

  /**
   * @param bool            $count      Returns the count if true
   * @param Pagination|null $pagination Pagination object used for getting the LIMIT part of the query
   *
   * @return int|Post[]
   */
  public function getFinishedPostContributions(bool $count = true, ?Pagination $pagination = null) {
    DB::$instance->where('reserved_by', $this->id)
      ->where('deviation_id IS NOT NULL');
    if ($count)
      return DB::$instance->count('posts');

    if ($pagination)
      DB::$instance->orderByLiteral(Post::ORDER_BY_POSTED_AT, 'DESC');

    $limit = isset($pagination) ? $pagination->getLimit() : null;

    return DB::$instance->get('posts', $limit);
  }

  /**
   * @param bool       $count      Returns the count if true
   * @param Pagination $pagination Pagination object used for getting the LIMIT part of the query
   *
   * @return int|Post[]
   */
  public function getApprovedFinishedRequestContributions(bool $count = true, Pagination $pagination = null) {

    DB::$instance
      ->where('requested_by IS NOT NULL') // Requests only
      ->where('deviation_id IS NOT NULL')
      ->where('reserved_by', $this->id)
      ->where('lock', 1);

    if ($count)
      return DB::$instance->count('posts');

    $limit = isset($pagination) ? $pagination->getLimit() : null;

    return DB::$instance->orderBy('finished_at', 'DESC')->get('posts', $limit);
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
    return $this->boundToDiscordMember() && $this->discord_member->isLinked();
  }

  public function isDiscordServerMember(bool $recheck = false):bool {
    return $this->isDiscordLinked() && $this->discord_member->isServerMember($recheck);
  }

  /**
   * @param Event  $event
   * @param string $cols
   *
   * @return EventEntry[]
   */
  public function getEntriesFor(Event $event, string $cols = '*'):?array {
    return DB::$instance->where('submitted_by', $this->id)->where('event_id', $event->id)->get(EventEntry::$table_name, null, $cols);
  }

  public const YOU_HAVE = [
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
          'requested_by IS '.($requests ? ' NOT' : '').' NULL AND reserved_by = ? AND deviation_id IS NULL',
          $this->id,
        ],
      ],
    ];
  }

  /**
   * @return Post[] All reservations from the database that the user posted but did not finish yet
   */
  private function _getPendingReservations() {
    return Post::find(...$this->_getPendingPostsArgs(false));
  }

  /**
   * @return Post[] All requests from the database that the user reserved but did not finish yet
   */
  private function _getPendingRequestReservations() {
    return Post::find(...$this->_getPendingPostsArgs(true));
  }

  /**
   * @param bool $same_user
   * @param bool $user_is_member
   *
   * @return string
   */
  public function getPendingReservationsHTML(bool $same_user, bool $user_is_member = true):string {
    $visitor_is_staff = Permission::sufficient('staff');
    $staff_visiting_member = $visitor_is_staff && $user_is_member;
    $data = [
      'same_user' => $same_user,
      'user_is_member' => $user_is_member,
      'staff_visiting_member' => $staff_visiting_member,
    ];

    if ($staff_visiting_member || ($user_is_member && $same_user)){
      $pending_res = $this->_getPendingReservations();
      $pending_req_res = $this->_getPendingRequestReservations();
      $data['total_pending'] = \count($pending_res) + \count($pending_req_res);
      $data['has_pending'] = $data['total_pending'] > 0;
    }
    else {
      $data['total_pending'] = 0;
      $data['has_pending'] = false;
    }
    if (($staff_visiting_member || $same_user) && $user_is_member && $data['has_pending']){
      $data['posts'] = array_merge(
        $pending_res,
        array_filter(array_values($pending_req_res))
      );
      usort($data['posts'], function (Post $a, Post $b) {
        return $b->posted_at->getTimestamp() <=> $a->posted_at->getTimestamp();
      });
    }

    return Twig::$env->render('user/_profile_pending_reservations.html.twig', $data);
  }

  /**
   * @return Post[]
   */
  public function getPostsAwaitingApproval():array {
    /** @var $awaiting_approval Post[] */
    $awaiting_approval = DB::$instance
      ->where('reserved_by', $this->id)
      ->where('deviation_id IS NOT NULL')
      ->where('lock', false)
      ->orderByLiteral(Post::ORDER_BY_POSTED_AT)
      ->get('posts');

    return $awaiting_approval;
  }

  public function getPCGBreadcrumb($active = false) {
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

    return "<button class='btn green typcn typcn-gift' id='gift-pcg-slots'>Gift slots</a>";
  }

  public function getPCGPointGiveButtonHTML():string {
    if (Permission::insufficient('staff'))
      return '';

    return "<button class='btn darkblue typcn typcn-plus' id='give-pcg-points'>Give points</a>";
  }

  /**
   * @return PCGSlotGift[]
   */
  public function getPendingPCGSlotGifts() {
    return DB::$instance->setModel(PCGSlotGift::class)->query(
      'SELECT * FROM pcg_slot_gifts
			WHERE refunded_by IS NULL AND rejected = FALSE AND claimed = FALSE AND receiver_id = ?
			ORDER BY created_at', [$this->id]);
  }

  /**
   * Returns whether the user has permissions for the specified role
   *
   * @param string $role
   *
   * @return bool
   */
  public function perm(string $role):bool {
    return Permission::sufficient($role, $this->role);
  }

  public function getOpenSubmissionsURL() {
    return "https://www.deviantart.com/mlp-vectorclub/messages/?log_type=1&instigator_module_type=21&instigator_username={$this->name}&bpp_status=3&display_order=desc";
  }
}
