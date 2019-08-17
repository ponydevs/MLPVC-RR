<?php

namespace App\Controllers\API;
use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\HTTP;
use App\Models\Appearance;
use App\Models\User;
use App\Pagination;
use App\Response;
use App\UserPrefs;

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
 *
 * @OA\Schema(
 *   schema="ValueOfGitInfo",
 *   type="object",
 *   description="Git revision information under the git key",
 *   required={
 *     "git",
 *   },
 *   additionalProperties=false,
 *   @OA\Property(
 *     property="git",
 *     type="object",
 *     allOf={
 *       @OA\Schema(ref="#/components/schemas/GitInfo"),
 *     }
 *   )
 * )
 *
 * @param array $git
 * @return array
 */
function map_git(array $git) {
	return [
		'commitId' => $git['commit_id'],
		'commitTime' => date('c', strtotime($git['commit_time'])),
	];
}

class AboutController extends APIController {
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
     *         @OA\Schema(ref="#/components/schemas/ValueOfGitInfo")
	 *       }
	 *     )
	 *   )
	 * )
	 */
	function server() {
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		$git = CoreUtils::getFooterGitInfoRaw();

		if (empty($git)) {
			HTTP::statusCode(500);
			Response::fail('GIT_INFO_MISSING');
		}

		Response::done([
			'git' => map_git($git),
		]);
	}
}
