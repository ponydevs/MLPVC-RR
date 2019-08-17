<?php

namespace App\Models;

use App\CoreUtils;
use App\Permission;
use App\Twig;
use App\UserPrefs;
use HtmlGenerator\HtmlTag;
use SeinopSys\RGBAColor;

/**
 * @inheritdoc
 * @property int        $id
 * @property int        $appearance_id
 * @property string     $label
 * @property Appearance $appearance
 * @property Color[]    $colors
 * @method static ColorGroup|ColorGroup[] find(...$args)
 * @method static ColorGroup[] find_all_by_appearance_id(int $appearance_id)
 */
class ColorGroup extends OrderedModel {
  public static $table_name = 'color_groups';

  public static $belongs_to = [
    ['appearance'],
  ];

  public static $has_many = [
    ['colors', 'foreign_key' => 'group_id', 'order' => '"order" asc'],
  ];

  /** @inheritdoc */
  public function assign_order() {
    if ($this->order !== null)
      return;

    $LastGroup = self::find('first', [
      'conditions' => ['appearance_id' => $this->appearance_id],
      'order' => '"order" desc',
    ]);
    $this->order = !empty($LastGroup->order) ? $LastGroup->order + 1 : 1;
  }

  /**
   * Make sure appearance_id is filtered somehow in the $opts array
   *
   * @inheritdoc
   */
  public static function in_order(array $opts = []) {
    self::addOrderOption($opts);

    return self::find('all', $opts);
  }

  /**
   * Remove the colors in this group without removing the group itself
   */
  public function wipeColors() {
    Color::delete_all([
      'conditions' => ['group_id' => $this->id],
    ]);
  }

  /** @return Color[] */
  public function getColors() {
    return $this->colors;
  }

  /**
   * Get HTML for a color group
   *
   * @param Color[][] $AllColors
   * @param bool      $compact
   *
   * @return string
   */
  public function getHTML($AllColors = null, bool $compact = true):string {
    if ($compact)
      return Twig::$env->render('appearances/_color_group_compact.html.twig', ['color_group' => $this]);

    $label = CoreUtils::escapeHTML($this->label).($compact ? ': ' : '');
    $HTML =
      "\n<span class='cat'>$label".
      (!$compact && Permission::sufficient('staff')
        ? '<span class="admin"><button class="blue typcn typcn-pencil edit-cg"><span>Edit</span></button><button class="red typcn typcn-trash delete-cg"><span>Delete</span></button></span>'
        : '').
      "</span>\n";
    $Colors = empty($AllColors) ? $this->colors : ($AllColors[$this->id] ?? null);
    if (!empty($Colors)){
      $extraInfo = !UserPrefs::get('cg_hideclrinfo');
      foreach ($Colors as $i => $c){
        $span = HtmlTag::createElement('span');
        $title = CoreUtils::aposEncode($c->label);
        $color = '';
        if (!empty($c->hex)){
          $color = $c->hex;
          $span->text($color);
          $span->set('style', "background-color:$color");
          $span->set('class', 'valid-color');
        }

        if (!$compact){
          $label = CoreUtils::escapeHTML($c->label);
          $append = "<div class='color-line".(!$extraInfo || empty($color) ? ' no-detail' : '')."'>$span <span><span class='label'>$label";
          if ($extraInfo && !empty($color)){
            /** @noinspection NullPointerExceptionInspection */
            $rgb = RGBAColor::parse($color)->toRGB();
            $append .= "</span><span class='hidden'>: </span><span class='ext'>$color &bull; $rgb";
          }
          $append .= '</span></div>';
        }
        else $append = (string)$span->set('title', $title);
        $HTML .= $append."\n";
      }
    }

    return "<li id='cg-{$this->id}'>$HTML</li>\n";
  }
}
