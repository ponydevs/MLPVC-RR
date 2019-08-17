<?php

namespace App\Controllers\API;
use App\CGUtils;
use App\CoreUtils;
use App\HTTP;
use App\Models\Appearance;
use App\Models\Color;
use App\Models\ColorGroup;
use App\Pagination;
use App\Response;
use OpenApi\Annotations as OA;

/**
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
 *
 * @OA\Schema(
 *     schema="Order",
 *     type="number",
 *     description="Used for displaying items in a specific order. The API guarantees that array return values are sorted in ascending order based on this property."
 * )
 */

class AppearancesController extends APIController {
	/**
	 * @OA\Schema(
	 *   schema="Appearance",
	 *   type="object",
	 *   description="Represents an entry in the color guide",
	 *   required={
	 *     "id",
	 *     "label",
	 *     "added",
	 *     "notes",
	 *     "sprite"
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
	 *     property="added",
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
	 *     property="sprite",
	 *     nullable=true,
	 *     ref="#/components/schemas/Sprite",
	 *     description="The sprite that belongs to this appearance, or null if there is none"
	 *   ),
	 *   @OA\Property(
	 *     property="hasCutieMarks",
	 *     type="boolean",
	 *     description="Indicates whether there are any cutie marks tied to this appearance"
	 *   ),
	 *   @OA\Property(
	 *     property="colorGroups",
	 *     type="array",
	 *     minItems=0,
	 *     @OA\Items(ref="#/components/schemas/ColorGroup"),
	 *     description="Array of color groups belogning to this appearance (may be an empty array)."
	 *   )
	 * )
	 * @param Appearance $a
	 * @param bool       $with_previews
	 *
	 * @return array
	 */
	static function mapAppearance(Appearance $a, bool $with_previews) {
		$colors = CGUtils::getColorsForEach($a->color_groups);
		$color_groups = array_map(function(ColorGroup $cg) use ($colors) {
			return self::mapColorGroup($cg, $colors);
		}, $a->color_groups);
		return [
			'id' => $a->id,
			'label' => $a->label,
			'added' => gmdate('c', $a->added->getTimestamp()),
			'notes' => $a->notes_rend,
			'sprite' => self::mapSprite($a, $with_previews),
			'hasCutieMarks' => \count($a->cutiemarks) !== 0,
			'colorGroups' => $color_groups,
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
	 *
	 * @param Appearance $a
	 * @param bool       $with_preview
	 *
	 * @return array|null
	 */
	static function mapSprite(Appearance $a, $with_preview = false):?array {
		if (!$a->hasSprite())
			return null;

		$value = [ 'hash' => $a->sprite_hash ];
		if ($with_preview)
			$value['preview'] = CoreUtils::bin2dataUri(CGUtils::generateSpritePreview($a->id), 'image/png');

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
	 *
	 * @param ColorGroup $cg
	 * @param array      $color_map
	 *
	 * @return array
	 */
	static function mapColorGroup(ColorGroup $cg, array $color_map) {
		$colors = array_map(function(Color $c) {
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
	 *   description="A color entry. Colors may link to other colors, in which case `linkedTo` will be set to the link target, but `hex` will always point to the value that should be displayed.",
	 *   required={
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
	 * )
	 *
	 * @param Color $c
	 *
	 * @return array
	 */
	static function mapColor(Color $c) {
		$is_linked = $c->linked_to !== null;
		return [
			'id' => $c->id,
			'label' => $c->label,
			'order' => $c->order,
			'hex' => $is_linked ? $c->linked->hex : $c->hex,
			'linkedTo' => $is_linked ? self::mapColor($c->linked) : null,
		];
	}

	/**
	 * @OA\Schema(
	 *   schema="GuideName",
     *   type="string",
	 *   enum={"pony", "eqg"},
	 *   default="pony"
	 * )
     *
	 * @OA\Schema(
	 *   schema="GuidePageSize",
     *   type="integer",
	 *   minimum=7,
     *   maximum=20,
	 *   default=7
	 * )
	 *
	 * @OA\Get(
	 *   path="/appearances",
	 *   description="Allows querying the full library of public appearances",
	 *   tags={"color guide", "appearances"},
	 *   @OA\Parameter(
	 *     in="query",
	 *     name="guide",
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
     *     @OA\Schema(ref="#/components/schemas/BooleanTrue"),
     *     description="Optional parameter that indicates whether you would like to get preview image data with the request. Typically unneccessary unless you want to display a temporary image on the fronend while the larger image loads."
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
		    $appearances_per_page = CoreUtils::rangeLimit(\intval($_GET['size'], 10), 7, 20);
		else $appearances_per_page = 7;
		$pagination = new Pagination('', $appearances_per_page);
		$searching = !empty($_GET['q']) && $_GET['q'] !== '';
		$guide_name = $_GET['guide'] ?? null;
		$with_previews = ($_GET['previews'] ?? null) === 'true';
		if (!isset(CGUtils::GUIDE_MAP[$guide_name])) {
			HTTP::statusCode(400);
			Response::fail('COLOR_GUIDE.INVALID_GUIDE_NAME');
		}
		$eqg = $guide_name === 'eqg';
		[$appearances] = CGUtils::searchGuide($pagination, $eqg, $searching);

		$results = array_map(function(Appearance $a) use ($with_previews) {
			return self::mapAppearance($a, $with_previews);
		}, $appearances);
		Response::done([
			'appearances' => $results,
			'pagination' => CoreUtils::paginationForApi($pagination),
		]);
	}

	/**
	 * @OA\Schema(
	 *   schema="SpriteSize",
     *   type="integer",
	 *   enum={300, 600},
	 *   default=300
	 * )
	 *
	 * @OA\Schema(
	 *   schema="SpriteHash",
     *   type="string",
	 *   format="md5",
	 *   minLength=32,
	 *   maxLength=32
	 * )
	 *
	 * @OA\Schema(
	 *   schema="AppearanceToken",
     *   type="string",
	 *   format="uuid"
	 * )
	 *
	 * @OA\Get(
	 *   path="/appearances/{id}/sprite",
	 *   description="Fetch the sprite file associated with the appearance",
	 *   tags={"color guide", "appearances"},
	 *   @OA\Parameter(
	 *     in="path",
	 *     name="id",
	 *     required=true,
     *     schema={
     *       "type"="integer",
	 *       "minimum"=0,
	 *       "example"=3
	 *     }
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
	 *   )
	 * )
	 *
	 * @param array $params
	 */
	function sprite(array $params) {
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		$id = \intval($params['id'], 10);
		$appearance = Appearance::find($id);
		if (empty($appearance)){
			HTTP::statusCode(404);
			Response::fail('COLOR_GUIDE.APPEARANCE_NOT_FOUND');
		}

		if ($appearance->private) {
			// TODO check for token param and allow if correct
			HTTP::statusCode(403);
			Response::fail('COLOR_GUIDE.APPEARANCE_PRIVATE');
		}

		CGUtils::renderSpritePNG($this->path, $appearance->id, $_GET['size'] ?? null);
	}
}
