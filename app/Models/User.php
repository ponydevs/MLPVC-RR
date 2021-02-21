<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\Appearances;
use App\Auth;
use App\CachedFile;
use App\CoreUtils;
use App\DB;
use App\Exceptions\NoPCGSlotsException;
use App\GlobalSettings;
use App\Logs;
use App\NavBreadcrumb;
use App\Pagination;
use App\Permission;
use App\Time;
use App\Twig;
use App\UserPrefs;
use RuntimeException;
use function count;

/**
 * @property integer          $id
 * @property string           $name
 * @property string           $email
 * @property string           $role
 * @property DateTime         $email_verified_at
 * @property string           $password
 * @property string           $remember_token
 * @property DateTime         $created_at
 * @property DateTime         $updated_at
 * @property Session[]        $sessions         (Via relations)
 * @property Notification[]   $notifications    (Via relations)
 * @property Event[]          $submitted_events (Via relations)
 * @property Event[]          $finalized_events (Via relations)
 * @property Appearance[]     $pcg_appearances  (Via relations)
 * @property DeviantartUser   $deviantart_user  (Via relations)
 * @property DiscordMember    $discord_member   (Via relations)
 * @property Log[]            $logs             (Via relations)
 * @property PCGSlotHistory[] $pcg_slot_history (Via relations)
 * @property string           $role_label       (Via magic method)
 * @property string           $avatar_url       (Via magic method)
 * @property null             $previous_names
 * @method static User find(...$args)
 * @method static User find_by_email(string $email)
 */
class User extends NSModel implements Linkable {
  public static $table_name = 'users';

  public static $has_many = [
    ['sessions', 'foreign_key' => 'user_id', 'order' => 'last_visit desc'],
    ['notifications', 'foreign_key' => 'recipient_id'],
    ['submitted_events', 'class' => 'Event', 'foreign_key' => 'submitted_by'],
    ['finalized_events', 'class' => 'Event', 'foreign_key' => 'finalized_by'],
    ['pcg_appearances', 'class' => 'Appearance', 'foreign_key' => 'owner_id'],
    ['logs', 'class' => 'Log', 'foreign_key' => 'initiator'],
    ['pcg_slot_history', 'class' => 'PCGSlotHistory', 'foreign_key' => 'user_id', 'order' => 'created desc, id desc'],
  ];
  public static $has_one = [
    ['discord_member'],
    ['deviantart_user'],
  ];

  public function get_role_label() {
    return Permission::ROLES_ASSOC[$this->role] ?? 'Curious Pony';
  }

  /** For Twig */
  public function getAvatar_url() {
    return $this->avatar_url;
  }

  /** For Twig */
  public function getDeviantart_user() {
    return $this->deviantart_user;
  }

  public function get_avatar_url():?string {
    $da_user = $this->deviantart_user;

    return $da_user->avatar_url ?? GUEST_AVATAR;
  }

  public function toURL(bool $id_only = true):string {
    $url = "/users/$this->id";

    if ($id_only)
      return $url;

    return "$url-".CoreUtils::makeUrlSafe($this->name);
  }

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

    return "<div class='avatar-wrap$vectorapp' $for><img src='{$this->avatar_url}' class='avatar' alt='avatar'></div>";
  }

  public function getVectorAppClassName():string {
    $pref = UserPrefs::get('p_vectorapp', $this);

    return !empty($pref) ? " app-$pref" : '';
  }

  public function getVectorAppReadableName():string {
    $pref = UserPrefs::get('p_vectorapp', $this);

    return CoreUtils::VECTOR_APPS[$pref] ?? 'unrecognized application';
  }

  /**
   * USED IN TWIG - DO NOT REMOVE
   *
   * @noinspection PhpUnused
   */
  public function getVectorAppIcon():string {
    $vectorapp = UserPrefs::get('p_vectorapp', $this);
    if (empty($vectorapp))
      return '';

    return "<img class='vectorapp-logo' src='/img/vapps/$vectorapp.svg' alt='$vectorapp logo' title='".$this->getVectorAppReadableName()." user'>";
  }

  /**
   * Update a user's role
   *
   * @param string $new_role
   * @param bool   $skip_log
   *
   * @return bool
   * @throws RuntimeException
   */
  public function updateRole(string $new_role, bool $skip_log = false):bool {
    $old_role = (string)$this->role;
    if ($old_role === 'developer'){
      $old_role = GlobalSettings::get('dev_role_label');
      if ($old_role === $new_role){
        return true;
      }
      $response = GlobalSettings::set('dev_role_label', $new_role);
    }
    else {
      $this->role = $new_role;
      $response = $this->save();
    }

    if ($response && !$skip_log){
      Logs::logAction('rolechange', [
        'target' => $this->id,
        'oldrole' => $old_role,
        'newrole' => $new_role,
      ]);
    }
    $old_role_is_staff = Permission::sufficient('staff', $old_role);
    $new_role_is_staff = Permission::sufficient('staff', $new_role);
    if ($old_role_is_staff && !$new_role_is_staff){
      PCGSlotHistory::record($this->id, 'staff_leave');
      $this->syncPCGSlotCount();
    }
    else if (!$old_role_is_staff && $new_role_is_staff){
      PCGSlotHistory::record($this->id, 'staff_join');
      $this->syncPCGSlotCount();
    }

    return $response;
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
    DB::$instance->orderBy('created_at', 'desc')->orderBy('id', 'desc');

    return DB::$instance->where('user_id', $this->id)->get(PCGSlotHistory::$table_name, $limit);
  }

  /**
   * @param bool $throw
   *
   * @return int
   * @throws NoPCGSlotsException
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
    $feature_introduced_ts = strtotime('2017-12-16T13:36:59Z');
    $account_created_ts = $this->created_at->getTimestamp();
    PCGSlotHistory::record($this->id, 'free_trial', null, null, max($feature_introduced_ts, $account_created_ts));

    # Grant points for approved requests
    DB::$instance->where('requested_by', $this->id, '!=');
    /** @var $posts Post[] */
    $posts = $this->getApprovedFinishedRequestContributions(false);
    if (!empty($posts))
      foreach ($posts as $post){
        $entry = $post->approval_entry;
        PCGSlotHistory::record($this->id, 'post_approved', null, [
          'id' => $post->id,
        ], $entry ? $entry->created_at : null);
      }

    # Take slots for existing appearances
    foreach ($this->pcg_appearances as $appearance){
      PCGSlotHistory::record($this->id, 'appearance_add', null, [
        'id' => $appearance->id,
        'label' => $appearance->label,
      ], $appearance->created_at);
    }

    # Apply manual point grants
    $grantedPoints = PCGPointGrant::find('all', ['conditions' => ['receiver_id' => $this->id]]);
    foreach ($grantedPoints as $grantedPoint)
      $grantedPoint->make_related_entries(false);

    $this->syncPCGSlotCount();
  }

  public const CONTRIB_CACHE_DURATION = Time::IN_SECONDS['hour'];

  public function getCachedContributionsPath():string {
    return FSPATH."contribs/{$this->id}.json.gz";
  }

  public function getCachedContributions():array {
    $cache = CachedFile::init($this->getCachedContributionsPath(), self::CONTRIB_CACHE_DURATION);
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

    $da_bound = $this->boundToDeviantartUser();
    if ($count){
      if (!$da_bound)
        return 0;

      return DB::$instance->querySingle($query, [$this->deviantart_user->id])['cnt'];
    }

    if (!$da_bound)
      return [];

    if ($pagination)
      $query .= " GROUP BY $cols ORDER BY MIN(p.order) ASC ".$pagination->getLimitString();

    return Cutiemark::find_by_sql($query, [$this->deviantart_user->id]);
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
    $approvedPosts = DB::$instance->where('user_id', $this->id)->count('locked_posts');
    if ($approvedPosts > 0)
      $contribs[] = [$approvedPosts, 'post', 'marked approved'];

    return $contribs;
  }

  public function boundToDiscordMember():bool {
    return $this->discord_member instanceof DiscordMember;
  }

  public function safelyGetDiscordMember():?DiscordMember {
    return $this->boundToDiscordMember() ? $this->discord_member : null;
  }

  public function isDiscordLinked():bool {
    return $this->boundToDiscordMember() && $this->discord_member->isLinked();
  }

  public function isDiscordServerMember(bool $recheck = false):bool {
    return $this->isDiscordLinked() && $this->discord_member->isServerMember($recheck);
  }

  public function boundToDeviantartUser():bool {
    return $this->deviantart_user instanceof DeviantartUser;
  }

  public function isDeviantartLinked():bool {
    return $this->boundToDeviantartUser() && $this->deviantart_user->isLinked();
  }

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
      $data['total_pending'] = count($pending_res) + count($pending_req_res);
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

  public function getPCGBreadcrumb($active = false):NavBreadcrumb {
    return (new NavBreadcrumb('Users', '/users'))->setEnabled(Permission::sufficient('staff'))->setChild(
      (new NavBreadcrumb($this->name, $this->toURL(false)))->setChild(
        (new NavBreadcrumb('Personal Color Guide', ($this->canVisitorSeePCG() ? $this->toURL().'/cg' : null)))->setActive($active)
      )
    );
  }

  /**
   * USED IN TWIG - DO NOT REMOVE
   *
   * @param bool|null $show_private
   *
   * @return string
   * @noinspection PhpUnused
   */
  public function getPCGPointHistoryButtonHTML(?bool $show_private = null):string {
    if ($show_private === null)
      $show_private = (Auth::$signed_in && Auth::$user->id === $this->id) || Permission::sufficient('staff');

    return $show_private ? "<a href='{$this->toURL()}/cg/point-history' class='btn link typcn typcn-document-text'>Point history</a>" : '';
  }

  public function canVisitorSeePCG():bool {
    $is_owner = Auth::$signed_in && Auth::$user->id === $this->id;
    if ($is_owner) return true;

    $is_staff = Permission::sufficient('staff');
    if ($is_staff) return true;

    $hidden_by_owner = UserPrefs::get('p_hidepcg', $this);
    if (!$hidden_by_owner) return true;

    return false;
  }

  public function maskedRole():string {
    return $this->role !== 'developer' ? $this->role : GlobalSettings::get('dev_role_label');
  }

  /**
   * Used in profile page template
   */
  public function maskedRoleLabel():string {
    return Permission::ROLES_ASSOC[$this->maskedRole()];
  }

  public function getPCGPointGiveButtonHTML():string {
    if (Permission::insufficient('staff'))
      return '';

    return "<button class='btn darkblue typcn typcn-plus' id='give-pcg-points'>Give points</a>";
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

  public function assignCorrectRole():bool {
    $da_user = $this->deviantart_user;
    $club_member = $da_user->isClubMember();
    if ($club_member){
      $club_role = $da_user->getClubRole();
      if ($club_role !== $this->role)
        return $this->updateRole($club_role);
    }
    else if (Permission::sufficient('member', $this->role))
      return $this->updateRole('user');

    return $this->save();
  }

  public function getPasswordSet():bool {
    return $this->password !== null;
  }

  public function getEmailSet():bool {
    return $this->email !== null;
  }

  public function getAccountPagePath():string {
    return "/users/{$this->id}/account";
  }

  public function setVerifiedEmail(EmailVerification $verification):bool {
    $this->email = $verification->email;
    $this->email_verified_at = date('c');

    $status = $this->save();
    if ($status) {
      $verification->delete();
    }

    return $status;
  }
}
