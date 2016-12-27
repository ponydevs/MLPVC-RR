<?php

namespace App\Controllers;
use App\CoreUtils;
use App\CSRFProtection;
use App\DeviantArt;
use App\Episodes;
use App\Input;
use App\Logs;
use App\Notifications;
use App\Permission;
use App\Posts;
use App\RegExp;
use App\Response;
use App\Time;
use App\Users;
use App\Models\Post;
use App\Models\Request;
use App\Models\Reservation;

class PostController extends Controller {
	function _authorize(){
		global $signedIn;
		if (!$signedIn)
			Response::fail();
		CSRFProtection::protect();
	}

	function _authorizeMember(){
		$this->_authorize();

		if (Permission::insufficient('member'))
			Response::fail();
	}

	function reload($params){
		global $Database;

		$thing = $params['thing'];
		$this->_initPost(null, $params);

		Response::done(array('li' => Posts::getLi($this->_post, isset($_POST['FROM_PROFILE']), true)));
	}

	function action($params){
		global $Database, $currentUser;

		$this->_authorizeMember();

		$thing = $params['thing'];
		$action = $params['action'];
		$this->_initPost($action, $params);

		if (!(Permission::sufficient('staff') || ($thing === 'request' && empty($this->_post->reserved_by) && $this->_post->requested_by === $currentUser->id)))
			Response::fail();

		switch ($action){
			case "get":
				$response = array(
					'label' => $this->_post->label,
				);
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
			case "set":
				$update = array();
				Posts::checkPostDetails($thing, $update, $this->_post);

				if (empty($update))
					Response::success('Nothing was changed');

				if (!$Database->where('id', $this->_post->id)->update("{$thing}s", $update))
					Response::dbError();
				foreach ($update as $k => $v)
					$this->_post->{$k} = $v;
				Response::done(array('li' => Posts::getLi($this->_post)));
			break;
		}

		$isUserReserver = $this->_post->reserved_by === $currentUser->id;
		if (empty($this->_post->reserved_by)){
			switch ($action){
				case 'unreserve':
					Response::done(array('li' => Posts::getLi($this->_post)));
				case 'finish':
					Response::fail("This $thing has not been reserved by anypony yet");
				case 'reserve':
					if ($thing !== 'request')
						break;

					Users::reservationLimitExceeded();

					$update['reserved_by'] = $currentUser->id;
					if (Permission::sufficient('developer')){
						$reserve_as = Posts::validatePostAs();
						if (isset($reserve_as)){
							$User = Users::get($reserve_as, 'name');
							if (empty($User))
								Response::fail('User does not exist');
							if (!Permission::sufficient('member', $User->role) && !isset($_POST['screwit']))
								Response::fail('User does not have permission to reserve posts, continue anyway?', array('retry' => true));

							$update['reserved_by'] = $User->id;
						}
					}
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

				if (!$Database->where('id', $this->_post->id)->update("{$thing}s", array('lock' => true)))
					Response::dbError();

				$postdata = Posts::approve($thing, $this->_post->id, !$isUserReserver ? $this->_post->reserved_by : null);

				$this->_post->lock = true;
				$response = array(
					'message' => "The image appears to be in the group gallery and as such it is now marked as approved.",
					'li' => Posts::getLi($this->_post),
				);
				if ($isUserReserver)
					$response['message'] .= " Thank you for your contribution!<div class='align-center'><span class='sideways-smiley-face'>;)</span></div>";
				Response::done($response);
			break;
			case 'unlock':
				if (Permission::insufficient('staff'))
					Response::fail();
				if (empty($this->_post->lock))
					Response::fail("This $thing has not been approved yet");

				if (Permission::insufficient('developer') && CoreUtils::isDeviationInClub($this->_post->deviation_id) === true)
					Response::fail("<a href='http://fav.me/{$this->_post->deviation_id}' target='_blank'>This deviation</a> is part of the group gallery, which prevents the post from being unlocked.");

				$Database->where('id', $this->_post->id)->update("{$thing}s", array('lock' => false));
				$this->_post->lock = false;

				Response::done();
			break;
			case 'unreserve':
				if (!$isUserReserver && Permission::insufficient('staff'))
					Response::fail();

				if (!empty($this->_post->deviation_id))
					Response::fail("You must unfinish this $thing before unreserving it.");

				if ($thing === 'request'){
					$update = array(
						'reserved_by' => null,
						'reserved_at' => null,
					);
					break;
				}
				else $_REQUEST['unbind'] = true;
			case 'unfinish':
				if (!$isUserReserver && Permission::insufficient('staff'))
					Response::fail();

				if (isset($_REQUEST['unbind'])){
					if ($thing === 'reservation'){
						if (!$Database->where('id', $this->_post->id)->delete('reservations'))
							Response::dbError();

						Response::success('Reservation deleted');
					}
					else if ($thing === 'request' && Permission::insufficient('staff') && !$isUserReserver)
						Response::fail('You cannot remove the reservation from this post');

					$update = array(
						'reserved_by' => null,
						'reserved_at' => null,
					);
				}
				else if ($thing === 'reservation' && empty($this->_post->preview))
					Response::fail('This reservation was added directly and cannot be marked unfinished. To remove it, check the unbind from user checkbox.');

				$update['deviation_id'] = null;
				$update['finished_at'] = null;

				if (!$Database->where('id', $this->_post->id)->update("{$thing}s",$update))
					Response::dbError();

				Response::done();
			break;
			case 'finish':
				if (!$isUserReserver && Permission::insufficient('staff'))
					Response::fail();

				$update = Posts::checkRequestFinishingImage($this->_post->reserved_by);

				$finished_at = Posts::validateFinishedAt();
				$update['finished_at'] = isset($finished_at) ? date('c', $finished_at) : date('c');

				if (!$Database->where('id', $this->_post->id)->update("{$thing}s",$update))
					Response::dbError();

				$postdata = array(
					'type' => $thing,
					'id' => $this->_post->id
				);
				$message = '';
				if (isset($update['lock'])){
					$message .= "<p>The image appears to be in the group gallery already, so we marked it as approved.</p>";

					Logs::action('post_lock',$postdata);
					if ($this->_post->reserved_by !== $currentUser->id)
						Notifications::send($this->_post->reserved_by, 'post-approved', $postdata);
				}
				if ($thing === 'request'){
					$u = Users::get($this->_post->requested_by,'id','name,id');
					if (!empty($u) && $this->_post->requested_by !== $currentUser->id){
						$notifSent = Notifications::send($u->id, 'post-finished', $postdata);
						$message .= "<p><strong>{$u->name}</strong> ".($notifSent === 0?'has been notified':'will receive a notification shortly').'.'.(is_string($notifSent)?"</p><div class='notice fail'><strong>Error:</strong> $notifSent":'')."</div>";
					}
				}
				if (!empty($message))
					Response::success($message);
				Response::done();
			break;
			case 'reserve':
				if ($isUserReserver)
					Response::fail("You've already reserved this $thing", array('li' => Posts::getLi($this->_post)));
				if (!$this->_post->isOverdue())
					Response::fail("This $thing has already been reserved by ".Users::get($this->_post->reserved_by)->getProfileLink(), array('li' => Posts::getLi($this->_post)));
				$overdue = array(
					'reserved_by' => $this->_post->reserved_by,
					'reserved_at' => $this->_post->reserved_at,
				);
				$this->_post->reserved_by = null;
			break;
		}

		if (empty($update) || !$Database->where('id', $this->_post->id)->update("{$thing}s",$update))
			Response::fail('Nothing has been changed<br>If you tried to do something, then this is actually an error, which you should <a class="send-feedback">tell us</a> about.');

		if (!empty($overdue))
			Logs::action('res_overtake',array_merge(
				array(
					'id' => $this->_post->id,
					'type' => $thing
				),
				$overdue
			));

		if (!empty($update['reserved_by']))
			Posts::clearTransferAttempts($this->_post, $thing, 'snatch');
		else if (!empty($this->_post->reserved_by))
			Posts::clearTransferAttempts($this->_post, $thing, 'free');

		if ($thing === 'request'){
			$oldReserver = $this->_post->reserved_by;
			foreach ($update as $k => $v)
				$this->_post->{$k} = $v;
			$response = [];
			$suggested = isset($_POST['SUGGESTED']);
			$fromProfile = isset($_POST['FROM_PROFILE']);
			if ($suggested)
				$response['button'] = Posts::getPostReserveButton($this->_post, Users::get($this->_post->reserved_by), false);
			else if (!$fromProfile)
				$response['li'] = Posts::getLi($this->_post);
			if ($fromProfile || $suggested)
				$response['pendingReservations'] = Users::getPendingReservationsHTML($suggested ? $this->_post->reserved_by : $oldReserver, $suggested ? true : $isUserReserver);
			Response::done($response);
		}
		else Response::done();
	}

	private function _checkImage(){
		return Posts::checkImage(Posts::validateImageURL());
	}

	function checkImage(){
		$this->_authorize();

		$Image = $this->_checkImage();

		Response::done(array(
			'preview' => $Image->preview,
			'title' => $Image->title,
		));
	}

	function add(){
		global $currentUser, $Database;

		$this->_authorize();

		$thing = (new Input('what',function($value){
			if (!in_array($value,Posts::$TYPES))
				return Input::ERROR_INVALID;
		},array(
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_INVALID => 'Post type (@value) is invalid',
			)
		)))->out();

		if ($thing === 'reservation'){
			if (Permission::insufficient('member'))
				Response::fail();
			Users::reservationLimitExceeded();
		}

		$Image =  $this->_checkImage();

		$insert = array(
			'preview' => $Image->preview,
			'fullsize' => $Image->fullsize,
		);

		$season = Episodes::validateSeason(Episodes::ALLOW_MOVIES);
		$episode = Episodes::validateEpisode();
		$epdata = Episodes::getActual($season, $episode, Episodes::ALLOW_MOVIES);
		if (empty($epdata))
			Response::fail("The specified episode (S{$season}E$episode) does not exist");
		$insert['season'] = $epdata->season;
		$insert['episode'] = $epdata->episode;

		$ByID = $currentUser->id;
		if (Permission::sufficient('developer')){
			$username = Posts::validatePostAs();
			if (isset($username)){
				$PostAs = Users::get($username, 'name', 'id,role');

				if (empty($PostAs))
					Response::fail('The user you wanted to post as does not exist');

				if ($thing === 'reservation' && !Permission::sufficient('member', $PostAs->role) && !isset($_POST['allow_nonmember']))
					Response::fail('The user you wanted to post as is not a club member, do you want to post as them anyway?',array('canforce' => true));

				$ByID = $PostAs->id;
			}
		}

		$insert[$thing === 'reservation' ? 'reserved_by' : 'requested_by'] = $ByID;
		Posts::checkPostDetails($thing, $insert);

		$PostID = $Database->insert("{$thing}s",$insert,'id');
		if (!$PostID)
			Response::dbError();
		Response::done(array('id' => $PostID));
	}

	/** @var \App\Models\Post */
	private $_post;
	function _initPost($action, $params){
		global $Database;

		$thing = $params['thing'];

		$this->_post = $Database->where('id', $params['id'])->getOne("{$thing}s");
		if (empty($this->_post)) Response::fail("There's no $thing with the ID {$params['id']}");

		if (!empty($this->_post->lock) && Permission::insufficient('developer') && $action !== 'unlock')
			Response::fail('This post has been approved and cannot be edited or removed.');
	}

	function deleteRequest($params){
		global $currentUser, $Database,$signedIn;

		$this->_authorize();

		$params['thing'] = 'request';
		$this->_initPost('delete', $params);

		if (!Permission::sufficient('staff')){
			if (!$signedIn || $this->_post->requested_by !== $currentUser->id)
				Response::fail();

			if (!empty($this->_post->reserved_by))
				Response::fail('You cannot delete a request that has already been reserved by a group member');
		}

		if (!$Database->where('id', $this->_post->id)->delete('requests'))
			Response::dbError();

		if (!empty($this->_post->reserved_by))
			Posts::clearTransferAttempts($this->_post, $params['thing'], 'del');

		Logs::action('req_delete',array(
			'season' =>       $this->_post->season,
			'episode' =>      $this->_post->episode,
			'id' =>           $this->_post->id,
			'label' =>        $this->_post->label,
			'type' =>         $this->_post->type,
			'requested_by' => $this->_post->requested_by,
			'posted' =>       $this->_post->posted,
			'reserved_by' =>  $this->_post->reserved_by,
			'deviation_id' => $this->_post->deviation_id,
			'lock' =>         $this->_post->lock,
		));

		Response::done();
	}

	function queryTransfer($params){
		global $currentUser;

		if (Permission::insufficient('member'))
			Response::fail();

		$this->_authorizeMember();
		$this->_initPost(null, $params);

		$reserved_by = $this->_post->reserved_by ?? null;
		$checkIfUserCanReserve = function(&$message, &$data) use ($reserved_by, $params, $currentUser){
			Posts::clearTransferAttempts($this->_post, $params['thing'], 'free', $currentUser->id, $reserved_by);
			if (!Users::reservationLimitExceeded(RETURN_AS_BOOL)){
				$message .= '<br>Would you like to reserve it now?';
				$data = array('canreserve' => true);
			}
			else {
				$message .= "<br>However, you have 4 reservations already which means you can't reserve any more posts. Please review your pending reservations on your <a href='/user'>Account page</a> and cancel/finish at least one before trying to take on another.";
				$data = array();
			}
		};

		$data = null;
		if (empty($reserved_by)){
			$message = 'This post is not reserved by anyone so there no need to ask for anyone\'s confirmation.';
			$checkIfUserCanReserve($message, $data, 'overdue');
			Response::fail($message, $data);
		}
		if ($reserved_by === $currentUser->id)
			Response::fail("You've already reserved this {$params['thing']}");
		if ($this->_post->isOverdue()){
			$message = "This post was reserved ".Time::tag($this->_post->reserved_at)." so anyone's free to reserve it now.";
			$checkIfUserCanReserve($message, $data, 'overdue');
			Response::fail($message, $data);
		}

		Users::reservationLimitExceeded();

		if (!$this->_post->isTransferable())
			Response::fail("This {$params['thing']} was reserved recently, please allow up to 5 days before asking for a transfer");

		$ReserverLink = Users::get($reserved_by, 'id', 'name')->getProfileLink();

		$PreviousAttempts = Posts::getTransferAttempts($this->_post, $params['thing'], $currentUser->id, $reserved_by);

		if (!empty($PreviousAttempts[0]) && empty($PreviousAttempts[0]['read_at']))
			Response::fail("You already expressed your interest in this post to $ReserverLink ".Time::tag($PreviousAttempts[0]['sent_at']).', please wait for them to respond.');

		$notifSent = Notifications::send($this->_post->reserved_by,'post-passon',array(
			'type' => $params['thing'],
			'id' => $this->_post->id,
			'user' => $currentUser->id,
		));

		Response::success("A notification has been sent to $ReserverLink, please wait for them to react.<br>If they don't visit the site often, it'd be a good idea to send them a note asking them to consider your inquiry.");
	}

	function setImage($params){
		global $currentUser, $Database;

		$this->_authorize();

		$thing = $params['thing'];
		$this->_initPost(null, $params);
		if ($this->_post->lock)
			Response::fail('This post is locked, its image cannot be changed.');

		if (Permission::insufficient('staff'))
			switch ($thing){
				case 'request':
					if ($this->_post->requested_by !== $currentUser->id || !empty($this->_post->reserved_by))
						Response::fail();
				break;
				case 'reservation':
					if ($this->_post->reserved_by !== $currentUser->id)
						Response::fail();
				break;
			};

		$image_url = (new Input('image_url','string',array(
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'Image URL is missing',
			)
		)))->out();
		$Image = Posts::checkImage($image_url, $this->_post);

		// Check image availability
		if (!DeviantArt::isImageAvailable($Image->preview))
			Response::fail("<p class='align-center'>The specified image doesn't seem to exist. Please verify that you can reach the URL below and try again.<br><a href='{$Image->preview}' target='_blank'>{$Image->preview}</a></p>");

		if (!$Database->where('id', $this->_post->id)->update("{$thing}s",array(
			'preview' => $Image->preview,
			'fullsize' => $Image->fullsize,
		))) Response::dbError();

		Logs::action('img_update',array(
			'id' => $this->_post->id,
			'thing' => $thing,
			'oldpreview' => $this->_post->preview,
			'oldfullsize' => $this->_post->fullsize,
			'newpreview' => $Image->preview,
			'newfullsize' => $Image->fullsize,
		));

		Response::done(array('preview' => $Image->preview));
	}

	function fixStash($params){
		global $FULLSIZE_MATCH_REGEX, $Database;

		$this->_authorize();

		if (Permission::insufficient('staff'))
			Response::fail();

		$thing = $params['thing'];
		$this->_initPost(null, $params);

		// Link is already full size, we're done
		if (preg_match($FULLSIZE_MATCH_REGEX, $this->_post->fullsize))
			Response::done(array('fullsize' => $this->_post->fullsize));

		// Reverse submission lookup
		$StashItem = $Database
			->where('fullsize', $this->_post->fullsize)
			->orWhere('preview', $this->_post->preview)
			->getOne('deviation_cache','id,fullsize,preview');
		if (empty($StashItem['id']))
			Response::fail('Stash URL lookup failed');

		try {
			$fullsize = CoreUtils::getFullsizeURL($StashItem['id'], 'sta.sh');
			if (!is_string($fullsize)){
				if ($fullsize === 404){
					$Database->where('provider', 'sta.sh')->where('id', $StashItem['id'])->delete('deviation_cache');
					$Database->where('preview', $StashItem['preview'])->orWhere('fullsize', $StashItem['fullsize'])->update('requests',array(
						'fullsize' => null,
						'preview' => null,
					));
					$Database->where('preview', $StashItem['preview'])->orWhere('fullsize', $StashItem['fullsize'])->update('reservations',array(
						'fullsize' => null,
						'preview' => null,
					));
					Response::fail('The original image has been deleted from Sta.sh',array('rmdirect' => true));
				}
				else throw new \Exception("Code $fullsize; Could not find the URL");
			}
		}
		catch (\Exception $e){
			Response::fail('Error while finding URL: '.$e->getMessage());
		}
		// Check image availability
		if (!!DeviantArt::isImageAvailable($fullsize))
			Response::fail("The specified image doesn't seem to exist. Please verify that you can reach the URL below and try again.<br><a href='$fullsize' target='_blank'>$fullsize</a>");

		if (!$Database->where('id', $this->_post->id)->update("{$thing}s",array(
			'fullsize' => $fullsize,
		))) Response::dbError();

		Response::done(array('fullsize' => $fullsize));
	}

	function addReservation(){
		global $currentUser, $Database;

		$this->_authorize();

		if (!Permission::sufficient('staff'))
			Response::fail();
		$_POST['allow_overwrite_reserver'] = true;
		$insert = Posts::checkRequestFinishingImage();
		if (empty($insert['reserved_by']))
			$insert['reserved_by'] = $currentUser->id;

		$epdata = (new Input('epid','epid',array(
			Input::CUSTOM_ERROR_MESSAGES => array(
				Input::ERROR_MISSING => 'Episode identifier is missing',
				Input::ERROR_INVALID => 'Episode identifier (@value) is invalid',
			)
		)))->out();
		$epdata = Episodes::getActual($epdata['season'], $epdata['episode']);
		if (empty($epdata))
			Response::fail('The specified episode does not exist');
		$insert['season'] = $epdata->season;
		$insert['episode'] = $epdata->episode;

		$insert['finished_at'] = date('c');

		$postid = $Database->insert('reservations', $insert, 'id');
		if (!is_int($postid))
			Response::dbError();

		if (!empty($insert['lock']))
			Logs::action('post_lock',array(
				'type' => 'reservation',
				'id' => $postid,
			));

		Response::success('Reservation added');
	}

	function share($params){
		global $Database;

		$params['thing'] .= (array('req' => 'uest', 'res' => 'ervation'))[$params['thing']];

		/** @var $LinkedPost \App\Models\Post */
		$LinkedPost = $Database->where('id', $params['id'])->getOne("{$params['thing']}s");
		if (empty($LinkedPost))
			CoreUtils::notFound();

		$Episode = Episodes::getActual($LinkedPost->season, $LinkedPost->episode);
		if (empty($Episode))
			CoreUtils::notFound();

		Episodes::loadPage($Episode, false, $LinkedPost);
	}
}
