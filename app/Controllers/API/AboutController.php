<?php

namespace App\Controllers\API;

use App\CoreUtils;
use App\HTTP;
use App\Response;

/**
 * AboutController
 */
class AboutController extends APIController {
  /**
   * @OA\Schema(
   *   schema="GitInfo",
   *   type="object",
   *   description="Contains information about the server's current revision",
   *   required={
   *     "commitId",
   *     "commitTime",
   *   },
   *   additionalProperties=false,
   *   @OA\Property(
   *     property="commitId",
   *     type="string",
   *     example="a1bfc6d"
   *   ),
   *   @OA\Property(
   *     property="commitTime",
   *     type="string",
   *     format="date-time"
   *   )
   * )
   * @param array $git
   *
   * @return array
   */
  static function mapGit(array $git) {
    return [
      'commitId' => $git['commit_id'],
      'commitTime' => date('c', strtotime($git['commit_time'])),
    ];
  }

  /**
   * @OA\Get(
   *   path="/about/server",
   *   tags={"server info"},
   *   @OA\Response(
   *     response="200",
   *     description="OK",
   *     @OA\JsonContent(
   *       allOf={
   *         @OA\Schema(ref="#/components/schemas/ServerResponse"),
   *         @OA\Schema(
   *           type="object",
   *           description="Git revision information under the git key",
   *           required={
   *             "git",
   *           },
   *           additionalProperties=false,
   *           @OA\Property(
   *             property="git",
   *             type="object",
   *             ref="#/components/schemas/GitInfo"
   *           )
   *         )
   *       }
   *     )
   *   )
   * )
   */
  function server() {
    if ($this->action !== 'GET')
      CoreUtils::notAllowed();

    $git = CoreUtils::getFooterGitInfoRaw();

    if (empty($git)){
      HTTP::statusCode(500);
      Response::fail('GIT_INFO_MISSING');
    }

    Response::done([
      'git' => self::mapGit($git),
    ]);
  }
}
