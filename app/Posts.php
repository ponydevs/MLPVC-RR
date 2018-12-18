<?php

namespace App;

use App\Models\Show;
use App\Models\Notification;
use App\Models\PCGSlotHistory;
use App\Models\Post;
use App\Models\User;
use App\Exceptions\MismatchedProviderException;

class Posts {

	/**
	 * Retrieves requests & reservations for the episode specified
	 * Optionally lists broken posts
	 *
	 * @param int  $show_id
	 * @param int  $only
	 * @param bool $showBroken
	 *
	 * @return Post[]|Post[][]
	 */
	public static function get(int $show_id, int $only = null, bool $showBroken = false){
		$return = [];
		if ($only !== ONLY_RESERVATIONS){
			// If we don't want reservations only, grab requests
			$return[] = Post::find('all', [
				'conditions'=> [
					'requested_by IS NOT NULL AND show_id = ?'.($showBroken === false?' AND broken IS NOT true':''),
					$show_id
				],
				'order' => 'finished_at asc, requested_at asc',
			]);
		}
		if ($only !== ONLY_REQUESTS){
			// If we don't want requests only, grab reservations
			$return[] = Post::find('all', [
				'conditions'=> [
					'requested_by IS NULL AND show_id = ?'.($showBroken === false?' AND broken IS NOT true':''),
					$show_id
				],
				'order' => 'finished_at asc, reserved_at asc',
			]);
		}

		return $only ? $return[0] : $return;
	}

	/**
	 * @return Post[]
	 */
	public static function getRecentPosts():array {
		return DB::$instance->orderByLiteral(Post::ORDER_BY_POSTED_AT, 'DESC')->get('posts', 20);
	}

	/**
	 * Get list of most recent posts
	 *
	 * @param bool $wrap
	 *
	 * @return string
	 */
	public static function getMostRecentList($wrap = WRAP):string {
		$recent_posts = self::getRecentPosts();

		return Twig::$env->render('admin/_most_recent_posts.html.twig', ['recent_posts' => $recent_posts, 'wrap' => $wrap ]);
	}

	/**
	 * POST data validator function used when creating/editing posts
	 *
	 * @param bool         $request Boolean that's true if post is a request and false otherwise
	 * @param array|object $target  Array or object to output the checked data into
	 * @param Post|null    $post    Optional, exsting post to compare new data against
	 */
	public static function checkPostDetails(bool $request, &$target, $post = null){
		$editing = !empty($post);

		$label = (new Input('label','string', [
			Input::IS_OPTIONAL => true,
			Input::IN_RANGE => [3,255],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_RANGE => 'The description must be between @min and @max characters'
			]
		]))->out();
		if ($label !== null){
			if (!$editing || $label !== $post->label){
				CoreUtils::checkStringValidity($label,'The description',INVERSE_PRINTABLE_ASCII_PATTERN);
				$label = preg_replace(new RegExp("''"),'"',$label);
				CoreUtils::set($target, 'label', $label);
			}
		}
		else if (!$editing && $request)
			Response::fail('Description cannot be empty');
		else CoreUtils::set($target, 'label', null);

		if ($request){
			$type = (new Input('type',function($value){
				if (!isset(Post::REQUEST_TYPES[$value]))
					return Input::ERROR_INVALID;
			}, [
				Input::IS_OPTIONAL => true,
				Input::CUSTOM_ERROR_MESSAGES => [
					Input::ERROR_INVALID => 'Request type (@value) is invalid'
				]
			]))->out();
			if ($type === null && !$editing)
				Response::fail('Missing request type');

			if (!$editing || (isset($type) && $type !== $post->type))
				CoreUtils::set($target,'type',$type);

			if (Permission::sufficient('developer')){
				$reserved_at = self::validateReservedAt();
				if (isset($reserved_at)){
					if ($reserved_at !== strtotime($post->reserved_at))
						CoreUtils::set($target,'reserved_at',date('c', $reserved_at));
				}
				else CoreUtils::set($target,'reserved_at',null);
			}
		}

		if (Permission::sufficient('developer')){
			$posted_at = self::validatePostedAt();
			if (isset($posted_at) && ($post === null || $posted_at !== strtotime($post->posted_at)))
				CoreUtils::set($target,'posted_at',date('c', $posted_at));

			$finished_at = self::validateFinishedAt();
			if (isset($finished_at)){
				if ($post !== null && $finished_at !== strtotime($post->finished_at))
					CoreUtils::set($target,'finished_at',date('c', $finished_at));
			}
			else CoreUtils::set($target,'finished_at',null);
		}
	}

	/**
	 * Check image URL in POST request
	 *
	 * @param string    $image_url
	 * @param Post|null $Post      Existing post for comparison
	 *
	 * @return ImageProvider
	 */
	public static function checkImage($image_url, $Post = null){
		try {
			$Image = new ImageProvider($image_url);
		}
		catch (\Exception $e){ Response::fail($e->getMessage()); }

		foreach (Post::KINDS as $kind){
			/** @noinspection DisconnectedForeachInstructionInspection */
			if ($Post !== null)
				DB::$instance->where('id',$Post->id,'!=');
			if ($Image->preview !== null){
				$already_used = Post::find_by_preview($Image->preview);
				if (!empty($already_used))
					Response::fail("This exact image has already been used for a {$already_used->toAnchor($kind,null,true)} under {$already_used->show->toAnchor()}");
			}
		}

		return $Image;
	}

	/**
	 * Checks the image which allows a post to be finished
	 *
	 * @param string|null $ReserverID
	 *
	 * @return array
	 */
	public static function checkPostFinishingImage($ReserverID = null){
		$deviation = (new Input('deviation','string', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Please specify a deviation URL',
			]
		]))->out();
		try {
			$Image = new ImageProvider($deviation, ImageProvider::PROV_DEVIATION);

			foreach (Post::KINDS as $what){

				$already_used = Post::find_by_deviation_id($Image->id);
				if (!empty($already_used))
					Response::fail("This exact deviation has already been marked as the finished version of  a {$already_used->toAnchor($already_used->kind,null,true)} under {$already_used->show->toAnchor()}");
			}

			$return = ['deviation_id' => $Image->id];
			$Deviation = DeviantArt::getCachedDeviation($Image->id);
			if (!empty($Deviation->author)){
				$Author = Users::get($Deviation->author, 'name');

				if (empty($Author))
					Response::fail("Could not fetch local user data for username: $Deviation->author");

				if (!isset($_REQUEST['allow_overwrite_reserver']) && !empty($ReserverID) && $Author->id !== $ReserverID){
					$sameUser = Auth::$user->id === $ReserverID;
					$person = $sameUser ? 'you' : 'the user who reserved this post';
					Response::fail("You've linked to an image which was not submitted by $person. If this was intentional, press Continue to proceed with marking the post finished <b>but</b> note that it will make {$Author->name} the new reserver.".($sameUser
							? "<br><br>This means that you'll no longer be able to interact with this post until {$Author->name} or an administrator cancels the reservation on it."
							: ''), ['retry' => true]);
				}

				$return['reserved_by'] = $Author->id;
			}

			if (CoreUtils::isDeviationInClub($return['deviation_id']) === true)
				$return['lock'] = true;

			return $return;
		}
		catch (MismatchedProviderException $e){
			Response::fail('The finished vector must be uploaded to DeviantArt, '.$e->getActualProvider().' links are not allowed');
		}
		catch (\Exception $e){ Response::fail($e->getMessage()); }
	}

	/**
	 * Generate HTML of requests for episode pages
	 *
	 * @param Post[]|null $arranged Arranged requests
	 * @param bool        $lazyload Output promise elements in place of deviation data
	 *
	 * @return string|array
	 * @throws \Exception
	 */
	public static function getRequestsSection(?array $arranged = null, bool $lazyload = false){
		return Twig::$env->render('show/_requests.html.twig', [
			'arranged' => $arranged,
			'current_user' => Auth::$user,
			'lazyload' => $lazyload,
		]);
	}


	/**
	 * Generate HTML of reservations for episode pages
	 *
	 * @param Post[]|null $arranged Arranged reservations
	 * @param bool        $lazyload Output promise elements in place of deviation data
	 *
	 * @return string|array
	 */
	public static function getReservationsSection(?array $arranged = null, bool $lazyload = false){
		return Twig::$env->render('show/_reservations.html.twig', [
			'arranged' => $arranged,
			'current_user' => Auth::$user,
			'lazyload' => $lazyload,
		]);
	}

	/**
	 * @param Post      $Post
	 * @param User|null $sent_by
	 * @param string    $cols
	 *
	 * @return Notification[]|null
	 */
	public static function getTransferAttempts(Post $Post, ?User $sent_by = null, $cols = 'read_at,sent_at'){
		if ($Post->reserved_by !== null)
			DB::$instance->where('recipient_id', $Post->reserved_by);
		if (!empty($sent_by))
			DB::$instance->where("data->>'user'", $sent_by->id);
		return DB::$instance
			->where('type', 'post-passon')
			->where("data->>'id'", $Post->id)
			->orderBy('sent_at', NEWEST_FIRST)
			->get('notifications',null,$cols);
	}

	/**
	 * @param Post      $Post
	 * @param string    $reason
	 * @param User|null $sent_by
	 *
	 * @throws \InvalidArgumentException
	 */
	public static function clearTransferAttempts(Post $Post, string $reason, ?User $sent_by = null){
		if (empty(Post::TRANSFER_ATTEMPT_CLEAR_REASONS[$reason]))
			throw new \InvalidArgumentException("Invalid clear reason $reason");

		DB::$instance->where('read_at IS NULL');
		$transfer_attempts = self::getTransferAttempts($Post, $sent_by, 'id,data');
		if (!empty($transfer_attempts)){
			$sent_for = [];
			foreach ($transfer_attempts as $n){
				Notifications::safeMarkRead($n->id);

				$data = JSON::decode($n->data);
				if (!empty($sent_for[$data['user']][$reason]["{$data['type']}-{$data['id']}"]))
					continue;

				Notification::send($data['user'], "post-pass$reason", [
					'id' => $data['id'],
					'by' => Auth::$user->id,
				]);
				$sent_for[$data['user']][$reason]["{$data['type']}-{$data['id']}"] = true;
			}
		}
	}

	/**
	 * List item generator function for reservation suggestions
	 * This function assumes that the post it's being used for is not reserved or it can be contested.
	 *
	 * @param Post $Request
	 *
	 * @return string
	 */
	public static function getSuggestionLi(Post $Request):string {
		if ($Request->is_reservation)
			throw new \Exception(__METHOD__." only accepts requests as its first argument, got reservation ($Request->id)");
		$escapedLabel = CoreUtils::aposEncode($Request->label);
		$label = $Request->getLabelHTML();
		$time_ago = Time::tag($Request->posted_at);
		$cat = Post::REQUEST_TYPES[$Request->type];
		$reserve = Permission::sufficient('member')
			? self::getPostReserveButton($Request->reserver, false, true)
			: "<div><a href='{$Request->toURL()}' class='btn blue typcn typcn-arrow-forward'>View on episode page</a></div>";
		return <<<HTML
<li id="request-{$Request->id}">
	<div class="image screencap">
		<a href="{$Request->fullsize}" target="_blank" rel="noopener">
			<img src="{$Request->fullsize}" alt="{$escapedLabel}">
		</a>
	</div>
	$label
	<em class="post-date">Requested <a href="{$Request->toURL()}">$time_ago</a> under {$Request->toAnchor()}</em>
	<em class="category">Category: {$cat}</em>
	$reserve
</li>
HTML;
	}

	/**
	 * @param User|null   $reservedBy
	 * @param bool|string $view_only
	 * @param bool        $forceAvailable
	 * @param bool        $enablePromises
	 *
	 * @return string
	 */
	public static function getPostReserveButton($reservedBy, $view_only, bool $forceAvailable = false, bool $enablePromises = false):string {
		if (empty($reservedBy) || $forceAvailable)
			return Permission::sufficient('member') && $view_only === false && UserPrefs::get('a_reserve', Auth::$user) ? "<button class='reserve-request typcn typcn-user-add'>Reserve</button>" : '';

		$dAlink = $reservedBy->toAnchor(User::WITH_AVATAR, $enablePromises);
		$vectorapp = $reservedBy->getVectorAppClassName();
		if (!empty($vectorapp))
			$vectorapp .= "' title='Uses ".$reservedBy->getVectorAppReadableName().' to make vectors';
		return "<div class='reserver$vectorapp'>$dAlink</div>";
	}

	public static function checkReserveAs(Post $post){
		if (Permission::sufficient('developer')){
			$reserve_as = self::validatePostAs();
			if ($reserve_as !== null){
				$User = Users::get($reserve_as, 'name');
				if (empty($User))
					Response::fail('User to reserve as does not exist');
				if (!isset($_POST['screwit']) && Permission::insufficient('member', $User->role))
					Response::fail('The specified user does not have permission to reserve posts, continue anyway?', ['retry' => true]);

				$post->reserved_by = $User->id;
			}
		}
	}

	public static function validateImageURL():string {
		return (new Input('image_url','string', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Please provide an image URL.',
			]
		]))->out();
	}

	public static function validatePostAs(){
		return Users::validateName('post_as', [
			Input::ERROR_INVALID => '"Post as" username (@value) is invalid',
		]);
	}

	public static function validatePostedAt(){
		return (new Input('posted_at','timestamp', [
			Input::IS_OPTIONAL => true,
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_INVALID => '"Posted at" timestamp (@value) is invalid',
			]
		]))->out();
	}

	public static function validateReservedAt(){
		return (new Input('reserved_at','timestamp', [
			Input::IS_OPTIONAL => true,
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_INVALID => '"Reserved at" timestamp (@value) is invalid',
			]
		]))->out();
	}

	public static function validateFinishedAt(){
		return (new Input('finished_at','timestamp', [
			Input::IS_OPTIONAL => true,
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_INVALID => '"Finished at" timestamp (@value) is invalid',
			]
		]))->out();
	}

	public static function sendUpdate(Post $post):bool {
		$socketServerAvailable = true;
		try {
			CoreUtils::socketEvent('post-update',[
				'id' => $post->id,
			]);
		}
		catch (ServerConnectionFailureException $e){
			$socketServerAvailable = false;
			CoreUtils::error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
		}
		catch (\Exception $e){
			CoreUtils::error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
		}
		return $socketServerAvailable;
	}
}
