<?php

namespace App\Models;

use App\DB;
use App\Tags;
use App\Twig;

/**
 * @property int          $id
 * @property int          $uses
 * @property int          $synonym_of
 * @property string       $name
 * @property string       $title
 * @property string       $type
 * @property Tag          $synonym
 * @property Appearance[] $appearances
 * @method static Tag find(...$args)
 * @method static Tag find_by_name(string $name)
 * @method static Tag create(...$args)
 */
class Tag extends NSModel {
  public static $has_many = [
    ['appearances', 'through' => 'tagged'],
    ['tagged', 'class' => 'Tagged'],
  ];

  public static $belongs_to = [
    ['synonym', 'class' => 'Tag', 'foreign_key' => 'synonym_of'],
  ];

  /** For Twig */
  public function getSynonym():Tag {
    return $this->synonym;
  }

  /**
   * @param Appearance $appearance
   *
   * @return bool Indicates whether the passed appearance has this tag
   */
  public function is_used_on(Appearance $appearance):bool {
    return Tagged::is($this, $appearance);
  }

  public function add_to(int $appearance_id):bool {
    return Tagged::make($this->id, $appearance_id)->save();
  }

  public function getHTML(string $guide):string {
    return Twig::$env->render('appearances/_tag.html.twig', [
      'tag' => $this,
      'guide' => $guide,
    ]);
  }

  public function getSearchUrl(string $guide):string {
    return "/cg/$guide?q=".urlencode($this->name);
  }

  public function updateUses() {
    if ($this->synonym_of !== null)
      return;

    return Tags::updateUses($this->id);
  }

  /**
   * @param int[] $tag_ids
   *
   * @return Tag[]
   */
  public static function synonyms_of(array $tag_ids):array {
    if (empty($tag_ids))
      return [];

    return DB::$instance->where('synonym_of', $tag_ids)->get('tags');
  }
}
