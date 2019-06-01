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
 *   schema="User",
 *   type="object",
 *   description="Represents an authenticated user",
 *   required={
 *     "id",
 *     "name",
 *     "role",
 *     "avatar_url"
 *   },
 *   additionalProperties=false,
 *   @OA\Property(
 *     property="id",
 *     type="string",
 *     format="uuid"
 *   ),
 *   @OA\Property(
 *     property="label",
 *     type="string",
 *     example="example"
 *   ),
 *   @OA\Property(
 *     property="role",
 *     @OA\Schema(ref="#/components/schemas/UserRole"),
 *   ),
 *   @OA\Property(
 *     property="avatar_url",
 *     type="string",
 *     format="uri",
 *     example="https://a.deviantart.net/avatars/e/x/example.png"
 *   )
 * )
 *
 * @OA\Schema(
 *   schema="UserRole",
 *   type="string",
 *   description="List of roles a user can have",
 *   enum={"guest","user","member","assistant","staff","admin","developer"}
 * )
 *
 * @OA\Schema(
 *   schema="ValueOfUser",
 *   type="object",
 *   description="A user's data under the user key",
 *   required={
 *     "user"
 *   },
 *   additionalProperties=false,
 *   @OA\Property(
 *     property="user",
 *     type="object",
 *     @OA\Items(ref="#/components/schemas/User")
 *   )
 * )
 *
 * @param User $u
 *
 * @return array
 */
function map_user(User $u) {
	return [
		'id' => $u->id,
		'name' => $u->name,
		'role' => $u->role,
		'avatar_url' => $u->avatar_url,
	];
}

class UsersController extends APIController {
	/**
	 * @OA\Get(
	 *   path="/api/v1/users/me",
	 *   @OA\Response(
	 *     response="200",
	 *     description="OK",
	 *     @OA\JsonContent(
	 *       allOf={
     *         @OA\Schema(ref="#/components/schemas/ServerResponse"),
     *         @OA\Schema(ref="#/components/schemas/ValueOfUser")
	 *       }
	 *     )
	 *   )
	 * )
	 */
	function getMe() {
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		if (!Auth::$signed_in) {
			HTTP::statusCode(401);
			Response::failApi();
		}

		Response::done([ 'user' => map_user(Auth::$user) ]);
	}
}
