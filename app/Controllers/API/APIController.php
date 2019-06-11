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
 *   @OA\Property(
 *     property="status",
 *     type="boolean",
 *     description="Indicates whether the request was successful"
 *   ),
 *   @OA\Property(
 *     property="message",
 *     type="string",
 *     description="A message explaining the outcome of the request, typically used for errors"
 *   ),
 * )
 *
 * @OA\Schema(
 *   schema="PageData",
 *   required={
 *     "pagination"
 *   },
 *   @OA\Property(
 *     property="pagination",
 *     type="object",
 *     required={
 *       "currentPage",
 *       "totalPages",
 *       "totalItems"
 *     },
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
