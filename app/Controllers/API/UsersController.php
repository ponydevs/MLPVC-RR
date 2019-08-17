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
 *     "avatarUrl",
 *     "avatarProvider"
 *   },
 *   additionalProperties=false,
 *   @OA\Property(
 *     property="id",
 *     type="string",
 *     format="uuid"
 *   ),
 *   @OA\Property(
 *     property="name",
 *     type="string",
 *     example="example"
 *   ),
 *   @OA\Property(
 *     property="role",
 *     ref="#/components/schemas/UserRole",
 *   ),
 *   @OA\Property(
 *     property="avatarUrl",
 *     type="string",
 *     format="uri",
 *     example="https://a.deviantart.net/avatars/e/x/example.png"
 *   ),
 *   @OA\Property(
 *     property="avatarProvider",
 *     ref="#/components/schemas/AvatarProvider",
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
 *   schema="AvatarProvider",
 *   type="string",
 *   description="List of supported avatar providers",
 *   enum={"deviantart","discord"}
 * )
 *
 * @OA\Schema(
 *   schema="ValueOfUser",
 *   type="object",
 *   description="A user's data under the user key",
 *   required={
 *     "user",
 *     "sessionUpdating"
 *   },
 *   additionalProperties=false,
 *   @OA\Property(
 *     property="user",
 *     type="object",
 *     allOf={
 *       @OA\Schema(ref="#/components/schemas/User")
 *     }
 *   ),
 *   @OA\Property(
 *     property="sessionUpdating",
 *     type="boolean",
 *     description="If this value is true the DeviantArt access token expired and the backend is updating it in the background. Future requests should be made to the appropriate endpoint periodically to check whether the session update was successful and the user should be logged out if it wasn't."
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
		'avatarUrl' => $u->avatar_url,
		'avatarProvider' => $u->avatar_provider,
	];
}

class UsersController extends APIController {
	/**
	 * @OA\Get(
	 *   path="/users/me",
	 *   description="Get information about the currently logged in user",
	 *   tags={"authentication"},
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
	function me() {
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		if (!Auth::$signed_in) {
			HTTP::statusCode(401);
			Response::failApi();
		}

		Response::done([
			'user' => map_user(Auth::$user),
			'sessionUpdating' => Auth::$session->updating,
		]);
	}

	// TODO Endpoint for changing user settings
}
