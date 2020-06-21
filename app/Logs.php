<?php

namespace App;

use ActiveRecord\RecordNotFound;
use App\Models\Appearance;
use App\Models\ColorGroup;
use App\Models\DeviantartUser;
use App\Models\LegacyPostMapping;
use App\Models\Log;
use App\Models\Post;
use App\Models\Show;
use App\Models\ShowVideo;
use cogpowered\FineDiff;
use Exception;
use RuntimeException;

class Logs {
  public const LOG_DESCRIPTION = [
    #--------------------# (max length)
    'rolechange' => 'User group change',
    'userfetch' => 'Fetch user details',
    'req_delete' => 'Request deleted',
    'img_update' => 'Post image updated',
    'res_overtake' => 'Overtook post reservation',
    'appearances' => 'Appearance management',
    'res_transfer' => 'Reservation transferred',
    'cg_modify' => 'Color group modified',
    'cgs' => 'Color group management',
    'cg_order' => 'Color groups re-ordered',
    'appearance_modify' => 'Appearance modified',
    'video_broken' => 'Broken video removed',
    'cm_modify' => 'Appearance CM edited',
    'cm_delete' => 'Appearance CM deleted',
    'post_fix' => 'Broken post restored',
    'staff_limits' => 'Account limitation changed',
    'derpimerge' => 'Derpibooru merge detected',
  ];

  public const FORCE_INITIATOR_WEBSERVER = true;

  /**
   * Logs a specific set of data (action) in the table belonging to the specified type
   *
   * @param string $entry_type Log entry type
   * @param array  $data       Data to be inserted
   * @param bool   $forcews    Force initiator to be null
   *
   * @return bool
   * @throws RuntimeException
   */
  public static function logAction($entry_type, $data = null, $forcews = false) {
    $log = new Log([
      'ip' => $_SERVER['REMOTE_ADDR'],
      'entry_type' => $entry_type,
    ]);

    if (Auth::$signed_in && !$forcews)
      $log->initiator = Auth::$user->id;

    $log->data = $data;

    return $log->save();
  }

  public const ACTIONS = [
    'add' => '<span class="color-green"><span class="typcn typcn-plus"></span> Create</span>',
    'del' => '<span class="color-red"><span class="typcn typcn-trash"></span> Delete</span>',
  ];

  public const
    KEYCOLOR_INFO = 'blue',
    KEYCOLOR_ERROR = 'red',
    KEYCOLOR_SUCCESS = 'green',
    SKIP_VALUE = [];

  /**
   * Format log entry details
   *
   * @param Log   $log  Main log entry
   * @param array $data Data to process (sub-log entry)
   *
   * @return array
   * @throws Exception
   */
  public static function formatEntryDetails(Log $log, array $data):array {
    $details = [];

    switch ($log->entry_type){
      case 'rolechange':
        $user = Users::resolveById($data['target']);

        $suffix = ' (invalid role)';
        $old_group = Permission::ROLES_ASSOC[$data['oldrole']] ?? '<del>'.CoreUtils::capitalize($data['oldrole'])."</del>$suffix";
        $new_group = Permission::ROLES_ASSOC[$data['newrole']] ?? '<del>'.CoreUtils::capitalize($data['newrole'])."</del>$suffix";

        $details = [
          ['Target user', $user !== null ? $user->toAnchor() : "Deleted user #{$data['target']}"],
          ['Old group', $old_group],
          ['New group', $new_group],
        ];
      break;
      case 'userfetch':
        $details[] = ['User', Users::resolveById($data['userid'])->toAnchor()];
      break;
      case 'post_lock':
        self::_genericPostInfo($data, $details);
      break;
      case 'major_changes':
        $details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];
        $details[] = ['Reason', CoreUtils::escapeHTML($data['reason'])];
      break;
      case 'req_delete':
        $details[] = self::_getReferenceForDeletedPost($data, 'Request');
        $details[] = ['Description', CoreUtils::escapeHTML($data['label'])];
        $details[] = ['Type', Post::REQUEST_TYPES[$data['type']]];
        $ep = Show::find($data['show_id']);
        $details[] = ['Posted under', !empty($ep) ? $ep->toAnchor() : "Show #{$data['show_id']} <em>(deleted)</em>"];
        $details[] = ['Requested on', Time::tag($data['requested_at'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME)];
        if (!empty($data['requested_by']))
          $details[] = ['Requested by', Users::resolveById($data['requested_by'])->toAnchor()];
        if (!empty($data['reserved_by']))
          $details[] = ['Reserved by', Users::resolveById($data['reserved_by'])->toAnchor()];
        $details[] = ['Finished', !empty($data['deviation_id'])];
        if (!empty($data['deviation_id'])){
          $details[] = ['Deviation', self::link("http://fav.me/{$data['deviation_id']}")];
          $details[] = ['Approved', $data['lock']];
        }
      break;
      case 'img_update':
        self::_genericPostInfo($data, $details);
        $details[] = ['Old image', "<a href='{$data['oldfullsize']}' target='_blank' rel='noopener'>Full size</a><div><img alt='screencap' src='{$data['oldpreview']}'></div>"];
        $details[] = ['New image', "<a href='{$data['newfullsize']}' target='_blank' rel='noopener'>Full size</a><div><img alt='screencap' src='{$data['newpreview']}'></div>"];
      break;
      case 'res_overtake':
        self::_genericPostInfo($data, $details);
        $details[] = ['Previous reserver', Users::resolveById($data['reserved_by'])->toAnchor()];
        $details[] = ['Previously reserved at', Time::tag($data['reserved_at'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME)];

        $diff = Time::difference(strtotime($log->created_at), strtotime($data['reserved_at']));
        $diff_text = Time::differenceToString($diff);
        $details[] = ['In progress for', $diff_text];
      break;
      case 'appearances':
        $details[] = ['Action', self::ACTIONS[$data['action']]];

        if (isset($data['ishuman']))
          $guide = CGUtils::GUIDE_MAP[$data['ishuman'] ? CGUtils::GUIDE_FIM : CGUtils::GUIDE_EQG];
        else if (isset($data['guide']))
          $guide = CGUtils::GUIDE_MAP[$data['guide']];
        else $guide = 'Personal Color Guide';

        $details[] = ['Guide', $guide];
        $details[] = ['ID', self::_getAppearanceLink($data['id'])];
        $details[] = ['Label', $data['label']];
        if (!empty($data['order']))
          $details[] = ['Ordering index', $data['order']];
        if (!empty($data['notes']))
          $details[] = ['Notes', '<div>'.nl2br($data['notes']).'</div>'];
        if (!empty($data['usetemplate']))
          $details[] = ['Template applied', true];
        $details[] = ['Private', !empty($data['private'])];
        if (!empty($data['created_at']))
          $details[] = ['Added', Time::tag($data['created_at'], Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME)];
      break;
      case 'res_transfer':
        self::_genericPostInfo($data, $details);
        $details[] = ['New reserver', Users::resolveById($data['to'])->toAnchor()];
      break;
      case 'cg_modify':
        $details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];
        $CG = ColorGroup::find($data['group_id']);
        if (empty($CG)){
          $details[] = ['Color group ID', '#'.$data['group_id']];
          $details[] = ['Still exists', false];
        }
        else $details[] = ['Group', "{$CG->label} (#{$data['group_id']})"];
        if (isset($data['newlabel']))
          $details[] = ['Label', self::diff($data['oldlabel'] ?? '', $data['newlabel'])];
        if (isset($data['newcolors']))
          $details[] = ['Colors', self::diff($data['oldcolors'] ?? '', $data['newcolors'], 'block')];
      break;
      case 'cgs':
        $details[] = ['Action', self::ACTIONS[$data['action']]];
        $details[] = ['Color group ID', '#'.$data['group_id']];
        $details[] = ['Label', $data['label']];
        $details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];
        if (isset($data['order']))
          $details[] = ['Ordering index', $data['order']];
      break;
      case 'cg_order':
        $details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];
        $details[] = ['Order', self::diff($data['oldgroups'], $data['newgroups'], 'block', new FineDiff\Granularity\Paragraph())];
      break;
      case 'appearance_modify':
        $details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];
        $changes = is_string($data['changes']) ? JSON::decode($data['changes']) : $data['changes'];
        $newOld = self::_arrangeNewOld($changes);

        if (isset($newOld['label']['new']))
          $details[] = ['Label', self::diff($newOld['label']['old'], $newOld['label']['new'], 'block')];

        if (isset($newOld['notes']['new']) || isset($newOld['notes']['old']))
          $details[] = ['Notes', self::diff($newOld['notes']['old'] ?? '', $newOld['notes']['new'] ?? '', 'block smaller', new FineDiff\Granularity\Word())];

        if (isset($newOld['cm_favme']['old']))
          $details[] = ['Old CM Submission', self::link('http://fav.me/'.$newOld['cm_favme']['old'])];
        else if (isset($newOld['cm_favme']['new']))
          $details[] = ['Old CM Submission', null];
        if (isset($newOld['cm_favme']['new']))
          $details[] = ['New CM Submission', self::link('http://fav.me/'.$newOld['cm_favme']['new'])];
        else if (isset($newOld['cm_favme']['old']))
          $details[] = ['New CM Submission', null];

        $olddir = isset($newOld['cm_dir']['old']) ? CGUtils::$CM_DIR[$newOld['cm_dir']['old']] : '';
        $newdir = isset($newOld['cm_dir']['new']) ? CGUtils::$CM_DIR[$newOld['cm_dir']['new']] : '';
        if ($olddir || $newdir)
          $details[] = ['CM Orientation', self::diff($olddir, $newdir, 'inline', new FineDiff\Granularity\Paragraph())];

        if (isset($newOld['private']['new']))
          $details[] = ['<span class="typcn typcn-lock-'.($newOld['private']['new'] ? 'closed' : 'open').'"></span> '.($newOld['private']['new']
                          ? 'Marked private' : 'No longer private'), self::SKIP_VALUE, self::KEYCOLOR_INFO];

        if (isset($newOld['cm_preview']['new']))
          $details[] = ['New Custom CM Preview', "<img src='".CoreUtils::aposEncode($newOld['cm_preview']['new'])."'>"];
        else if (isset($newOld['cm_preview']['old']))
          $details[] = ['New Custom CM Preview', null];
      break;
      case 'da_namechange':
        $da_user = DeviantartUser::find($data['user_id']);
        $newIsCurrent = $da_user->name === $data['new'];
        $details[] = ['User', $da_user->user->toAnchor()];
        if ($newIsCurrent)
          $details[] = ['Old name', $data['old']];
        else
          $details[] = ['Name', self::diff($data['old'], $data['new'])];
      break;
      case 'video_broken':
        $show = Show::find($data['show_id']);
        $details[] = ['Episode', $show->toAnchor()];
        $url = VideoProvider::getEmbed(new ShowVideo([
          'provider_abbr' => $data['provider'],
          'provider_id' => $data['id'],
        ]), VideoProvider::URL_ONLY);
        $details[] = ['Link', "<a href='$url'>$url</a>"];
      break;
      case 'cm_modify':
        $details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];

        $keys = [];
        if (isset($data['olddata'])){
          $keys[] = 'olddata';
          $data['olddata'] = is_string($data['olddata']) ? JSON::decode($data['olddata']) : $data['olddata'];
        }
        if (isset($data['newdata'])){
          $keys[] = 'newdata';
          $data['newdata'] = is_string($data['newdata']) ? JSON::decode($data['newdata']) : $data['newdata'];
        }

        foreach ($keys as $key){
          foreach ($data[$key] as $k => $_){
            foreach ($data[$key][$k] as $i => $v){
              if (!isset($v) || $i === 'id'){
                unset($data[$key][$k][$i]);
                continue;
              }
            }
          }
        }

        $olddata = !empty($data['olddata']) ? JSON::encode($data['olddata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
        $newdata = !empty($data['newdata']) ? JSON::encode($data['newdata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
        if ($olddata || $newdata){
          $diff = self::diff($olddata, $newdata, 'block', new FineDiff\Granularity\Sentence(), function ($diff) {
            return preg_replace('~([^/>a-z\d])(d[a-z\d]{6,})~', '$1<a href="http://fav.me/$2">$2</a>', $diff);
          });
          $details[] = ['Metadata changes', $diff];
        }
      break;
      case 'cm_delete':
        $details[] = ['Appearance', self::_getAppearanceLink($data['appearance_id'])];

        if (!empty($data['data']))
          foreach ($data['data'] as $k => $_){
            foreach ($data['data'][$k] as $i => $v){
              if (!isset($v) || $i === 'id'){
                unset($data['data'][$k][$i]);
                continue;
              }
            }
          }

        $olddata = !empty($data['data']) ? JSON::encode($data['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
        $newdata = '';
        if ($olddata || $newdata){
          $diff = self::diff($olddata, $newdata, 'block', new FineDiff\Granularity\Sentence(), function ($diff) {
            return preg_replace('~([^/>a-z\d])(d[a-z\d]{6,})~', '$1<a href="http://fav.me/$2">$2</a>', $diff);
          });
          $details[] = ['Metadata changes', $diff];
        }
      break;
      case 'post_break':
        self::_genericPostInfo($data, $details);
        $details[] = ['Response Code', "<code>{$data['response_code']}</code>"];
        $escaped_url = CoreUtils::aposEncode($data['failing_url']);
        $details[] = ['Failing URL', "<a href='$escaped_url'>$escaped_url</a>"];
      break;
      case 'post_fix':
        self::_genericPostInfo($data, $details);
      break;
      break;
      case 'staff_limits':
        $details[] = ['For', Users::resolveById($data['user_id'])->toAnchor()];
        $details[] = ['Limitation', UserSettingForm::INPUT_MAP[$data['setting']]['options']['desc']];
        $icon = $data['allow'] ? 'tick' : 'times';
        $text = $data['allow'] ? 'Now allowed' : 'Now disallowed';
        $keyc = $data['allow'] ? self::KEYCOLOR_SUCCESS : self::KEYCOLOR_ERROR;
        $details[] = ["<span class='typcn typcn-$icon'></span> $text", self::SKIP_VALUE, $keyc];
      break;
      case 'failed_auth_attempts':
        $browser = !empty($data['user_agent']) ? CoreUtils::detectBrowser($data['user_agent']) : null;
        $details[] = ['Browser', $browser === null ? 'Unknown' : "{$browser['browser_name']} {$browser['browser_ver']} on {$browser['platform']}"];
        if (!empty($data['user_agent']))
          $details[] = ['User Agent', $data['user_agent']];
      break;
      case 'derpimerge':
        self::_genericPostInfo($data, $details);
        $details[] = ['Original image URLs', "<a href='{$data['original_fullsize']}' target='_blank' rel='noopener'>Full size</a> / <a href='{$data['original_preview']}' target='_blank' rel='noopener'>Preview</a>"];
        $details[] = ['New image', "<a href='{$data['new_fullsize']}' target='_blank' rel='noopener'>Full size</a><div><img alt='screencap' src='{$data['new_preview']}'></div>"];
      break;
      default:
        $details[] = ["<span class=\"typcn typcn-warning\"></span> Couldn't process details", 'No data processor defined for this entry type', self::KEYCOLOR_ERROR];
        $details[] = ['Raw details', '<pre>'.var_export($data, true).'</pre>'];
      break;
    }

    return ['details' => $details];
  }

  private static function get_post(array $data):?Post {
    if (!empty($data['post_id']))
      return Post::find($data['post_id']);

    if ($data['type'] === 'post')
      return Post::find($data['id']);

    $type = $data['type'] === 'request' ? 'request' : 'reservation';

    return LegacyPostMapping::lookup($data['old_id'], $type);
  }

  const REF_KEY = 'Reference';

  /**
   * @param array $data
   * @param array $details
   *
   * @throws Exception
   */
  private static function _genericPostInfo(array $data, array &$details) {
    $post = self::get_post($data);

    if (empty($post)){
      $details[] = self::_getReferenceForDeletedPost($data);
      $details[] = ['<span class="typcn typcn-info-large"></span> No longer exists', self::SKIP_VALUE, self::KEYCOLOR_INFO];
    }
    else {
      $details[] = [self::REF_KEY, $post->toAnchor("Post #{$post->id}")];
      $details[] = ['Kind', CoreUtils::capitalize($post->kind)];
      $details[] = ['Posted under', $post->show->toAnchor()];
      $details[] = ['Posted by', $post->poster->toAnchor()];
      if ($post->reserved_by !== null)
        $details[] = ['Reserved by', $post->reserver->toAnchor()];
      else $details[] = ['Reserved', false];
    }
  }

  private static function _getReferenceForDeletedPost(array $data, ?string $force_type = null) {
    $new_post = isset($data['id']);
    $type = $new_post ? 'Post' : ($force_type ?? $data['type']);
    $id = $new_post ? $data['id'] : $data['old_id'];

    return [self::REF_KEY, CoreUtils::capitalize($type)." #$id"];
  }

  /**
   * @param int $id
   *
   * @return string
   */
  private static function _getAppearanceLink(int $id):string {
    $ID = "#$id";
    try {
      $Appearance = Appearance::find($id);
    }
    catch (RecordNotFound $e){
      return $ID;
    }

    if (!empty($Appearance))
      $ID = "<a href='{$Appearance->toURL()}'>".CoreUtils::escapeHTML($Appearance->label)."</a> ($ID)";

    return $ID;
  }

  private static function _arrangeNewOld($data) {
    $newOld = [];
    unset($data['entryid'], $data['target']);
    foreach ($data as $k => $v){
      if ($v === null)
        continue;

      $thing = mb_substr($k, 3);
      $type = mb_substr($k, 0, 3);
      if (!isset($newOld[$thing]))
        $newOld[$thing] = [];
      $newOld[$thing][$type] = $v;
    }

    return $newOld;
  }

  private static function link($url, $blank = false) {
    return "<a href='".CoreUtils::aposEncode($url)."' ".($blank ? 'target="_blank" rel="noopener"' : '').">$url</a>";
  }

  public const LOCALHOST_IPS = ['::1', '127.0.0.1', '::ffff:127.0.0.1'];

  public static function validateEntryType($key, $optional = false, $method_get = false) {
    return (new Input($key, function ($value) {
      if (!isset(self::LOG_DESCRIPTION[$value]))
        return Input::ERROR_INVALID;
    }, [
      Input::IS_OPTIONAL => $optional,
      Input::SOURCE => $method_get ? 'GET' : 'POST',
    ]))->out();
  }

  public static function diff(string $old, string $new, $type = 'inline', FineDiff\Granularity\Granularity $gran = null, ?callable $transformer = null):string {
    if (!isset($gran))
      $gran = new FineDiff\Granularity\Character;
    else if ($gran instanceof FineDiff\Granularity\Paragraph)
      $old .= "\n";
    $diff = str_replace('\n', "\n", (new FineDiff\Diff($gran))->render($old, $new));

    if ($transformer !== null) {
      $diff = $transformer($diff);
    }

    return "<span class='btn darkblue view-switch' title='Left/Right click to change view mode'>diff</span><div class='log-diff $type'>$diff</div>";
  }

}
