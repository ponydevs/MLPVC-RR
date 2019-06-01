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
 *     "notes"
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
 *   )
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
	function getAll() {
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
		$eqg = ($_GET['guide'] ?? null) === 'eqg';
		[$appearances] = CGUtils::searchGuide($pagination, $eqg, $searching);

		$results = array_map(function(Appearance $a) {
			return map_appearance($a);
		}, $appearances);
		Response::done([
			'appearances' => $results,
			'pagination' => CoreUtils::paginationForApi($pagination),
		]);
	}
}
