<?php

namespace App\Controllers\API;

use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\DB;
use App\HTTP;
use App\Models\Appearance;
use App\Models\Color;
use App\Models\ColorGroup;
use App\Models\Tag;
use App\Pagination;
use App\Permission;
use App\RedisHelper;
use App\Response;
use App\Tags;
use OpenApi\Annotations as OA;
use function count;

/**
 * @OA\Schema(
 *   schema="SlimAppearanceList",
 *   type="object",
 *   description="An array of less resource intensive appearances under the appearances key",
 *   required={
 *     "appearances"
 *   },
 *   additionalProperties=false,
 *   @OA\Property(
 *     property="appearances",
 *     type="array",
 *     @OA\Items(ref="#/components/schemas/SlimAppearance")
 *   )
 * )
 * @OA\Schema(
 *   schema="AppearanceList",
 *   type="object",
 *   description="An array of appearances under the appearances key",
 *   required={
 *     "appearances"
 *   },
 *   additionalProperties=false,
 *   @OA\Property(
 *     property="appearances",
 *     type="array",
 *     @OA\Items(ref="#/components/schemas/Appearance")
 *   )
 * )
 * @OA\Schema(
 *   schema="PreviewsIndicator",
 *   type="boolean",
 *   enum={true},
 *   description="Optional parameter that indicates whether you would like to get preview image data with the request. Typically unneccessary unless you want to display a temporary image while the larger image loads."
 * )
 * @OA\Schema(
 *   schema="Order",
 *   type="number",
 *   description="Used for displaying items in a specific order. The API guarantees that array return values are sorted in ascending order based on this property."
 * )
 */

/**
 * AppearancesController
 */
class AppearancesController extends APIController {
  /**
   * @OA\Schema(
   *   schema="SlimAppearance",
   *   type="object",
   *   description="A less heavy version of the regular Appearance schema",
   *   required={
   *     "id",
   *     "label",
   *     "created_at",
   *     "notes",
   *     "tags",
   *     "sprite",
   *     "hasCutieMarks"
   *   },
   *   additionalProperties=false,
   *   @OA\Property(
   *     property="id",
   *     ref="#/components/schemas/ZeroBasedId"
   *   ),
   *   @OA\Property(
   *     property="label",
   *     type="string",
   *     description="The name of the appearance",
   *     example="Twinkle Sprinkle"
   *   ),
   *   @OA\Property(
   *     property="created_at",
   *     type="string",
   *     format="date-time"
   *   ),
   *   @OA\Property(
   *     property="notes",
   *     type="string",
   *     format="html",
   *     nullable=true,
   *     example="Far legs use darker colors. Based on <strong>S2E21</strong>."
   *   ),
   *   @OA\Property(
   *     property="tags",
   *     type="array",
   *     minItems=0,
   *     @OA\Items(ref="#/components/schemas/SlimGuideTag")
   *   ),
   *   @OA\Property(
   *     property="sprite",
   *     nullable=true,
   *     ref="#/components/schemas/Sprite",
   *     description="The sprite that belongs to this appearance, or null if there is none"
   *   ),
   *   @OA\Property(
   *     property="hasCutieMarks",
   *     type="boolean",
   *     description="Indicates whether there are any cutie marks tied to this appearance"
   *   )
   * )
   * @OA\Schema(
   *   schema="ListOfColorGroups",
   *   type="object",
   *   description="Array of color groups under the `colorGroups` key",
   *   required={
   *     "colorGroups"
   *   },
   *   additionalProperties=false,
   *   @OA\Property(
   *     property="colorGroups",
   *     type="array",
   *     minItems=0,
   *     @OA\Items(ref="#/components/schemas/ColorGroup"),
   *    description="Array of color groups belonging to an appearance (may be an empty array)."
   *   )
   * )
   * @OA\Schema(
   *   schema="Appearance",
   *   type="object",
   *   description="Represents an entry in the color guide",
   *   additionalProperties=false,
   *   allOf={
   *     @OA\Schema(ref="#/components/schemas/SlimAppearance"),
   *     @OA\Schema(ref="#/components/schemas/ListOfColorGroups")
   *   }
   * )
   * @param Appearance $a
   * @param bool       $with_previews
   * @param bool       $compact
   *
   * @return array
   */
  static function mapAppearance(Appearance $a, bool $with_previews, bool $compact = false) {
    $tags = array_map(function (Tag $t) {
      return self::mapTag($t);
    }, Tags::getFor($a->id, null, true, true));

    $appearance = [
      'id' => $a->id,
      'label' => $a->label,
      'created_at' => gmdate('c', $a->created_at->getTimestamp()),
      'notes' => $a->notes_rend,
      'tags' => $tags,
      'sprite' => self::mapSprite($a, $with_previews),
      'hasCutieMarks' => count($a->cutiemarks) !== 0,
    ];
    if (!$compact)
      $appearance['colorGroups'] = self::_getColorGroups($a);

    return $appearance;
  }

  private static function _getColorGroups(Appearance $a):array {
    $colors = CGUtils::getColorsForEach($a->color_groups);
    $color_groups = array_map(function (ColorGroup $cg) use ($colors) {
      return self::mapColorGroup($cg, $colors);
    }, $a->color_groups);

    return $color_groups;
  }

  /**
   * @OA\Schema(
   *   schema="SlimGuideTag",
   *   type="object",
   *   @OA\Property(
   *     property="id",
   *     ref="#/components/schemas/OneBasedId"
   *   ),
   *   @OA\Property(
   *     property="name",
   *     type="string",
   *     minLength=1,
   *     maxLength=255,
   *     example="mane six",
   *     description="Tag name (all lowercase)"
   *   )
   * )
   * @param Tag $t
   *
   * @return array
   */
  static function mapTag(Tag $t) {
    return [
      'id' => $t->id,
      'name' => $t->name,
      'type' => $t->type,
    ];
  }

  /**
   * @OA\Schema(
   *   schema="Sprite",
   *   type="object",
   *   description="Data related to an appearance's sprite file. The actual file is available from a different endpoint.",
   *   required={
   *     "hash",
   *   },
   *   additionalProperties=false,
   *   @OA\Property(
   *     property="hash",
   *     description="MD5 hash of the current sprite image",
   *     ref="#/components/schemas/SpriteHash"
   *   ),
   *   @OA\Property(
   *     property="preview",
   *     type="string",
   *     format="data-uri",
   *     example="data:image/png;base64,<image data>",
   *     description="Data URI for a small preview image with matching proportions to the actual image, suitable for displaying as a preview while the full image loads. May not be sent based on the request parameters."
   *   ),
   * )
   * @param Appearance $a
   * @param bool       $with_preview
   *
   * @return array|null
   */
  static function mapSprite(Appearance $a, $with_preview = false):?array {
    if (!$a->hasSprite())
      return null;

    $value = ['hash' => $a->sprite_hash];
    if ($with_preview)
      $value['preview'] = CoreUtils::bin2dataUri(CGUtils::generateSpritePreview($a->id, $a->owner_id !== null), 'image/png');

    return $value;
  }

  /**
   * @OA\Schema(
   *   schema="ColorGroup",
   *   type="object",
   *   description="Groups a list of colors",
   *   required={
   *     "id",
   *     "label",
   *     "order",
   *     "colors"
   *   },
   *   additionalProperties=false,
   *   @OA\Property(
   *     property="id",
   *     ref="#/components/schemas/OneBasedId"
   *   ),
   *   @OA\Property(
   *     property="label",
   *     type="string",
   *     description="The name of the color group",
   *     example="Coat"
   *   ),
   *   @OA\Property(
   *     property="order",
   *     ref="#/components/schemas/Order"
   *   ),
   *   @OA\Property(
   *     property="colors",
   *     type="array",
   *     minItems=1,
   *     @OA\Items(ref="#/components/schemas/Color"),
   *     description="The list of colors inside this group"
   *   )
   * )
   * @param ColorGroup $cg
   * @param array      $color_map
   *
   * @return array
   */
  static function mapColorGroup(ColorGroup $cg, array $color_map) {
    $colors = array_map(function (Color $c) {
      return self::mapColor($c);
    }, $color_map[$cg->id]);

    return [
      'id' => $cg->id,
      'label' => $cg->label,
      'order' => $cg->order,
      'colors' => $colors,
    ];
  }

  /**
   * @OA\Schema(
   *   schema="Color",
   *   type="object",
   *   description="A color entry. Colors may link to other colors, in which case `linkedTo` will be set to the link target, but `hex` will always point to the value that should be displayed.", required={
   *     "id",
   *     "label",
   *     "order",
   *     "hex"
   *   },
   *   additionalProperties=false,
   *   @OA\Property(
   *     property="id",
   *     ref="#/components/schemas/OneBasedId"
   *   ),
   *   @OA\Property(
   *     property="label",
   *     type="string",
   *     description="The name of the color",
   *     example="Fill"
   *   ),
   *   @OA\Property(
   *     property="order",
   *     ref="#/components/schemas/Order"
   *   ),
   *   @OA\Property(
   *     property="hex",
   *     type="string",
   *     format="#RRGGBB",
   *     description="The color value in uppercase hexadecimal form, including a # prefix",
   *     example="#6181B6"
   *   ),
   *   @OA\Property(
   *     property="linkedTo",
   *     deprecated=true,
   *     description="This field used to indicate if this color was linked to another color, however, this feature was removed and this field now only ever returns null",
   *     type="object",
   *     nullable=true,
   *     ref="#/components/schemas/Color",
   *     example=null
   *   ),
   * )
   * @param Color $c
   *
   * @return array
   */
  static function mapColor(Color $c) {
    return [
      'id' => $c->id,
      'label' => $c->label,
      'order' => $c->order,
      'hex' => $c->hex,
      'linkedTo' => null,
    ];
  }

  /**
   * @OA\Schema(
   *   schema="GuideName",
   *   type="string",
   *   enum={"pony", "eqg"},
   *   default="pony"
   * )
   * @OA\Schema(
   *   schema="GuidePageSize",
   *   type="integer",
   *   minimum=7,
   *   maximum=20,
   *   default=7
   * )
   * @OA\Get(
   *   path="/appearances",
   *   description="Allows querying the full library of public appearances (forced pagination)",
   *   tags={"color guide", "appearances"},
   *   @OA\Parameter(
   *     in="query",
   *     name="guide",
   *     required=true,
   *     @OA\Schema(ref="#/components/schemas/GuideName"),
   *     description="Determines the guide to search in"
   *   ),
   *   @OA\Parameter(
   *     in="query",
   *     name="page",
   *     required=false,
   *     @OA\Schema(ref="#/components/schemas/PageNumber"),
   *     description="Which page of results to return"
   *   ),
   *   @OA\Parameter(
   *     in="query",
   *     name="size",
   *     required=false,
   *     @OA\Schema(ref="#/components/schemas/GuidePageSize"),
   *     description="The number of results to return per page"
   *   ),
   *   @OA\Parameter(
   *     in="query",
   *     name="q",
   *     required=false,
   *     @OA\Schema(ref="#/components/schemas/QueryString"),
   *     description="Search query"
   *   ),
   *   @OA\Parameter(
   *     in="query",
   *     name="previews",
   *     required=false,
   *     @OA\Schema(ref="#/components/schemas/PreviewsIndicator")
   *   ),
   *   @OA\Response(
   *     response="200",
   *     description="OK",
   *     @OA\JsonContent(
   *       allOf={
   *         @OA\Schema(ref="#/components/schemas/PagedServerResponse"),
   *         @OA\Schema(ref="#/components/schemas/AppearanceList")
   *       }
   *     )
   *   )
   * )
   */
  function queryPublic() {
    if ($this->action !== 'GET')
      CoreUtils::notAllowed();

    $elastic_avail = CGUtils::isElasticAvailable();
    if (!$elastic_avail){
      HTTP::statusCode(503);
      Response::fail('ELASTIC_DOWN');
    }
    if (isset($_GET['size']) && is_numeric($_GET['size']))
      $appearances_per_page = CoreUtils::rangeLimit((int)$_GET['size'], 7, 20);
    else $appearances_per_page = 7;
    $pagination = new Pagination('', $appearances_per_page);
    $searching = !empty($_GET['q']) && $_GET['q'] !== '';
    $guide_name = $_GET['guide'] ?? null;
    $with_previews = ($_GET['previews'] ?? null) === 'true';
    if (!array_key_exists($guide_name, CGUtils::GUIDE_MAP)){
      HTTP::statusCode(400);
      Response::fail('COLOR_GUIDE.INVALID_GUIDE_NAME');
    }
    [$appearances] = CGUtils::searchGuide($pagination, $guide_name, $searching);

    $results = array_map(function (Appearance $a) use ($with_previews) {
      return self::mapAppearance($a, $with_previews);
    }, $appearances);
    Response::done([
      'appearances' => $results,
      'pagination' => CoreUtils::paginationForApi($pagination),
    ]);
  }

  /**
   * @OA\Schema(
   *   schema="CacheIndicator",
   *   required={
   *     "cachedOn",
   *     "cachedFor"
   *   },
   *   additionalProperties=false,
   *   @OA\Property(
   *     property="cachedOn",
   *     type="string",
   *     format="date-time",
   *     description="Indicates when a cached resource was last updated with fresh data"
   *   ),
   *   @OA\Property(
   *     property="cachedFor",
   *     type="number",
   *     minimum=1,
   *     example="3600",
   *     description="How long the data is cached for (in seconds)"
   *   )
   * )
   * @OA\Get(
   *   path="/appearances/all",
   *   description="Get a list of every appearance in the database (without color group data)",
   *   tags={"color guide", "appearances"},
   *   @OA\Parameter(
   *     in="query",
   *     name="guide",
   *     required=true,
   *     @OA\Schema(ref="#/components/schemas/GuideName"),
   *     description="Determines the guide to search in"
   *   ),
   *   @OA\Parameter(
   *     in="query",
   *     name="previews",
   *     required=false,
   *     @OA\Schema(ref="#/components/schemas/PreviewsIndicator")
   *   ),
   *   @OA\Response(
   *     response="200",
   *     description="OK",
   *     @OA\JsonContent(
   *       allOf={
   *         @OA\Schema(ref="#/components/schemas/ServerResponse"),
   *         @OA\Schema(ref="#/components/schemas/SlimAppearanceList"),
   *         @OA\Schema(ref="#/components/schemas/CacheIndicator")
   *       }
   *     )
   *   )
   * )
   */
  function queryAll() {
    if ($this->action !== 'GET')
      CoreUtils::notAllowed();

    $guide_name = $_GET['guide'] ?? null;
    $with_previews = ($_GET['previews'] ?? null) === 'true';
    if (!isset(CGUtils::GUIDE_MAP[$guide_name])){
      HTTP::statusCode(400);
      Response::fail('COLOR_GUIDE.INVALID_GUIDE_NAME');
    }

    $cache_time = 600;
    $cache_key = CoreUtils::generateCacheKey(1, 'all appearances', $guide_name, $with_previews);
    $cached_data = RedisHelper::get($cache_key);
    if ($cached_data !== null)
      Response::doneCached($cached_data);

    /** @var $appearances Appearance[] */
    $appearances = DB::$instance->where('guide', $guide_name)->get(Appearance::$table_name);

    $results = array_map(function (Appearance $a) use ($with_previews) {
      return self::mapAppearance($a, $with_previews, true);
    }, $appearances);
    Response::done([
      'appearances' => $results,
    ], $cache_key, $cache_time);
  }

  private static function _resolveAppearance(array $params):Appearance {
    $id = (int)$params['id'];
    $appearance = Appearance::find($id);
    if (empty($appearance)){
      HTTP::statusCode(404);
      Response::fail('COLOR_GUIDE.APPEARANCE_NOT_FOUND');
    }
    return $appearance;
  }

  private static function _handlePrivateAppearanceCheck(Appearance $appearance):void {
    if ($appearance->private && Permission::insufficient('staff')){
      if (Auth::$signed_in && $appearance->owner_id === Auth::$user->id)
        return;

      // Usage with token is postponed for the rewrite

      HTTP::statusCode(403);
      Response::fail('COLOR_GUIDE.APPEARANCE_PRIVATE');
    }
  }

  /**
   * @OA\Get(
   *   path="/appearances/{id}/color-groups",
   *   description="Get all color groups associated with an appearance",
   *   tags={"color guide", "appearances"},
   *   @OA\Parameter(
   *     in="path",
   *     name="id",
   *     required=true,
   *     @OA\Schema(ref="#/components/schemas/ZeroBasedId")
   *   ),
   *   @OA\Response(
   *     response="200",
   *     description="OK",
   *     @OA\JsonContent(
   *       allOf={
   *         @OA\Schema(ref="#/components/schemas/ServerResponse"),
   *         @OA\Schema(ref="#/components/schemas/ListOfColorGroups")
   *       }
   *     )
   *   )
   * )
   * @param array $params
   */
  function getColorGroups(array $params) {
    if ($this->action !== 'GET')
      CoreUtils::notAllowed();

    $appearance = self::_resolveAppearance($params);

    self::_handlePrivateAppearanceCheck($appearance);

    Response::done(['colorGroups' => self::_getColorGroups($appearance)]);
  }

  /**
   * @OA\Schema(
   *   schema="SpriteSize",
   *   type="integer",
   *   enum={300, 600},
   *   default=300
   * )
   * @OA\Schema(
   *   schema="SpriteHash",
   *   type="string",
   *   format="md5",
   *   minLength=32,
   *   maxLength=32
   * )
   * @OA\Schema(
   *   schema="AppearanceToken",
   *   type="string",
   *   format="uuid"
   * )
   * @OA\Get(
   *   path="/appearances/{id}/sprite",
   *   description="Fetch the sprite file associated with the appearance",
   *   tags={"color guide", "appearances"},
   *   @OA\Parameter(
   *     in="path",
   *     name="id",
   *     required=true,
   *     @OA\Schema(ref="#/components/schemas/ZeroBasedId")
   *   ),
   *   @OA\Parameter(
   *     in="query",
   *     name="size",
   *     @OA\Schema(ref="#/components/schemas/SpriteSize")
   *   ),
   *   @OA\Parameter(
   *     in="query",
   *     name="token",
   *     @OA\Schema(ref="#/components/schemas/AppearanceToken")
   *   ),
   *   @OA\Parameter(
   *     in="query",
   *     name="hash",
   *     description="Used for cache busting. The latest value is provided by the appearance resource.",
   *     @OA\Schema(ref="#/components/schemas/SpriteHash")
   *   ),
   *   @OA\Response(
   *     response="200",
   *     description="The sprite image at the specified size",
   *     @OA\MediaType(
   *       mediaType="image/png",
   *       @OA\Schema(ref="#/components/schemas/File")
   *     )
   *   ),
   *   @OA\Response(
   *     response="404",
   *     description="Sprite image missing",
   *     @OA\JsonContent(
   *       allOf={
   *         @OA\Schema(ref="#/components/schemas/ServerResponse")
   *       }
   *     )
   *   ),
   *   @OA\Response(
   *     response="403",
   *     description="You don't have permission to access this resource",
   *     @OA\JsonContent(
   *       allOf={
   *         @OA\Schema(ref="#/components/schemas/ServerResponse")
   *       }
   *     )
   *   )
   * )
   * @param array $params
   */
  function sprite(array $params) {
    if ($this->action !== 'GET')
      CoreUtils::notAllowed();

    $appearance = self::_resolveAppearance($params);

    self::_handlePrivateAppearanceCheck($appearance);

    CGUtils::renderSpritePNG($appearance, $_GET['size'] ?? null);
  }

  /**
   * @OA\Get(
   *   path="/appearances/{id}/preview",
   *   description="Fetch the preview file associated with the appearance",
   *   tags={"color guide", "appearances"},
   *   @OA\Parameter(
   *     in="path",
   *     name="id",
   *     required=true,
   *     @OA\Schema(ref="#/components/schemas/ZeroBasedId")
   *   ),
   *   @OA\Parameter(
   *     in="query",
   *     name="token",
   *     @OA\Schema(ref="#/components/schemas/AppearanceToken")
   *   ),
   *   @OA\Response(
   *     response="200",
   *     description="The appearance preview image",
   *     @OA\MediaType(
   *       mediaType="image/svg+xml",
   *       @OA\Schema(ref="#/components/schemas/SVGFile")
   *     )
   *   ),
   *   @OA\Response(
   *     response="404",
   *     description="Appearance missing",
   *     @OA\JsonContent(
   *       allOf={
   *         @OA\Schema(ref="#/components/schemas/ServerResponse")
   *       }
   *     )
   *   ),
   *   @OA\Response(
   *     response="403",
   *     description="You don't have permission to access this resource",
   *     @OA\JsonContent(
   *       allOf={
   *         @OA\Schema(ref="#/components/schemas/ServerResponse")
   *       }
   *     )
   *   )
   * )
   * @param array $params
   */
  function preview(array $params) {
    if ($this->action !== 'GET')
      CoreUtils::notAllowed();

    $appearance = self::_resolveAppearance($params);

    self::_handlePrivateAppearanceCheck($appearance);

    CGUtils::renderPreviewSVG($appearance);
  }
}
