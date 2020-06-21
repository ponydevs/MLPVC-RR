<?php

namespace App\Controllers\API;

use App\Controllers\Controller;
use App\CoreUtils;
use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 *   @OA\Info(
 *     title="MLP Vector Club API",
 *     version="0.1",
 *     description="A temporary API which allows programmatic access to some existing features of the [MLPVector.Club](https://mlpvector.club) website. Will be superseded by the [next version](https://api.mlpvector.club) whenever its development is finished.",
 *     @OA\License(name="MIT"),
 *     @OA\Contact(name="David Joseph Guzsik", url="https://seinopsys.dev", email="david@seinopsys.dev"),
 *   ),
 *   @OA\Server(url="/api/v0", description="Unstable API"),
 *   @OA\Tag(name="authentication", description="Endpoints related to getting a user logged in or out, as well as checking logged in status"),
 *   @OA\Tag(name="color guide", description="Endpoints related to the color guide section of the site"),
 *   @OA\Tag(name="appearances", description="Working with entries in the color guide"),
 *   @OA\Tag(name="server info", description="For diagnostic or informational data")
 * )
 * @OA\Schema(
 *   schema="ServerResponse",
 *   required={
 *     "status"
 *   },
 *   additionalProperties=false,
 *   @OA\Property(
 *     property="status",
 *     type="boolean",
 *     description="Indicates whether the request was successful"
 *   ),
 *   @OA\Property(
 *     property="message",
 *     type="string",
 *     description="A translation key pointing to a message that explains the outcome of the request, typically used for errors"
 *   )
 * )
 * @OA\Schema(
 *   schema="PageNumber",
 *   type="integer",
 *   minimum=1,
 *   default=1,
 *   description="A query parameter used for specifying which page is currently being displayed"
 * )
 * @OA\Schema(
 *   schema="File",
 *   type="string",
 *   format="binary",
 * )
 * @OA\Schema(
 *   schema="SVGFile",
 *   type="string",
 *   format="svg",
 * )
 * @OA\Schema(
 *   schema="QueryString",
 *   type="string",
 *   default=""
 * )
 * @OA\Schema(
 *   schema="OneBasedId",
 *   type="integer",
 *   minimum=1,
 *   example=1
 * )
 * @OA\Schema(
 *   schema="ZeroBasedId",
 *   type="integer",
 *   minimum=0,
 *   example=1
 * )
 * @OA\Schema(
 *   schema="PageData",
 *   required={
 *     "pagination"
 *   },
 *   additionalProperties=false,
 *   @OA\Property(
 *     property="pagination",
 *     type="object",
 *     required={
 *       "currentPage",
 *       "totalPages",
 *       "totalItems",
 *       "itemsPerPage"
 *     },
 *     additionalProperties=false,
 *     @OA\Property(
 *       property="currentPage",
 *       type="integer",
 *       minimum=1
 *     ),
 *     @OA\Property(
 *       property="totalPages",
 *       type="integer",
 *       minimum=1
 *     ),
 *     @OA\Property(
 *       property="totalItems",
 *       type="integer",
 *       minimum=0
 *     ),
 *     @OA\Property(
 *       property="itemsPerPage",
 *       type="integer",
 *       minimum=1
 *     ),
 *   ),
 * )
 * @OA\Schema(
 *   schema="PagedServerResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ServerResponse"),
 *     @OA\Schema(ref="#/components/schemas/PageData")
 *   }
 * )
 */

/**
 * This controller handles all new API communication
 */
class APIController extends Controller {
  public function __construct() {
    CoreUtils::removeCSPHeaders();
    parent::__construct();
  }
}
