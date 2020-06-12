<?php

namespace App\Controllers;

use App\Auth;
use App\CoreUtils;
use App\DB;
use App\DeviantArt;
use App\File;
use App\HTTP;
use App\Input;
use App\Models\PreviousUsername;
use App\Models\Session;
use App\Models\DeviantartUser;
use App\Pagination;
use App\Permission;
use App\Response;
use App\Twig;
use App\UserPrefs;
use App\Users;
use RuntimeException;
use function count;

class UserController extends Controller {
  use UserLoaderTrait;

  public function homepage():void {
    if (UserPrefs::get('p_homelastep'))
      HTTP::tempRedirect('/episode/latest');

    HTTP::tempRedirect('/cg');
  }

  public function profile($params):void {
    $un = $params['name'] ?? null;

    $error = null;
    $sub_error = null;
    if ($un === null){
      if (Auth::$signed_in)
        $user = Auth::$user;
      else $error = 'Sign in to view your settings';
    }
    else $user = Users::get($un, 'name');

    if (empty($user) || !($user instanceof DeviantartUser)){
      if (Auth::$signed_in && isset($user) && $user === false){
        if (CoreUtils::contains(Auth::$session->scope, 'browse')){
          $error = 'User does not exist';
          $sub_error = 'Check the name for typos and try again';
        }
        else {
          $error = 'Could not fetch user information';
          $sub_error = 'Your session is missing the "browse" scope';
        }
      }
      else if ($error === null){
        $error = 'Local user data missing';
        if (!Auth::$signed_in){
          $exists = 'exists on DeviantArt';
          if ($un !== null)
            $exists = "<a href='https://www.deviantart.com/".CoreUtils::aposEncode(strtolower($un))."'>$exists</a>";
          $sub_error = "If this user $exists, sign in to import their details.";
        }
      }
      $can_edit = $same_user = $dev_on_dev = false;
    }
    else {
      $pagePath = "/@{$user->name}";
      CoreUtils::fixPath($pagePath);
      $same_user = Auth::$signed_in && $user->id === Auth::$user->id;
      $can_edit = !$same_user && Permission::sufficient('staff') && Permission::sufficient($user->role);
      $dev_on_dev = Permission::sufficient('developer') && Permission::sufficient('developer', $user->role);
    }

    if ($error !== null)
      HTTP::statusCode(404);
    else {
      $sessions = $user->sessions;

      $is_staff = Permission::sufficient('staff');

      if ($same_user || $is_staff){
        if (count($user->previous_names) > 0){
          $old_names = implode(', ', array_map(fn(PreviousUsername $p) => $p->username, $user->previous_names));
        }
      }

      if ($user->boundToDiscordMember()){
        $discord_membership = $user->discord_member;
      }

      $contribs = $user->getCachedContributions();
      $contrib_cache_duration = Users::getContributionsCacheDuration();

      if ($can_edit){
        $export_roles = [];
        $roles_copy = Permission::ROLES_ASSOC;
        unset($roles_copy['guest']);
        foreach ($roles_copy as $name => $label){
          if (Permission::insufficient($name, Auth::$user->role))
            continue;
          $export_roles[$name] = $label;
        }
      }
      else if ($dev_on_dev)
        $export_roles = Permission::ROLES_ASSOC;

      $pcg_section_is_private = UserPrefs::get('p_hidepcg', $user);
      $list_pcgs = !$pcg_section_is_private || $same_user || $is_staff;
      if ($list_pcgs)
        $personal_color_guides = $user->pcg_appearances;

      $awaiting_approval = $user->getPostsAwaitingApproval();
    }

    $settings = [
      'title' => $error === null ? ($same_user ? 'Your' : "{$user->name} -").' '.($same_user || $can_edit ? 'Account' : 'Profile')
        : 'Account',
      'noindex' => true,
      'css' => [true],
      'js' => [true],
      'og' => [
        'image' => !empty($user) ? $user->avatar_url : null,
        'description' => !empty($user) ? CoreUtils::posess($user->name)." profile on the MLP-VectorClub's website" : null,
      ],
      'import' => [
        'user' => $user ?? null,
        'discord_membership' => $discord_membership ?? null,
        'can_edit' => $can_edit,
        'same_user' => $same_user,
        'is_staff' => $is_staff ?? null,
        'dev_on_dev' => $dev_on_dev,
        'sessions' => $sessions ?? null,
        'da_logo' => str_replace(' fill="#FFF"', '', File::get(APPATH.'img/da-logo.svg')),
        'old_names' => $old_names ?? null,
        'contribs' => $contribs ?? null,
        'contrib_cache_duration' => $contrib_cache_duration ?? null,
        'export_roles' => $export_roles ?? null,
        'section_is_private' => $pcg_section_is_private ?? null,
        'list_pcgs' => $list_pcgs ?? null,
        'personal_color_guides' => $personal_color_guides ?? null,
        'awaiting_approval' => $awaiting_approval ?? null,
      ],
    ];
    if ($error !== null)
      $settings['import']['error'] = $error;
    if ($sub_error !== null)
      $settings['import']['sub_error'] = $sub_error;
    if ($can_edit || $dev_on_dev)
      $settings['js'][] = 'pages/user/manage';
    $show_suggestions = $same_user;
    if ($show_suggestions){
      $settings['js'][] = 'pages/user/suggestion';
      $settings['css'][] = 'pages/user/suggestion';
    }
    $settings['import']['showSuggestions'] = $show_suggestions;
    CoreUtils::loadPage(__METHOD__, $settings);
  }

  public function profileByUuid($params):void {
    if (!isset($params['uuid']) || Permission::insufficient('developer'))
      CoreUtils::notFound();

    $user = DeviantartUser::find($params['uuid']);
    if (empty($user))
      CoreUtils::notFound();

    HTTP::permRedirect('/@'.$user->name);
  }

  public function sessionApi($params):void {
    if ($this->action !== 'DELETE')
      CoreUtils::notAllowed();

    if (!isset($params['id']))
      Response::fail('Missing session ID');

    $session = Session::find($params['id']);
    if (empty($session))
      Response::fail('This session does not exist');
    if ($session->user_id !== Auth::$user->id && Permission::insufficient('staff'))
      Response::fail('You are not allowed to delete this session');

    $session->delete();

    Response::success('Session successfully removed');
  }

  public function roleApi($params):void {
    if ($this->action !== 'PUT')
      CoreUtils::notAllowed();

    if (Permission::insufficient('staff'))
      Response::fail();

    if (!isset($params['id']))
      Response::fail('Missing user ID');

    $target_user = DeviantartUser::find($params['id']);
    if (empty($target_user))
      Response::fail('User not found');

    if ($target_user->id === Auth::$user->id)
      Response::fail('You cannot modify your own group');
    if (Permission::insufficient($target_user->role))
      Response::fail('You can only modify the group of users who are in the same or a lower-level group than you');

    $new_role = (new Input('value', 'role', [
      Input::CUSTOM_ERROR_MESSAGES => [
        Input::ERROR_MISSING => 'The new group is not specified',
        Input::ERROR_INVALID => 'The specified group (@value) does not exist',
      ],
    ]))->out();
    if ($target_user->role === $new_role)
      Response::done(['already_in' => true]);

    $target_user->updateRole($new_role);

    Response::done();
  }

  public const CONTRIB_NAMES = [
    'cms-provided' => 'Cutie Mark vectors provided',
    'requests' => 'Requests posted',
    'reservations' => 'Reservations posted',
    'finished-posts' => 'Posts finished',
    'fulfilled-requests' => 'Requests fulfilled',
  ];

  public function contrib($params):void {
    if (!isset(self::CONTRIB_NAMES[$params['type']]))
      CoreUtils::notFound();

    $user = Users::get($params['name'], 'name');
    if (empty($user))
      CoreUtils::notFound();
    if ($user->id !== (Auth::$user->id ?? null) && $params['type'] === 'requests' && Permission::insufficient('staff'))
      CoreUtils::notFound();

    $items_per_page = 10;
    $pagination = new Pagination("/@{$user->name}/contrib/{$params['type']}", $items_per_page);

    /** @var $cnt int */
    /** @var $data array */
    switch ($params['type']){
      case 'cms-provided':
        $cnt = $user->getCMContributions();
        $pagination->calcMaxPages($cnt);
        $data = $user->getCMContributions(false, $pagination);
      break;
      case 'requests':
        $cnt = $user->getRequestContributions();
        $pagination->calcMaxPages($cnt);
        $data = $user->getRequestContributions(false, $pagination);
      break;
      case 'reservations':
        $cnt = $user->getReservationContributions();
        $pagination->calcMaxPages($cnt);
        $data = $user->getReservationContributions(false, $pagination);
      break;
      case 'finished-posts':
        $cnt = $user->getFinishedPostContributions();
        $pagination->calcMaxPages($cnt);
        $data = $user->getFinishedPostContributions(false, $pagination);
      break;
      case 'fulfilled-requests':
        $cnt = $user->getApprovedFinishedRequestContributions();
        $pagination->calcMaxPages($cnt);
        $data = $user->getApprovedFinishedRequestContributions(false, $pagination);
      break;
      default:
        throw new RuntimeException(__METHOD__.": Missing data retriever for type {$params['type']}");
    }

    CoreUtils::fixPath($pagination->toURI());

    $title = "Page {$pagination->getPage()} - ".self::CONTRIB_NAMES[$params['type']].' - '.CoreUtils::posess($user->name).' Contributions';
    $heading = self::CONTRIB_NAMES[$params['type']].' by '.$user->toAnchor();
    CoreUtils::loadPage(__METHOD__, [
      'title' => $title,
      'heading' => $heading,
      'css' => [true],
      'js' => ['paginate', true],
      'import' => [
        'pagination' => $pagination,
        'user' => $user,
        'contrib_name' => self::CONTRIB_NAMES[$params['type']],
        'contribution_list' => Users::getContributionListHTML($params['type'], $data),
      ],
    ]);
  }

  public function contribLazyload($params):void {
    $CachedDeviation = DeviantArt::getCachedDeviation($params['favme']);
    if (empty($CachedDeviation))
      HTTP::statusCode(404, AND_DIE);

    Response::done(['html' => $CachedDeviation->toLinkWithPreview()]);
  }

  public function contribCacheApi($params):void {
    if ($this->action !== 'DELETE')
      CoreUtils::notAllowed();

    if (Permission::insufficient('staff'))
      Response::fail('You are not allowed to clear contribution caches');

    if (!isset($params['id']))
      Response::fail('Missing user ID');

    $user = DeviantartUser::find($params['id']);
    if (empty($user))
      Response::fail('The specified user does not exist');

    unlink($user->getCachedContributionsPath());

    $same_user = Auth::$signed_in && $user->id === Auth::$user->id;
    $contribs = $user->getCachedContributions();
    $contrib_cache_duration = Users::getContributionsCacheDuration();

    Response::success('Contributions cache successfully cleared', [
      'html' => Twig::$env->render('user/_profile_contributions.html.twig', [
        'user' => $user,
        'same_user' => $same_user,
        'contribs' => $contribs ?? null,
        'contrib_cache_duration' => $contrib_cache_duration ?? null,
        'wrap' => false,
      ]),
    ]);
  }

  public function list():void {
    if (Permission::insufficient('staff'))
      CoreUtils::noPerm();

    $users = DB::$instance->orderBy('name')->get(DeviantartUser::$table_name);
    if (!empty($users)){
      $arranged = [];
      foreach ($users as $u){
        if (!isset($arranged[$u->role])) $arranged[$u->role] = [];

        $arranged[$u->maskedRole()][] = $u;
      }

      $sections = [];
      foreach (array_reverse(Permission::ROLES) as $r => $v){
        if (empty($arranged[$r])) continue;
        /** @var $users \App\Models\DeviantartUser[] */
        $users = $arranged[$r];
        $user_count = count($users);
        $group = CoreUtils::makePlural(Permission::ROLES_ASSOC[$r], $user_count, true);

        if ($user_count > 10){
          $users_out = [];
          foreach ($users as $u){
            $firstletter = strtoupper($u->name[0]);
            if (preg_match('/^[^a-z]$/i', $firstletter))
              $firstletter = '#';
            $users_out[$firstletter][] = $u->toAnchor();
          }

          ksort($users_out);

          $users_str = '';
          foreach ($users_out as $chr => $users){
            $users_str .= "<span class='letter-group'><strong>$chr</strong>".implode('', $users).'</span>';
          }
        }
        else {
          $users_out = [];
          foreach ($users as $u)
            $users_out[] = $u->toAnchor();
          $users_str = implode(', ', $users_out);
        }

        $sections[] = [
          $group,
          $users_str,
        ];
      }
    }

    CoreUtils::loadPage(__METHOD__, [
      'title' => 'Users',
      'css' => [true],
      'import' => [
        'sections' => $sections ?? null,
      ],
    ]);
  }

  public function avatarWrap($params):void {
    if ($this->action !== 'GET')
      CoreUtils::notAllowed();

    $this->load_user($params);

    Response::done(['html' => $this->user->getAvatarWrap()]);
  }
}
