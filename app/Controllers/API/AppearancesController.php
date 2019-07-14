<?php

namespace App\Controllers\API;
use App\CGUtils;
use App\CoreUtils;
use App\HTTP;
use App\Models\Appearance;
use App\Pagination;
use App\Response;
use App\UserPrefs;

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
 *     type="integer",
 *     example=1
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
 *   )
 * )
 *
 * @OA\Schema(
 *   schema="Sprite",
 *   type="object",
 *   description="Data related to an appearance's sprite file. The actual file is available from a different endpoint.",
 *   required={
 *     "hash",
 *     "preview",
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
 *     description="Data URI for a small preview image with matching proportions to the actual image, suitable for displaying as a preview while the full image loads"
 *   ),
 * )
 *
 * @OA\Schema(
 *   schema="ArrayOfAppearances",
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
 * @param Appearance $a
 *
 * @return array
 */
function map_appearance(Appearance $a) {
	return [
		'id' => $a->id,
		'label' => $a->label,
		'added' => gmdate('c', $a->added->getTimestamp()),
		'notes' => $a->notes_rend,
		'sprite' => $a->getSpriteAPI(),
		'hasCutieMarks' => \count($a->cutiemarks) !== 0
	];
}

class AppearancesController extends APIController {
	/**
	 * @OA\Schema(
	 *   schema="GuideName",
     *   type="string",
	 *   enum={"pony", "eqg"},
	 *   default="pony"
	 * )
	 *
	 * @OA\Get(
	 *   path="/api/v1/appearances",
	 *   description="Allows querying the full library of public appearances",
	 *   @OA\Parameter(
	 *     in="query",
	 *     name="guide",
     *     @OA\Schema(ref="#/components/schemas/GuideName"),
     *     description="Determines the guide to search in"
	 *   ),
	 *   @OA\Parameter(
	 *     in="query",
	 *     name="page",
     *     @OA\Schema(ref="#/components/schemas/PageNumber"),
     *     description="Which page of results to return"
	 *   ),
	 *   @OA\Parameter(
	 *     in="query",
	 *     name="q",
     *     schema={
     *       "type"="string",
	 *       "default"=""
	 *     },
     *     description="Search query"
	 *   ),
	 *   @OA\Response(
	 *     response="200",
	 *     description="OK",
	 *     @OA\JsonContent(
	 *       allOf={
     *         @OA\Schema(ref="#/components/schemas/PagedServerResponse"),
     *         @OA\Schema(ref="#/components/schemas/ArrayOfAppearances")
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
		$appearances_per_page = UserPrefs::get('cg_itemsperpage');
		$pagination = new Pagination('', $appearances_per_page);
		$searching = !empty($_GET['q']) && $_GET['q'] !== '';
		$guide_name = $_GET['guide'] ?? null;
		if (!isset(CGUtils::GUIDE_MAP[$guide_name])) {
			HTTP::statusCode(400);
			Response::fail('COLOR_GUIDE.INVALID_GUIDE_NAME');
		}
		$eqg = $guide_name === 'eqg';
		[$appearances] = CGUtils::searchGuide($pagination, $eqg, $searching);

		$results = array_map(function(Appearance $a) {
			return map_appearance($a);
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
	 *   default="300"
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
	 *   path="/api/v1/appearances/{id}/sprite",
	 *   @OA\Parameter(
	 *     in="path",
	 *     name="id",
	 *     required=true,
     *     schema={
     *       "type"="integer",
	 *       "minimum"=1
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
