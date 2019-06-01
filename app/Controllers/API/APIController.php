<?php

namespace App\Controllers\API;
use App\Controllers\Controller;

/**
 * @OA\Info(title="MLP Vector Club API", version="1")
 *
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
 *     description="A translation key pointing to a messsage that explains the outcome of the request, typically used for errors"
 *   ),
 * )
 *
 *
 * @OA\Schema(
 *   schema="PageNumber",
 *   type="integer",
 *   minimum=1,
 *   default=1,
 *   description="A query parameter used for specifying which page is currently being displayed"
 * )
 *
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
 *
 * @OA\Schema(
 *   schema="PagedServerResponse",
 *   allOf={
 *     @OA\Schema(ref="#/components/schemas/ServerResponse"),
 *     @OA\Schema(ref="#/components/schemas/PageData")
 *   }
 * )
 */
class APIController extends Controller {
}
