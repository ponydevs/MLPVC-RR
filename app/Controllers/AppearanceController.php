<?php

namespace App\Controllers;

use ActiveRecord\Table;
use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\Cutiemarks;
use App\DB;
use App\Exceptions\MismatchedProviderException;
use App\File;
use App\HTTP;
use App\ImageProvider;
use App\Input;
use App\JSON;
use App\Logs;
use App\Models\Appearance;
use App\Models\CachedDeviation;
use App\Models\ColorGroup;
use App\Models\Cutiemark;
use App\Models\Notification;
use App\Models\PCGSlotHistory;
use App\Models\RelatedAppearance;
use App\Models\Show;
use App\Models\ShowAppearance;
use App\Models\TagChange;
use App\Pagination;
use App\Permission;
use App\Regexes;
use App\Response;
use App\ShowHelper;
use App\Tags;
use App\UploadedFile;
use App\UserPrefs;
use App\Users;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Exception;
use Ramsey\Uuid\Uuid;
use function count;
use function in_array;

class AppearanceController extends ColorGuideController {
  public function view($params):void {
    if ($this->owner === null)
      $this->_initialize($params);
    $this->load_appearance($params);

    if ($this->appearance->hidden())
      CoreUtils::noPerm();

    CoreUtils::fixPath($this->appearance->toURL());

    $cm_count = count($this->appearance->cutiemarks);
    $cmv = $cm_count > 0 ? ' and cutie mark '.CoreUtils::makePlural('vector', $cm_count) : '';

    $guide_name = ($this->owner ? CoreUtils::posess($this->owner->name).' Personal' : CGUtils::GUIDE_MAP[$this->guide]).' Color Guide';

    $settings = [
      'title' => "{$this->appearance->label} - $guide_name",
      'heading' => $this->appearance->getBabelLabel(),
      'css' => ['pages/colorguide/guide', true],
      'js' => ['jquery.ctxmenu', 'pages/colorguide/guide', true],
      'og' => [
        'image' => $this->appearance->getSpriteURL(),
        'description' => "Show accurate colors$cmv for \"{$this->appearance->label}\" from the MLP-VectorClub's Official Color Guide",
        'tags' =>
          ($cm_count > 0 ? 'cutie mark,cm,cm vector,cutie mark vector,' : '').
          $this->appearance->getTagsAsText(null, ',').
          ',color guide,colors,swatch file,illustrator swatches,gimp palette,inkscape swatches,png download',
      ],
      'import' => [
        'appearance' => $this->appearance,
        'guide' => $this->guide,
        'is_owner' => false,
      ],
    ];
    if (!empty($this->appearance->owner_id)){
      $settings['import']['owner'] = $this->owner;
      $settings['import']['is_owner'] = $this->is_owner;
      $settings['og']['description'] = "Colors$cmv for \"{$this->appearance->label}\" from ".CoreUtils::posess($this->owner->name)." Personal Color Guide on the the MLP-VectorClub's website";
    }
    if ($this->is_owner || Permission::sufficient('staff')){
      self::_appendManageAssets($settings);
      $settings['import']['exports'] = [
        'TAG_TYPES_ASSOC' => Tags::TAG_TYPES,
        'TAG_NAME_REGEX' => Regexes::$tag_name,
        'MAX_SIZE' => CoreUtils::getMaxUploadSize(),
        'HEX_COLOR_PATTERN' => Regexes::$hex_color,
      ];
    }
    CoreUtils::loadPage('ColorGuideController::appearance', $settings);
  }

  public function viewPersonal($params):void {
    $this->_initialize($params);
    if ($this->owner === null)
      CoreUtils::notFound();

    $this->view($params);
  }

  public function tagChanges($params):void {
    // TODO Finish feature
    CoreUtils::notFound();

    if (Permission::insufficient('staff'))
      Response::fail();

    $this->_initialize($params);
    $this->load_appearance($params);

    if ($this->appearance->owner_id !== null)
      CoreUtils::notFound();

    $totalChangeCount = TagChange::count(['appearance_id' => $this->appearance->id]);
    /** @noinspection PhpUnusedLocalVariableInspection */
    $Pagination = new Pagination("{$this->path}/tag-changes/{$this->appearance->getURLSafeLabel()}", 25, $totalChangeCount);
  }

  public function asFile($params):void {
    $this->_initialize($params);
    $this->load_appearance($params);

    if ($this->appearance->hidden())
      CoreUtils::notFound();

    switch ($params['ext']){
      case 'png':
        switch ($params['type']){
          case 's':
            HTTP::tempRedirect($this->appearance->getSpriteURL());
          case 'p':
          default:
            CGUtils::renderAppearancePNG($this->path, $this->appearance);
        }
      break;
      case 'svg':
        if (!empty($params['type'])) switch ($params['type']){
          case 's':
            CGUtils::renderSpriteSVG($this->path, $this->appearance);
          case 'p':
            CGUtils::renderPreviewSVG($this->appearance);
          case 'f':
            CGUtils::renderCMFacingSVG($this->appearance);
          default:
            CoreUtils::notFound();
        }
      case 'json':
        CGUtils::getSwatchesAI($this->appearance);
      case 'gpl':
        CGUtils::getSwatchesInkscape($this->appearance);
    }
    # rendering functions internally call die(), so execution stops above #

    CoreUtils::notFound();
  }

  public function api($params):void {
    $this->_initialize($params);

    if (!Auth::$signed_in)
      Response::fail();

    if ($this->creating){
      Appearance::checkCreatePermission(Auth::$user, $this->guide === null);
    }
    else {
      $this->load_appearance($params);
      $this->appearance->enforceManagePermission();
    }

    switch ($this->action){
      case 'GET':
        Response::done([
          'label' => $this->appearance->label,
          'notes' => $this->appearance->notes_src,
          'private' => $this->appearance->private,
        ]);
      break;
      case 'PUT':
      case 'POST':
        // Force personal guides for non-staff
        $guide = null;
        if (Permission::sufficient('staff')) {
          $guide = (new Input('guide', function ($value){
            if (!isset(CGUtils::GUIDE_MAP[$value]))
              return Input::ERROR_INVALID;
          }, [
            Input::IS_OPTIONAL => true,
            Input::CUSTOM_ERROR_MESSAGES => [
              Input::ERROR_INVALID => 'Guide is invalid: @value',
            ],
          ]))->out();
        }

        $this->guide = $guide;

        /** @var $data array */
        $data = ['guide' => $this->guide];

        $label = (new Input('label', 'string', [
          Input::IN_RANGE => [2, 70],
          Input::CUSTOM_ERROR_MESSAGES => [
            Input::ERROR_MISSING => 'Appearance name is missing',
            Input::ERROR_RANGE => 'Appearance name must be beetween @min and @max characters long',
          ],
        ]))->out();
        CoreUtils::checkStringValidity($label, 'Appearance name');
        $dupe = Appearance::find_dupe($this->creating, [
          'owner_id' => Auth::$user->id,
          'guide' => $data['guide'],
          'label' => $label,
          'id' => $this->creating ? null : $this->appearance->id,
        ]);
        if (!empty($dupe)){
          if ($this->guide === null)
            Response::fail('You already have an appearance with the same name in your Personal Color Guide');

          Response::fail("An appearance <a href='{$dupe->toURL()}' target='_blank'>already exists</a> in the ".CGUtils::GUIDE_MAP[$this->guide].' guide with this exact name. Consider adding an identifier in brackets or choosing a different name.');
        }
        if ($this->creating || $label !== $this->appearance->label)
          $data['label'] = $label;

        $notes = (new Input('notes', 'text', [
          Input::IS_OPTIONAL => true,
          Input::IN_RANGE => $this->creating || $this->appearance->id !== 0 ? [null, 1000] : null,
          Input::CUSTOM_ERROR_MESSAGES => [
            Input::ERROR_RANGE => 'Appearance notes cannot be longer than @max characters',
          ],
        ]))->out();
        if ($notes !== null){
          CoreUtils::checkStringValidity($notes, 'Appearance notes');
          if ($this->creating || $notes !== $this->appearance->notes_src)
            $data['notes_src'] = $notes;
        }
        else $data['notes_src'] = null;

        $data['private'] = (new Input('private', 'bool', [
          Input::IS_OPTIONAL => true,
        ]))->out();

        if ($this->creating){
          if ($this->guide === null){
            $data['owner_id'] = Auth::$user->id;
          }
          if (empty($data['owner_id'])){
            $biggest_order = DB::$instance->disableAutoClass()
              ->where('guide', $data['guide'])
              ->getOne('appearances', 'MAX("order") as "order"');
            $data['order'] = ($biggest_order['order'] ?? 0) + 1;
          }
        }
        else if ($data['private'] === true){
          $data['last_cleared'] = date('c');
        }

        /** @var $new_appearance Appearance */
        if ($this->creating){
          $new_appearance = Appearance::create($data);
          $new_appearance->reindex();
        }
        else {
          $old_data = $this->appearance->to_array();
          $this->appearance->update_attributes($data);
          $this->appearance->reindex();
        }

        /** @var Appearance $edited_appearance */
        $edited_appearance = $this->creating ? $new_appearance : $this->appearance;

        if ($this->creating){
          $data['id'] = $new_appearance->id;
          $response = [
            'message' => 'Appearance added successfully',
            'goto' => $new_appearance->toURL(),
          ];
          $use_template = (new Input('template', 'bool', [
            Input::IS_OPTIONAL => true,
          ]))->out();
          if ($use_template){
            try {
              $new_appearance->applyTemplate();
            }
            catch (Exception $e){
              $response['message'] .= ', but applying the template failed';
              $response['info'] = 'The common color groups could not be added.<br>Reason: '.$e->getMessage();
              $use_template = false;
            }
          }

          Logs::logAction('appearances', [
            'action' => 'add',
            'id' => $new_appearance->id,
            'order' => $new_appearance->order,
            'label' => $new_appearance->label,
            'notes' => $new_appearance->notes_src,
            'guide' => $new_appearance->guide,
            'usetemplate' => $use_template,
            'private' => $new_appearance->private,
            'owner_id' => $new_appearance->owner_id,
          ]);

          if ($new_appearance->owner_id !== null){
            PCGSlotHistory::record($new_appearance->owner_id, 'appearance_add', null, [
              'id' => $new_appearance->id,
              'label' => $new_appearance->label,
            ]);
            $new_appearance->owner->syncPCGSlotCount();
          }

          Response::done($response);
        }

        $this->appearance->clearRenderedImages([Appearance::CLEAR_PALETTE, Appearance::CLEAR_PREVIEW]);

        if (!$this->creating){
          $diff = [];
          foreach (['label' => true, 'notes_src' => 'notes', 'private' => true, 'owner_id' => true] as $orig => $mapped){
            $key = $mapped === true ? $orig : $mapped;
            if ($edited_appearance->{$orig} !== $old_data[$orig]){
              $diff["old$key"] = $old_data[$orig];
              $diff["new$key"] = $edited_appearance->{$orig};
            }
          }
          if (!empty($diff)) Logs::logAction('appearance_modify', [
            'appearance_id' => $this->appearance->id,
            'changes' => JSON::encode($diff),
          ]);
        }

        $response = [];
        if (!$this->appearance_page){
          $response['label'] = $edited_appearance->label;
          if (isset($old_data['label']) && $old_data['label'] !== $this->appearance->label)
            $response['newurl'] = $edited_appearance->toURL();
          $response['notes'] = $edited_appearance->getNotesHTML(NOWRAP);
        }

        Response::done($response);
      break;
      case 'DELETE':
        if ($this->appearance->protected)
          Response::fail('This appearance cannot be deleted');

        $tagged = Tags::getFor($this->appearance->id, null);

        if (!DB::$instance->where('id', $this->appearance->id)->delete(Appearance::$table_name))
          Response::dbError();

        if ($this->appearance->owner_id === null){
          try {
            CoreUtils::elasticClient()->delete($this->appearance->toElasticArray(true));
          }
          catch (Missing404Exception $e){
            $message = JSON::decode($e->getMessage());

            // Eat error if appearance was not indexed
            if (!isset($message['found']) || $message['found'] !== false)
              throw $e;
          }
          catch (NoNodesAvailableException $e){
            CoreUtils::logError('ElasticSearch server was down when server attempted to remove appearance '.$this->appearance->id);
          }
        }

        if (!empty($tagged))
          foreach ($tagged as $tag)
            $tag->updateUses();

        $fpath = $this->appearance->getSpriteFilePath();
        CoreUtils::deleteFile($fpath);

        $this->appearance->clearRenderedImages();

        Logs::logAction('appearances', [
          'action' => 'del',
          'id' => $this->appearance->id,
          'order' => $this->appearance->order,
          'label' => $this->appearance->label,
          'notes' => $this->appearance->notes_src,
          'guide' => $this->appearance->guide,
          'added' => $this->appearance->created_at,
          'private' => $this->appearance->private,
          'owner_id' => $this->appearance->owner_id,
        ]);

        /** @var $spriteColorNotifs Notification[] */
        $spriteColorNotifs = DB::$instance
          ->where('type', 'sprite-colors')
          ->where("data->'appearance_id'", $this->appearance->id)
          ->get(Notification::$table_name);
        foreach ($spriteColorNotifs as $notif)
          $notif->safeMarkRead();

        if ($this->appearance->owner_id !== null){
          PCGSlotHistory::record($this->appearance->owner_id, 'appearance_del', null, [
            'id' => $this->appearance->id,
            'label' => $this->appearance->label,
          ]);
          $this->appearance->owner->syncPCGSlotCount();
        }

        Response::success('Appearance removed');
      break;
      default:
        CoreUtils::notAllowed();
    }
  }

  public function applyTemplate($params):void {
    if ($this->action !== 'POST')
      CoreUtils::notAllowed();

    $this->load_appearance($params);
    $this->appearance->enforceManagePermission();

    try {
      $this->appearance->applyTemplate();
    }
    catch (Exception $e){
      Response::fail('Applying the template failed. Reason: '.$e->getMessage());
    }

    Response::done(['cgs' => $this->appearance->getColorsHTML(!$this->appearance_page, NOWRAP)]);
  }

  public function selectiveClear($params):void {
    if ($this->action !== 'DELETE')
      CoreUtils::notAllowed();

    $this->load_appearance($params);
    $this->appearance->enforceManagePermission();

    $wipe_cache = (new Input('wipe_cache', 'bool', [
      Input::IS_OPTIONAL => true,
    ]))->out();
    if ($wipe_cache)
      $this->appearance->clearRenderedImages();

    $wipe_cm_tokenized = (new Input('wipe_cm_tokenized', 'bool', [
      Input::IS_OPTIONAL => true,
    ]))->out();
    if ($wipe_cm_tokenized){
      foreach ($this->appearance->cutiemarks as $cm){
        CoreUtils::deleteFile($cm->getTokenizedFilePath());
        CoreUtils::deleteFile($cm->getRenderedFilePath());
      }
    }

    $wipe_cm_source = (new Input('wipe_cm_source', 'bool', [
      Input::IS_OPTIONAL => true,
    ]))->out();
    if ($wipe_cm_source){
      foreach ($this->appearance->cutiemarks as $cm)
        CoreUtils::deleteFile($cm->getSourceFilePath());
    }

    $wipe_sprite = (new Input('wipe_sprite', 'bool', [
      Input::IS_OPTIONAL => true,
    ]))->out();
    if ($wipe_sprite)
      $this->appearance->deleteSprite();

    $wipe_colors = (new Input('wipe_colors', 'string', [
      Input::IS_OPTIONAL => true,
    ]))->out();
    switch ($wipe_colors){
      case 'color_hex':
        if ($this->appearance->hasColors(true)){
          /** @noinspection NestedPositiveIfStatementsInspection */
          if (!DB::$instance->query('UPDATE colors SET hex = null WHERE group_id IN (SELECT id FROM color_groups WHERE appearance_id = ?)', [$this->appearance->id]))
            Response::dbError();
        }
      break;
      case 'color_all':
        if ($this->appearance->hasColors()){
          /** @noinspection NestedPositiveIfStatementsInspection */
          if (!DB::$instance->query('DELETE FROM colors WHERE group_id IN (SELECT id FROM color_groups WHERE appearance_id = ?)', [$this->appearance->id]))
            Response::dbError();
        }
      break;
      case 'all':
        if (ColorGroup::exists(['conditions' => ['appearance_id = ?', $this->appearance->id]])){
          /** @noinspection NestedPositiveIfStatementsInspection */
          if (!DB::$instance->query('DELETE FROM color_groups WHERE appearance_id = ?', [$this->appearance->id]))
            Response::dbError();
        }
      break;
    }

    if (empty($this->appearance->owner_id)){
      $wipe_tags = (new Input('wipe_tags', 'bool', [
        Input::IS_OPTIONAL => true,
      ]))->out();
      if ($wipe_tags && !empty($this->appearance->tagged)){
        if (!DB::$instance->where('appearance_id', $this->appearance->id)->delete('tagged'))
          Response::dbError('Failed to wipe tags');
        foreach ($this->appearance->tagged as $tag)
          Tags::updateUses($tag->tag_id);
      }
    }

    /**
     * @see Appearance::$last_cleared
     */
    $update = ['last_cleared' => date('c')];

    $wipe_notes = (new Input('wipe_notes', 'bool', [
      Input::IS_OPTIONAL => true,
    ]))->out();
    /**
     * @see Appearance::$notes_src
     * @see Appearance::$notes_rend
     */
    if ($wipe_notes){
      $update['notes_src'] = null;
      $update['notes_rend'] = null;
    }

    $mkpriv = (new Input('mkpriv', 'bool', [
      Input::IS_OPTIONAL => true,
    ]))->out();
    /**
     * @see Appearance::$private
     */
    if ($mkpriv)
      $update['private'] = 1;

    $reset_priv_key = (new Input('reset_priv_key', 'bool', [
      Input::IS_OPTIONAL => true,
    ]))->out();
    /**
     * @see Appearance::$token
     */
    if ($reset_priv_key)
      $update['token'] = Uuid::uuid4();

    if (!empty($update))
      DB::$instance->where('id', $this->appearance->id)->update('appearances', $update);

    Response::done();
  }

  public function colorGroupsApi($params):void {
    $this->load_appearance($params);
    $this->appearance->enforceManagePermission();

    switch ($this->action){
      case 'GET':
        $cgs = $this->appearance->color_groups;
        if (empty($cgs))
          Response::fail('This appearance does not have any color groups');
        if (count($cgs) < 2)
          Response::fail('An appearance needs at least 2 color groups before you can change their order');
        foreach ($cgs as $i => $cg)
          $cgs[$i] = $cg->to_array([
            'only' => ['id', 'label'],
          ]);
        Response::done(['cgs' => $cgs]);
      break;
      case 'PUT':
        /** @var $order int[] */
        $order = (new Input('cgs', 'int[]', [
          Input::CUSTOM_ERROR_MESSAGES => [
            Input::ERROR_MISSING => 'Color group order data missing',
            Input::ERROR_INVALID => 'Color group order data (@value) is invalid',
          ],
        ]))->out();
        $oldCGs = DB::$instance->where('appearance_id', $this->appearance->id)->get('color_groups');
        $possibleIDs = [];
        foreach ($oldCGs as $cg)
          $possibleIDs[$cg->id] = true;
        foreach ($order as $i => $GroupID){
          if (empty($possibleIDs[$GroupID]))
            Response::fail("There's no group with the ID of $GroupID on this appearance");

          DB::$instance->where('id', $GroupID)->update('color_groups', ['order' => $i]);
        }
        Table::clear_cache();
        $newCGs = DB::$instance->where('appearance_id', $this->appearance->id)->get('color_groups');

        $this->appearance->clearRenderedImages([Appearance::CLEAR_PALETTE, Appearance::CLEAR_PREVIEW]);

        $oldCGs = CGUtils::stringifyColorGroups($oldCGs);
        $newCGs = CGUtils::stringifyColorGroups($newCGs);
        if ($oldCGs !== $newCGs) Logs::logAction('cg_order', [
          'appearance_id' => $this->appearance->id,
          'oldgroups' => $oldCGs,
          'newgroups' => $newCGs,
        ]);

        Response::done(['cgs' => $this->appearance->getColorsHTML(!$this->appearance_page, NOWRAP)]);
      break;
      default:
        CoreUtils::notAllowed();
    }
  }

  public function sprite($params):void {
    if (Permission::insufficient('member'))
      CoreUtils::noPerm();

    $this->load_appearance($params);
    $this->appearance->enforceManagePermission();

    $pcg = $this->appearance->owner_id !== null;
    if ($pcg)
      $params['name'] = $this->appearance->owner->name;
    $this->_initialize($params);

    $Map = CGUtils::getSpriteImageMap($this->appearance->id, $pcg);
    if (empty($Map))
      CoreUtils::notFound();

    [$Colors] = $this->appearance->getSpriteRelevantColors();

    $SafeLabel = $this->appearance->getURLSafeLabel();
    CoreUtils::fixPath("{$this->path}/sprite/{$this->appearance->id}-$SafeLabel");

    CoreUtils::loadPage('ColorGuideController::sprite', [
      'title' => "Sprite of {$this->appearance->label}",
      'css' => [true],
      'js' => [true],
      'import' => [
        'appearance' => $this->appearance,
        'colors' => $Colors,
        'map' => $Map,
        'guide' => $this->guide,
        'owner' => $this->appearance->owner,
      ],
    ]);
  }

  public function spriteApi($params):void {
    $this->load_appearance($params);
    $this->appearance->enforceManagePermission();

    $final_path = $this->appearance->getSpriteFilePath();

    switch ($this->action){
      case 'POST':
        if ($this->appearance->owner_id === Auth::$user->id && !UserPrefs::get('a_pcgsprite'))
          Response::fail('You are not allowed to upload sprite images on your own PCG appearances');
        CGUtils::processUploadedImage('sprite', $final_path, ['image/png'], [300], [700, 300]);
        $this->appearance->clearRenderedImages();
        $this->appearance->regenerateSpriteHash();
        $this->appearance->checkSpriteColors();

        Response::done(['path' => $this->appearance->getSpriteURL()]);
      break;
      case 'DELETE':
        if (!$this->appearance->hasSprite())
          Response::fail('No sprite file found');

        $this->appearance->deleteSprite($final_path);

        Response::done(['sprite' => DEFAULT_SPRITE]);
      break;
      default:
        CoreUtils::notAllowed();
    }
  }

  public function relationsApi($params):void {
    $this->load_appearance($params);
    $this->appearance->enforceManagePermission();

    if (!empty($this->appearance->owner_id))
      Response::fail('Relations are unavailable for appearances in personal guides');

    switch ($this->action){
      case 'GET':
        $RelatedAppearances = $this->appearance->related_appearances;
        $RelatedAppearanceIDs = [];
        foreach ($RelatedAppearances as $p)
          $RelatedAppearanceIDs[$p->target_id] = $p->is_mutual;

        $Appearances = DB::$instance->disableAutoClass()
          ->where('guide', $this->guide)
          ->where('id', [...CGUtils::HIDDEN_APPEARANCES, $this->appearance->id], '!=')
          ->orderBy('label')
          ->get('appearances', null, 'id,label');

        $Sorted = [
          'unlinked' => [],
          'linked' => [],
        ];
        foreach ($Appearances as $a){
          $linked = isset($RelatedAppearanceIDs[$a['id']]);
          if ($linked)
            $a['mutual'] = $RelatedAppearanceIDs[$a['id']];
          $Sorted[$linked ? 'linked' : 'unlinked'][] = $a;
        }

        Response::done($Sorted);
      break;
      case 'PUT':
        /** @var $AppearanceIDs int[] */
        $AppearanceIDs = (new Input('ids', 'int[]', [
          Input::IS_OPTIONAL => true,
          Input::CUSTOM_ERROR_MESSAGES => [
            Input::ERROR_INVALID => 'Appearance ID list is invalid',
          ],
        ]))->out();
        /** @var $MutualIDs int[] */
        $MutualIDs = (new Input('mutuals', 'int[]', [
          Input::IS_OPTIONAL => true,
          Input::CUSTOM_ERROR_MESSAGES => [
            Input::ERROR_INVALID => 'Mutial relation ID list is invalid',
          ],
        ]))->out();

        $appearances = [];
        if (!empty($AppearanceIDs))
          foreach ($AppearanceIDs as $id)
            $appearances[$id] = true;

        $mutuals = [];
        if (!empty($MutualIDs))
          foreach ($MutualIDs as $id)
            $mutuals[$id] = true;

        $this->appearance->clearRelations();
        if (!empty($appearances))
          foreach ($appearances as $id => $_)
            RelatedAppearance::make($this->appearance->id, $id, isset($mutuals[$id]));

        $out = [];
        if ($this->appearance_page)
          $out['section'] = $this->appearance->getRelatedHTML();
        Response::done($out);
      break;
      default:
        CoreUtils::notAllowed();
    }
  }

  public function cutiemarkApi($params):void {
    $this->load_appearance($params);
    $this->appearance->enforceManagePermission();

    switch ($this->action){
      case 'GET':
        $cms = Cutiemarks::get($this->appearance);
        foreach ($cms as &$cm)
          $cm = $cm->to_js_response();
        unset($cm);

        $processed_cms = Cutiemarks::get($this->appearance);

        Response::done(['cms' => $cms, 'preview' => Cutiemarks::getListForAppearancePage($processed_cms, NOWRAP)]);
      break;
      case 'PUT':
        $grab_cms = Cutiemarks::get($this->appearance);
        /** @var $current_cms Cutiemark[] */
        $current_cms = [];
        foreach ($grab_cms as $cm)
          $current_cms[$cm->id] = $cm;
        /** @var $data array */
        $data = (new Input('CMData', 'json', [
          Input::CUSTOM_ERROR_MESSAGES => [
            Input::ERROR_MISSING => 'Cutie mark data is missing',
            Input::ERROR_INVALID => 'Cutie mark data (@value) is invalid',
          ],
        ]))->out();
        if (count($data) > 4)
          Response::fail('Appearances can only have a maximum of 4 cutie marks.');
        /** @var $new_cms Cutiemark[] */
        $new_cms = [];
        $new_svgs = [];
        $new_ids = [];
        $labels = [];
        foreach ($data as $i => $item){
          if (isset($item['id'])){
            $cm = Cutiemark::find($item['id']);
            if (empty($cm))
              Response::fail("The cutie mark you're trying to update (#{$item['id']}) does not exist");
            $new_ids[] = $cm->id;
          }
          else $cm = new Cutiemark([
            'appearance_id' => $this->appearance->id,
          ]);

          $svg_data_missing = empty($item['svgdata']);
          if ($cm->id === null || !$svg_data_missing){
            if ($svg_data_missing)
              Response::fail('SVG data is missing');
            if (CoreUtils::stringSize($item['svgdata']) > UploadedFile::SIZES['megabyte'])
              Response::fail('SVG data exceeds the maximum size of 1 MB');
            if (CoreUtils::validateSvg($item['svgdata']) !== Input::ERROR_NONE)
              Response::fail('SVG data is invalid');
            $svgdata = $item['svgdata'];
          }
          else $svgdata = null;

          $label = null;
          if (isset($item['label'])){
            $item['label'] = CoreUtils::trim($item['label']);
            if (!empty($item['label'])){
              CoreUtils::checkStringValidity($item['label'], 'Cutie Mark label');
              if (Input::checkStringLength($item['label'], [1, 32]) === Input::ERROR_RANGE)
                Response::fail('Cutie mark label must be between 1 and 32 chars long');
              if (isset($labels[$item['label']]))
                Response::fail('Cutie mark labels must be unique within an appearance');
              else $labels[$item['label']] = true;
              $label = $item['label'];
            }
          }
          $cm->label = $label;

          if (isset($item['facing'])){
            $facing = CoreUtils::trim($item['facing']);
            if (empty($facing))
              $facing = null;
            else if (!in_array($facing, Cutiemarks::VALID_FACING_VALUES, true))
              Response::fail('Body orientation "'.CoreUtils::escapeHTML($facing).'" is invalid');
          }
          else $facing = null;
          $cm->facing = $facing;

          switch ($item['attribution']){
            case 'deviation':
              if (empty($item['deviation']))
                Response::fail('Deviation link is missing');

              try {
                $image = new ImageProvider(CoreUtils::trim($item['deviation']), ImageProvider::PROV_DEVIATION, true);
                /** @var $deviation CachedDeviation */
                $deviation = $image->extra;
              }
              catch (MismatchedProviderException $e){
                Response::fail('The link must point to a DeviantArt submission, '.$e->getActualProvider().' links are not allowed');
              }
              catch (Exception $e){
                Response::fail('Error while checking deviation link: '.$e->getMessage());
              }

              if (empty($deviation))
                Response::fail('The provided deviation could not be fetched');
              $cm->favme = $deviation->id;
              $contributor = Users::getDA($deviation->author, 'name');
              if (empty($contributor))
                Response::fail("The provided deviation's creator could not be fetched");
              $cm->contributor_id = $contributor->id;
            break;
            case 'user':
              if (empty($item['username']))
                Response::fail('Username is missing');
              if (!preg_match(Regexes::$username, $item['username']))
                Response::fail("Username ({$item['username']}) is invalid");
              $contributor = Users::getDA($item['username'], 'name');
              if (empty($contributor))
                Response::fail("The provided deviation's creator could not be fetched");
              $cm->favme = null;
              $cm->contributor_id = $contributor->id;
            break;
            case 'none':
              $cm->favme = null;
              $cm->contributor_id = null;
            break;
            default:
              Response::fail('The specified attribution method is invalid');
          }

          if (!isset($item['rotation']))
            Response::fail('Preview rotation amount is missing');
          if (!is_numeric($item['rotation']))
            Response::fail('Preview rotation must be a number');
          $rotation = (int)$item['rotation'];
          if (abs($rotation) > 45)
            Response::fail('Preview rotation must be between -45 and 45');
          $cm->rotation = $rotation;

          $new_cms[$i] = $cm;
          $new_svgs[$i] = $svgdata;
        }

        if (!empty($new_cms)){
          CoreUtils::createFoldersFor(Cutiemark::SOURCE_FOLDER);
          foreach ($new_cms as $i => $cm){
            if (!$cm->save())
              Response::dbError("Saving cutie mark (index $i) failed");

            if ($new_svgs[$i] !== null){
              if (false !== File::put($cm->getSourceFilePath(), $new_svgs[$i])){
                CoreUtils::deleteFile($cm->getTokenizedFilePath());
                CoreUtils::deleteFile($cm->getRenderedFilePath());
                continue;
              }

              Response::fail("Saving SVG data for cutie mark (index $i) failed");
            }
          }

          $removed_ids = CoreUtils::array_subtract(array_keys($current_cms), $new_ids);
          if (!empty($removed_ids)){
            foreach ($removed_ids as $removedID)
              $current_cms[$removedID]->delete();
          }

          $cutie_marks = Cutiemarks::get($this->appearance);
          $old_data = Cutiemarks::convertDataForLogs($current_cms);
          $new_data = Cutiemarks::convertDataForLogs($cutie_marks);
          if ($old_data !== $new_data)
            Logs::logAction('cm_modify', [
              'appearance_id' => $this->appearance->id,
              'olddata' => $old_data,
              'newdata' => $new_data,
            ]);
        }
        else {
          foreach ($current_cms as $cm)
            $cm->delete();

          $this->appearance->clearRenderedImages([Appearance::CLEAR_CMDIR]);

          Logs::logAction('cm_delete', [
            'appearance_id' => $this->appearance->id,
            'data' => Cutiemarks::convertDataForLogs($current_cms),
          ]);

          $cutie_marks = [];
        }

        $data = [];
        if ($this->appearance_page && !empty($cutie_marks))
          $data['html'] = Cutiemarks::getListForAppearancePage(Cutiemarks::get($this->appearance));
        Response::done($data);
      break;
      default:
        CoreUtils::notAllowed();
    }
  }

  public function taggedApi($params):void {
    $this->load_appearance($params);
    $this->appearance->enforceManagePermission();

    if ($this->appearance->owner_id !== null)
      Response::fail('Tagging is unavailable for appearances in personal guides');

    if ($this->appearance->protected)
      Response::fail('This appearance cannot be tagged');

    switch ($this->action){
      case 'GET':
        Response::done(['tags' => $this->appearance->getTagsAsText(false)]);
      break;
      case 'PUT':
        $orig_tags = (new Input('orig_tags', 'string', [
          Input::IS_OPTIONAL => true,
          Input::CUSTOM_ERROR_MESSAGES => [
            Input::ERROR_MISSING => 'Initial list of tags is missing',
            Input::ERROR_INVALID => 'Initial list of tags is invalid',
          ],
        ]))->out();
        $tags = (new Input('tags', 'string', [
          Input::CUSTOM_ERROR_MESSAGES => [
            Input::ERROR_MISSING => 'List of tags is missing',
            Input::ERROR_INVALID => 'List of tags is invalid',
          ],
        ]))->out();
        $this->appearance->processTagChanges($orig_tags ?? '', $tags, $this->guide);
        $this->appearance->updateIndex();

        Response::done();
      break;
      default:
        CoreUtils::notAllowed();
    }
  }

  public function sanitizeSvg($params):void {
    if ($this->action !== 'POST')
      CoreUtils::notAllowed();

    if (!Auth::$signed_in)
      Response::fail();

    $this->load_appearance($params, false);
    $this->appearance->enforceManagePermission();

    $svgdata = (new Input('file', 'svg_file', [
      Input::SOURCE => 'FILES',
      Input::IN_RANGE => [null, UploadedFile::SIZES['megabyte']],
      Input::CUSTOM_ERROR_MESSAGES => [
        Input::ERROR_MISSING => 'SVG data is missing',
        Input::ERROR_INVALID => 'SVG data is invalid',
        Input::ERROR_RANGE => 'SVG file size exceeds @max bytes.',
      ],
    ]))->out();

    $warnings = [];

    $sanitized_svg = CoreUtils::sanitizeSvg($svgdata, true, $warnings);
    $tokenized_svg = CGUtils::tokenizeSvg($sanitized_svg, $this->appearance->id);
    $svgel = CGUtils::untokenizeSvg($tokenized_svg, $this->appearance->id, $warnings);

    Response::done(['svgel' => $svgel, 'svgdata' => $svgdata, 'keep_dialog' => true, 'warnings' => $warnings]);
  }

  public function checkColors($params):void {
    if ($this->action !== 'POST')
      CoreUtils::notAllowed();

    if (Permission::insufficient('staff'))
      Response::fail();

    $this->load_appearance($params, false);

    if ($this->appearance->checkSpriteColors())
      Response::success('One or more color issues were found.');

    Response::success("There doesn't seem to be seem to be any color issues.");
  }

  public function guideRelationsApi($params):void {
    if (Permission::insufficient('staff'))
      Response::fail();

    $this->load_appearance($params);

    switch ($this->action){
      case 'GET':
        $linked_ids = [];
        foreach ($this->appearance->related_shows as $s){
          $linked_ids[] = $s->id;
        }

        $query = DB::$instance
          ->orderBy('season', 'DESC')
          ->orderBy('episode', 'DESC')
          ->orderBy('no', 'DESC')
          ->where('generation', null);
        if (isset(CGUtils::GUIDE_GENERATION_MAP[$this->appearance->guide]))
          $query = $query->orWhere('generation', CGUtils::GUIDE_GENERATION_MAP[$this->appearance->guide]);
        /** @var $raw_entries Show[] */
        $raw_entries = $query->get('show');
        $entries = [];
        foreach ($raw_entries as $entry){
          $entries[] = [
            'id' => $entry->id,
            'label' => $entry->formatTitle(),
            'type' => $entry->type,
          ];
        }

        Response::done([
          'groups' => ShowHelper::VALID_TYPES,
          'entries' => $entries,
          'linkedIds' => $linked_ids,
        ]);
      break;
      case 'PUT':
        /** @var $show_ids int[] */
        $show_ids = (new Input('ids', 'int[]', [
          Input::IS_OPTIONAL => true,
          Input::CUSTOM_ERROR_MESSAGES => [
            Input::ERROR_MISSING => 'Missing appearance ID list',
            Input::ERROR_INVALID => 'Appearance ID list is invalid',
          ],
        ]))->out() ?? [];

        $existing_relation_ids = array_map(fn($p) => $p->id, $this->appearance->related_shows);

        // Prevent relations to guide for a different generation
        if (in_array($this->appearance->guide, [CGUtils::GUIDE_FIM, CGUtils::GUIDE_PL], true)) {
          /** @var $can_relate_to_shows Show[] */
          $can_relate_to_shows = DB::$instance
            ->where('type', 'episode')
            ->where('generation', CGUtils::GUIDE_GENERATION_MAP[$this->appearance->guide])
            ->get(Show::$table_name, null, 'id');

          $can_relate_to_ids = array_map(fn(Show $show) => $show->id, $can_relate_to_shows);
          $show_ids = array_intersect($can_relate_to_ids, $show_ids);
        }

        $created_relations = array_diff($show_ids, $existing_relation_ids);
        if (!empty($created_relations)){
          foreach ($created_relations as $show_id)
            ShowAppearance::makeRelation($show_id, $this->appearance->id);
        }

        $removed = array_diff($existing_relation_ids, $show_ids);
        if (!empty($removed))
          DB::$instance->where('appearance_id', $this->appearance->id)->where('show_id', $removed)->delete(ShowAppearance::$table_name);

        $this->appearance->reload();

        Response::done(['section' => $this->appearance->getRelatedShowsHTML()]);
      break;
      default:
        CoreUtils::notAllowed();
    }
  }

  public function autocomplete():void {
    if ($this->action !== 'GET')
      CoreUtils::notAllowed();

    if (empty($_GET['q']) || empty($_GET['guide']))
      CGUtils::autocompleteRespond('[]');

    if (!array_key_exists($_GET['guide'], CGUtils::GUIDE_MAP))
      CoreUtils::badRequest();

    $pagination = new Pagination('', 5);
    /** @var $appearances Appearance[] */
    [$appearances] = CGUtils::searchGuide($pagination, $_GET['guide']);

    if (empty($appearances))
      CGUtils::autocompleteRespond('[]');

    CGUtils::autocompleteRespond(array_map(static function (Appearance $a) {
      return [
        'label' => $a->getBabelLabel(),
        'url' => $a->toURL(),
        'image' => $a->getPreviewImage(),
      ];
    }, $appearances));
  }
}
