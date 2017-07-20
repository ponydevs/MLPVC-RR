<?php

namespace App\Controllers;
use ActiveRecord\RecordNotFound;
use App\Auth;
use App\CoreUtils;
use App\CSRFProtection;
use App\DB;
use App\DeviantArt;
use App\Episodes;
use APp\HTTP;
use App\ImageProvider;
use App\Input;
use App\Logs;
use App\Models\CachedDeviation;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Request;
use App\Models\Reservation;
use App\Models\User;
use App\Permission;
use App\Posts;
use App\Response;
use App\Time;
use App\Users;
use ElephantIO\Exception\ServerConnectionFailureException;

class PostController extends Controller {
	public function _authorize(){
		if (!Auth::$signed_in)
			Response::fail();
		CSRFProtection::protect();
	}

	public function _authorizeMember(){
		$this->_authorize();

		if (Permission::insufficient('member'))
			Response::fail();
	}

	public function reload($params){
		$thing = $params['thing'];
		$this->_initPost(null, $params);

		if (!isset($this->_post->deviation_id) && !DeviantArt::isImageAvailable($this->_post->fullsize, [404])){
			$update = ['broken' => 1 ];
			if ($this->_post->is_request && $this->_post->reserved_by !== null){
				$oldreserver = $this->_post->reserved_by;
				$update['reserved_by'] = null;
			}
			$this->_post->update_attributes($update);
			$log = [
				'type' => $this->_post->kind,
				'id' => $this->_post->id,
			];
			try {
				CoreUtils::socketEvent('post-break',$log);
			}
			catch (\Exception $e){
				error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
			}
			$log['reserved_by'] = $oldreserver ?? $this->_post->reserved_by;
			Logs::logAction('post_break',$log);

			if (Permission::insufficient('staff'))
				Response::done(['broken' => true]);
		}

		if ($this->_post->is_request && !$this->_post->finished){
			$section = "#group-{$this->_post->type}";
		}
		else {
			$un = $this->_post->finished?'':'un';
			$section = "#{$thing}s .{$un}finished";
		}
		$section .= ' > ul';

		Response::done([
			'li' => Posts::getLi($this->_post, isset($_POST['FROM_PROFILE']), !isset($_POST['cache'])),
			'section' => $section,
		]);
	}

	public function _checkPostEditPermission($thing){
		if (($thing === 'request' && empty($this->_post->reserved_by) && $this->_post->requested_by === Auth::$user->id) || Permission::insufficient('staff'))
			Response::fail();
	}

	const CONTRIB_THANKS = 'Thank you for your contribution!<div class="align-center"><span class="sideways-smiley-face">;)</span></div>';

	public function action($params){
		$this->_authorizeMember();

		$thing = $params['thing'];
		$action = $params['action'];
		$this->_initPost($action, $params);

		switch ($action){
			case 'get':
				$this->_checkPostEditPermission($thing);

				$response = [
					'label' => $this->_post->label,
				];
				if ($thing === 'request'){
					$response['type'] = $this->_post->type;

					if (Permission::sufficient('developer') && !empty($this->_post->reserved_by))
						$response['reserved_at'] = !empty($this->_post->reserved_at) ? date('c', strtotime($this->_post->reserved_at)) : '';
				}
				if (Permission::sufficient('developer')){
					$response['posted'] = date('c', strtotime($this->_post->posted));
					if (!empty($this->_post->reserved_by) && !empty($this->_post->deviation_id))
							$response['finished_at'] = !empty($this->_post->finished_at) ? date('c', strtotime($this->_post->finished_at)) : '';
				}
				Response::done($response);
			break;
			case 'set':
				$this->_checkPostEditPermission($thing);

				$update = [];
				Posts::checkPostDetails($thing, $update, $this->_post);

				if (empty($update))
					Response::success('Nothing was changed');

				if (!$this->_post->update_attributes($update))
					Response::dbError();

				try {
					CoreUtils::socketEvent('post-update',[
						'id' => $this->_post->id,
						'type' => $thing,
					]);
				}
				catch (\Exception $e){
					error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
				}

				Response::done();
			break;
			case 'unbreak':
				if (Permission::insufficient('staff'))
					Response::fail();

				foreach (['preview', 'fullsize'] as $key){
					$link = $this->_post->{$key};

					if (!DeviantArt::isImageAvailable($link))
						Response::fail("The $key image appears to be unavailable. Please make sure <a href='$link'>this link</a> works and try again. If it doesn't, you will need to replace the image.");
				}


				// We fetch the last log entry and restore the reserver from when the post was still up (if applicable)
				$LogEntry = DB::$instance->where('id', $this->_post->id)->where('type', $thing)->orderBy('entryid','DESC')->getOne('log__post_break');
				$update = ['broken' => 0];
				if (isset($LogEntry['reserved_by']))
					$update['reserved_by'] = $LogEntry['reserved_by'];
				$this->_post->update_attributes($update);

				Logs::logAction('post_fix',[
					'id' => $this->_post->id,
					'type' => $thing,
					'reserved_by' => $update['reserved_by'] ?? null,
				]);

				Response::done(['li' => Posts::getLi($this->_post)]);
			break;
			case 'locate':
				if (empty($this->_post) || $this->_post->broken)
					Response::fail("The post you were linked to has either been deleted or didn’t exist in the first place. Sorry.<div class='align-center'><span class='sideways-smiley-face'>:\</div>");

				Response::done([
					'castle' => [
						'name' => $this->_post->ep->formatTitle(),
						'url' => $this->_post->toLink(),
					],
				]);
			break;
		}

		$isUserReserver = $this->_post->reserved_by === Auth::$user->id;
		if ($this->_post->reserved_by === null){
			switch ($action){
				case 'unreserve':
					Response::done(['li' => Posts::getLi($this->_post)]);
				case 'finish':
					Response::fail("This $thing has not been reserved by anypony yet");
				case 'reserve':
					if ($thing !== 'request')
						break;
					if ($this->_post->broken)
						Response::fail('Broken posts cannot be reserved.'.(Permission::sufficient('staff')?' Update the image or clear the broken status through the edit menu to make the post reservable.':''));

					Users::reservationLimitExceeded();

					$update['reserved_by'] = Auth::$user->id;
					Posts::checkReserveAs($update);
					$update['reserved_at'] = date('c');
					if (Permission::sufficient('developer')){
						$reserved_at = Posts::validateReservedAt();
						if (isset($reserved_at))
							$update['reserved_at'] = date('c', $reserved_at);
					}
				break;
			}
		}
		else switch ($action){
			case 'lock':
				if (empty($this->_post->deviation_id))
					Response::fail("Only finished {$thing}s can be locked");

				CoreUtils::checkDeviationInClub($this->_post->deviation_id);

				if (!DB::$instance->where('id', $this->_post->id)->update("{$thing}s", ['lock' => true]))
					Response::dbError();

				$postdata = Posts::approve($thing, $this->_post->id, !$isUserReserver ? $this->_post->reserved_by : null);

				$this->_post->lock = true;
				$response = [
					'message' => 'The image appears to be in the group gallery and as such it is now marked as approved.',
				];
				try {
					CoreUtils::socketEvent('post-update',[
						'id' => $this->_post->id,
						'type' => $thing,
					]);
				}
				catch (ServerConnectionFailureException $e){
					$response['li'] = Posts::getLi($this->_post);
				}
				catch (\Exception $e){
					error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
				}
				if ($isUserReserver)
					$response['message'] .= ' '.self::CONTRIB_THANKS;
				Response::done($response);
			break;
			case 'unlock':
				if (Permission::insufficient('staff'))
					Response::fail();
				if (!$this->_post->lock)
					Response::fail("This $thing has not been approved yet");

				if (Permission::insufficient('developer') && CoreUtils::isDeviationInClub($this->_post->deviation_id) === true)
					Response::fail("<a href='http://fav.me/{$this->_post->deviation_id}' target='_blank' rel='noopener'>This deviation</a> is part of the group gallery, which prevents the post from being unlocked.");

				$this->_post->lock = false;
				$this->_post->save();

				try {
					CoreUtils::socketEvent('post-update',[
						'id' => $this->_post->id,
						'type' => $thing,
					]);
				}
				catch (\Exception $e){
					error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
				}

				Response::done();
			break;
			case 'unreserve':
				if (!$isUserReserver && Permission::insufficient('staff'))
					Response::fail();

				if (!empty($this->_post->deviation_id))
					Response::fail("You must unfinish this $thing before unreserving it.");

				if ($thing === 'request'){
					$update = [
						'reserved_by' => null,
						'reserved_at' => null,
					];
					break;
				}
				else $_REQUEST['unbind'] = true;
			case 'unfinish':
				if (!$isUserReserver && Permission::insufficient('staff'))
					Response::fail();

				if (isset($_REQUEST['unbind'])){
					if ($thing === 'reservation'){
						if (!DB::$instance->where('id', $this->_post->id)->delete('reservations'))
							Response::dbError();

						try {
							CoreUtils::socketEvent('post-delete',[
								'id' => $this->_post->id,
								'type' => 'reservation',
							]);
						}
						catch (\Exception $e){
							error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
						}

						Response::success('Reservation deleted',['remove' => true]);
					}
					else if ($thing === 'request' && !$isUserReserver && Permission::insufficient('staff'))
						Response::fail('You cannot remove the reservation from this post');

					$update = [
						'reserved_by' => null,
						'reserved_at' => null,
					];
				}
				else if ($thing === 'reservation' && empty($this->_post->preview))
					Response::fail('This reservation was added directly and cannot be marked unfinished. To remove it, check the unbind from user checkbox.');

				$update['deviation_id'] = null;
				$update['finished_at'] = null;

				if (!DB::$instance->where('id', $this->_post->id)->update("{$thing}s",$update))
					Response::dbError();

				try {
					CoreUtils::socketEvent('post-update',[
						'id' => $this->_post->id,
						'type' => $thing,
					]);
				}
				catch (\Exception $e){
					error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
				}

				Response::done();
			break;
			case 'finish':
				if (!$isUserReserver && Permission::insufficient('staff'))
					Response::fail();

				$update = Posts::checkRequestFinishingImage($this->_post->reserved_by);

				$finished_at = Posts::validateFinishedAt();
				$update['finished_at'] = $finished_at !== null ? date('c', $finished_at) : date('c');

				if (!DB::$instance->where('id', $this->_post->id)->update("{$thing}s",$update))
					Response::dbError();

				$postdata = [
					'type' => $thing,
					'id' => $this->_post->id
				];
				$message = '';
				if (isset($update['lock'])){
					$message .= '<p>';

					Logs::logAction('post_lock',$postdata);
					if ($isUserReserver)
						$message .= self::CONTRIB_THANKS.' ';
					else Notification::send($this->_post->reserved_by, 'post-approved', $postdata);

					$message .= "The post has been approved automatically because it's already in the club gallery.</p>";
				}
				if ($thing === 'request' && $this->_post->requested_by !== null && $this->_post->requested_by !== Auth::$user->id){
					$notifSent = Notification::send($this->_post->requester->id, 'post-finished', $postdata);
					$message .= "<p><strong>{$this->_post->requester->name}</strong> ".($notifSent === 0?'has been notified':'will receive a notification shortly').'.</p>'.(is_string($notifSent)?"<div class='notice fail'><strong>Error:</strong> $notifSent</div>":'');
				}

				try {
					CoreUtils::socketEvent('post-update',[
						'id' => $this->_post->id,
						'type' => $thing,
					]);
				}
				catch (\Exception $e){
					error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
				}

				if (!empty($message))
					Response::success($message);
				Response::done();
			break;
			case 'reserve':
				if ($isUserReserver)
					Response::fail("You've already reserved this $thing", ['li' => Posts::getLi($this->_post)]);
				if (!$this->_post->isOverdue())
					Response::fail("This $thing has already been reserved by ".$this->_post->reserver->getProfileLink(), ['li' => Posts::getLi($this->_post)]);
				$overdue = [
					'reserved_by' => $this->_post->reserved_by,
					'reserved_at' => $this->_post->reserved_at,
				];
				$update['reserved_by'] = Auth::$user->id;
				Posts::checkReserveAs($update);
				$update['reserved_at'] = date('c');
				$this->_post->reserved_by = $update['reserved_by'];
				$this->_post->reserved_at = $update['reserved_at'];
			break;
		}

		if (empty($update) || !DB::$instance->where('id', $this->_post->id)->update("{$thing}s",$update))
			Response::fail('Nothing has been changed<br>If you tried to do something, then this is actually an error, which you should <a class="send-feedback">tell us</a> about.');

		if (!empty($overdue))
			Logs::logAction('res_overtake',array_merge(
				[
					'id' => $this->_post->id,
					'type' => $thing
				],
				$overdue
			));

		if (!empty($update['reserved_by']))
			Posts::clearTransferAttempts($this->_post, 'snatch');
		else if (!empty($this->_post->reserved_by))
			Posts::clearTransferAttempts($this->_post, 'free');

		$socketServerAvailable = true;
		try {
			CoreUtils::socketEvent('post-update',[
				'id' => $this->_post->id,
				'type' => $thing,
			]);
		}
		catch (ServerConnectionFailureException $e){
			$socketServerAvailable = false;
			error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
		}
		catch (\Exception $e){
			error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
		}

		if ($thing === 'request'){
			$oldReserver = $this->_post->reserved_by;
			foreach ($update as $k => $v)
				$this->_post->{$k} = $v;
			$response = [];
			$suggested = isset($_POST['SUGGESTED']);
			$fromProfile = isset($_POST['FROM_PROFILE']);
			if ($suggested)
				$response['button'] = Posts::getPostReserveButton($this->_post, User::find($this->_post->reserved_by), false);
			else if (!$fromProfile && !$socketServerAvailable){
				if ($action !== 'unreserve')
					$response['li'] = Posts::getLi($this->_post);
				else $response['reload'] = true;
			}
			if ($fromProfile || $suggested)
				$response['pendingReservations'] = Users::getPendingReservationsHTML(User::find($suggested ? $this->_post->reserved_by : $oldReserver), $suggested ? true : $isUserReserver);
			Response::done($response);
		}
		else Response::done();
	}

	/**
	 * @return ImageProvider
	 */
	private function _checkImage(){
		return Posts::checkImage(Posts::validateImageURL());
	}

	public function checkImage(){
		$this->_authorize();

		$Image = $this->_checkImage();

		Response::done([
			'preview' => $Image->preview,
			'title' => $Image->title,
		]);
	}

	public function add(){
		$this->_authorize();

		$thing = (new Input('what',function($value){
			if (!in_array($value,Posts::TYPES))
				return Input::ERROR_INVALID;
		}, [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_INVALID => 'Post type (@value) is invalid',
			]
		]))->out();

		if ($thing === 'reservation'){
			if (Permission::insufficient('member'))
				Response::fail();
			Users::reservationLimitExceeded();
		}

		$Image = $this->_checkImage();
		if (!is_object($Image)){
			error_log("Getting post image failed\n".var_export($Image, true));
			Response::fail('Getting post image failed. If this persists, please <a class="send-feedback">let us know</a>.');
		}

		$insert = [
			'preview' => $Image->preview,
			'fullsize' => $Image->fullsize,
		];

		$season = Episodes::validateSeason(Episodes::ALLOW_MOVIES);
		$episode = Episodes::validateEpisode();
		$epdata = Episodes::getActual($season, $episode, Episodes::ALLOW_MOVIES);
		if (empty($epdata))
			Response::fail("The specified episode (S{$season}E$episode) does not exist");
		$insert['season'] = $epdata->season;
		$insert['episode'] = $epdata->episode;

		$ByID = Auth::$user->id;
		if (Permission::sufficient('developer')){
			$username = Posts::validatePostAs();
			if (isset($username)){
				$PostAs = Users::get($username, 'name');

				if (empty($PostAs))
					Response::fail('The user you wanted to post as does not exist');

				if ($thing === 'reservation' && !Permission::sufficient('member', $PostAs->role) && !isset($_POST['allow_nonmember']))
					Response::fail('The user you wanted to post as is not a club member, do you want to post as them anyway?', ['canforce' => true]);

				$ByID = $PostAs->id;
			}
		}

		$insert[$thing === 'reservation' ? 'reserved_by' : 'requested_by'] = $ByID;
		Posts::checkPostDetails($thing, $insert);

		$PostID = DB::$instance->insert("{$thing}s",$insert,'id');
		if (!$PostID)
			Response::dbError();

		try {
			CoreUtils::socketEvent('post-add',[
				'id' => $PostID,
				'type' => $thing,
				'season' => (int)$insert['season'],
				'episode' => (int)$insert['episode'],
			]);
		}
		catch (\Exception $e){
			error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
		}

		Response::done(['id' => "$thing-$PostID"]);
	}

	/** @var Request|Reservation */
	private $_post;
	public function _initPost($action, $params){
		$thing = $params['thing'];

		try {
			$this->_post = $thing === 'request' ? Request::find($params['id']) : Reservation::find($params['id']);
		}
		catch (RecordNotFound $e){}
		if (empty($this->_post))
			Response::fail("There’s no $thing with the ID {$params['id']}");

		if (!empty($this->_post->lock) && Permission::insufficient('developer') && !in_array($action,['unlock','lazyload','locate'],true))
			Response::fail('This post has been approved and cannot be edited or removed.');
	}

	public function deleteRequest($params){
		$this->_authorize();

		$params['thing'] = 'request';
		$this->_initPost('delete', $params);

		if (!Permission::sufficient('staff')){
			if (!Auth::$signed_in || $this->_post->requested_by !== Auth::$user->id)
				Response::fail();

			if (!empty($this->_post->reserved_by))
				Response::fail('You cannot delete a request that has already been reserved by a group member');
		}

		if (!DB::$instance->where('id', $this->_post->id)->delete('requests'))
			Response::dbError();

		if (!empty($this->_post->reserved_by))
			Posts::clearTransferAttempts($this->_post, 'del');

		Logs::logAction('req_delete', [
			'season' =>       $this->_post->season,
			'episode' =>      $this->_post->episode,
			'id' =>           $this->_post->id,
			'label' =>        $this->_post->label,
			'type' =>         $this->_post->type,
			'requested_by' => $this->_post->requested_by,
			'requested_at' => $this->_post->requested_at,
			'reserved_by' =>  $this->_post->reserved_by,
			'deviation_id' => $this->_post->deviation_id,
			'lock' =>         $this->_post->lock,
		]);
		try {
			CoreUtils::socketEvent('post-delete',[
				'id' => $this->_post->id,
				'type' => 'request',
			]);
		}
		catch (\Exception $e){
			error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
		}

		Response::done();
	}

	public function queryTransfer($params){
		if (Permission::insufficient('member'))
			Response::fail();

		$this->_authorizeMember();
		$this->_initPost(null, $params);

		$reserved_by = $this->_post->reserver;
		$checkIfUserCanReserve = function(&$message, &$data){
			Posts::clearTransferAttempts($this->_post, 'free', Auth::$user);
			if (!Users::reservationLimitExceeded(RETURN_AS_BOOL)){
				$message .= '<br>Would you like to reserve it now?';
				$data = ['canreserve' => true];
			}
			else {
				$message .= "<br>However, you have 4 reservations already which means you can’t reserve any more posts. Please review your pending reservations on your <a href='/u'>Account page</a> and cancel/finish at least one before trying to take on another.";
				$data = [];
			}
		};

		$data = null;
		if (empty($reserved_by)){
			$message = 'This post is not reserved by anyone so there no need to ask for anyone’s confirmation.';
			$checkIfUserCanReserve($message, $data);
			Response::fail($message, $data);
		}
		if ($reserved_by->id === Auth::$user->id)
			Response::fail("You've already reserved this {$params['thing']}");
		if ($this->_post->isOverdue()){
			$message = 'This post was reserved '.Time::tag($this->_post->reserved_at).' so anyone’s free to reserve it now.';
			$checkIfUserCanReserve($message, $data);
			Response::fail($message, $data);
		}

		Users::reservationLimitExceeded();

		if (!$this->_post->isTransferable())
			Response::fail("This {$params['thing']} was reserved recently, please allow up to 5 days before asking for a transfer");

		$ReserverLink = $reserved_by->getProfileLink();

		$PreviousAttempts = Posts::getTransferAttempts($this->_post, Auth::$user->id);

		if (!empty($PreviousAttempts[0]) && empty($PreviousAttempts[0]['read_at']))
			Response::fail("You already expressed your interest in this post to $ReserverLink ".Time::tag($PreviousAttempts[0]['sent_at']).', please wait for them to respond.');

		$notifSent = Notification::send($this->_post->reserved_by, 'post-passon', [
			'type' => $this->_post->kind,
			'id' => $this->_post->id,
			'user' => Auth::$user->id,
		]);

		Response::success("A notification has been sent to $ReserverLink, please wait for them to react.<br>If they don’t visit the site often, it’d be a good idea to send them a note asking them to consider your inquiry.");
	}

	public function setImage($params){
		$this->_authorize();

		$thing = $params['thing'];
		$this->_initPost(null, $params);
		if ($this->_post->lock)
			Response::fail('This post is locked, its image cannot be changed.');

		if (Permission::insufficient('staff'))
			switch ($thing){
				case 'request':
					if ($this->_post->requested_by !== Auth::$user->id || !empty($this->_post->reserved_by))
						Response::fail();
				break;
				case 'reservation':
					if ($this->_post->reserved_by !== Auth::$user->id)
						Response::fail();
				break;
			};

		$image_url = (new Input('image_url','string', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Image URL is missing',
			]
		]))->out();
		$Image = Posts::checkImage($image_url, $this->_post);

		// Check image availability
		if (!DeviantArt::isImageAvailable($Image->preview))
			Response::fail("<p class='align-center'>The specified image doesn’t seem to exist. Please verify that you can reach the URL below and try again.<br><a href='{$Image->preview}' target='_blank' rel='noopener'>{$Image->preview}</a></p>");

		$update = [
			'preview' => $Image->preview,
			'fullsize' => $Image->fullsize,
			'broken' => 0,
		];
		$wasBroken = $this->_post->broken;
		if (!$this->_post->update_attributes($update))
			Response::dbError();

		Logs::logAction('img_update', [
			'id' => $this->_post->id,
			'thing' => $thing,
			'oldpreview' => $this->_post->preview,
			'oldfullsize' => $this->_post->fullsize,
			'newpreview' => $Image->preview,
			'newfullsize' => $Image->fullsize,
		]);

		Response::done($wasBroken ? ['li' => Posts::getLi($this->_post)] : ['preview' => $Image->preview]);
	}

	public function lazyload($params){
		$this->_initPost('lazyload', $params);

		if (empty($this->_post))
			HTTP::statusCode(404, AND_DIE);

		Response::done(['html' => $this->_post->getFinishedImage(isset($_GET['viewonly']))]);
	}

	public function fixStash($params){
		global $FULLSIZE_MATCH_REGEX;

		$this->_authorize();

		if (Permission::insufficient('staff'))
			Response::fail();

		$thing = $params['thing'];
		$this->_initPost(null, $params);

		// Link is already full size, we're done
		if (preg_match($FULLSIZE_MATCH_REGEX, $this->_post->fullsize))
			Response::done(['fullsize' => $this->_post->fullsize]);

		// Reverse submission lookup
		/** @var $StashItem CachedDeviation */
		$StashItem = DB::$instance
			->where('fullsize', $this->_post->fullsize)
			->orWhere('preview', $this->_post->preview)
			->getOne('cached_deviations');
		if (empty($StashItem))
			Response::fail('Stash URL lookup failed');

		try {
			$fullsize = CoreUtils::getFullsizeURL($StashItem->id, 'sta.sh');
			if (!is_string($fullsize)){
				if ($fullsize === 404){
					$StashItem->delete();
					DB::$instance->where('preview', $StashItem->preview)->orWhere('fullsize', $StashItem->fullsize)->update('requests', [
						'fullsize' => null,
						'preview' => null,
					]);
					DB::$instance->where('preview', $StashItem->preview)->orWhere('fullsize', $StashItem->fullsize)->update('reservations', [
						'fullsize' => null,
						'preview' => null,
					]);
					Response::fail('The original image has been deleted from Sta.sh', ['rmdirect' => true]);
				}
				else throw new \Exception("Code $fullsize; Could not find the URL");
			}
		}
		catch (\Exception $e){
			Response::fail('Error while finding URL: '.$e->getMessage());
		}
		// Check image availability
		if (!DeviantArt::isImageAvailable($fullsize))
			Response::fail("The specified image doesn’t seem to exist. Please verify that you can reach the URL below and try again.<br><a href='$fullsize' target='_blank' rel='noopener'>$fullsize</a>");

		$this->_post->fullsize = $fullsize;
		$this->_post->save();

		Response::done(['fullsize' => $fullsize]);
	}

	public function addReservation(){
		$this->_authorize();

		if (!Permission::sufficient('staff'))
			Response::fail();
		$_POST['allow_overwrite_reserver'] = true;
		$insert = Posts::checkRequestFinishingImage();
		if (empty($insert['reserved_by']))
			$insert['reserved_by'] = Auth::$user->id;

		$epdata = (new Input('epid','epid', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Episode identifier is missing',
				Input::ERROR_INVALID => 'Episode identifier (@value) is invalid',
			]
		]))->out();
		$epdata = Episodes::getActual($epdata['season'], $epdata['episode']);
		if (empty($epdata))
			Response::fail('The specified episode does not exist');
		$insert['season'] = $epdata->season;
		$insert['episode'] = $epdata->episode;

		$insert['finished_at'] = date('c');

		$Post = new Reservation($insert);
		if (!$Post->save())
			Response::dbError();

		if (!empty($insert['lock']))
			Logs::logAction('post_lock', [
				'type' => $Post->kind,
				'id' => $Post->id,
			]);

		try {
			CoreUtils::socketEvent('post-add',[
				'id' => $Post->id,
				'type' => 'reservation',
				'season' => (int)$insert['season'],
				'episode' => (int)$insert['episode'],
			]);
		}
		catch (\Exception $e){
			error_log("SocketEvent Error\n".$e->getMessage()."\n".$e->getTraceAsString());
		}

		Response::success('Reservation added');
	}

	const SHARE_TYPE = [
		'req' => 'request',
		'res' => 'reservation',
	];

	public function share($params){
		if (!isset(self::SHARE_TYPE[$params['thing']]))
			CoreUtils::notFound();

		$thing = self::SHARE_TYPE[$params['thing']];

		/** @var $LinkedPost Post */
		$LinkedPost = DB::$instance->where('id', $params['id'])->getOne("{$thing}s");
		if (empty($LinkedPost))
			CoreUtils::notFound();

		$Episode = Episodes::getActual($LinkedPost->season, $LinkedPost->episode, Episodes::ALLOW_MOVIES);
		if (empty($Episode))
			CoreUtils::notFound();

		Episodes::loadPage($Episode, false, $LinkedPost);
	}
}
