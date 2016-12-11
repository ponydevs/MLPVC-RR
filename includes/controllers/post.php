<?php

use App\CoreUtils;
use App\CSRFProtection;
use App\Models\Post;
use App\Models\Request;
use App\Models\Reservation;
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

/** @var $data string */
/** @var $signedIn bool */

if (!POST_REQUEST) CoreUtils::notFound();

if (preg_match(new RegExp('^reload-(request|reservation)/(\d+)$'), $data, $_match)){
	$thing = $_match[1];
	$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
	if (empty($Post))
		Response::fail("The specified $thing does not exist");

	Response::done(array('li' => Posts::getLi($Post, isset($_POST['FROM_PROFILE']), true)));
}
if (!$signedIn) Response::fail();
CSRFProtection::protect();

$_match = array();
if (preg_match(new RegExp('^([gs]et)-(request|reservation)/(\d+)$'), $data, $_match)){
	$thing = $_match[2];
	/** @var $Post Request|Reservation */
	$Post = $Database->where('id', $_match[3])->getOne("{$thing}s");
	if (empty($Post))
		Response::fail("The specified $thing does not exist");

	if (!(Permission::sufficient('staff') || ($thing === 'request' && empty($Post->reserved_by) && $Post->requested_by === $currentUser->id)))
		Response::fail();

	if ($_match[1] === 'get'){
		$response = array(
			'label' => $Post->label,
		);
		if ($thing === 'request'){
			$response['type'] = $Post->type;

			if (Permission::sufficient('developer') && !empty($Post->reserved_by))
				$response['reserved_at'] = !empty($Post->reserved_at) ? date('c', strtotime($Post->reserved_at)) : '';
		}
		if (Permission::sufficient('developer')){
			$response['posted'] = date('c', strtotime($Post->posted));
			if (!empty($Post->reserved_by) && !empty($Post->deviation_id))
					$response['finished_at'] = !empty($Post->finished_at) ? date('c', strtotime($Post->finished_at)) : '';
		}
		Response::done($response);
	}

	$update = array();
	Posts::checkPostDetails($thing, $update, $Post);

	if (empty($update))
		Response::success('Nothing was changed');

	if (!$Database->where('id', $Post->id)->update("{$thing}s", $update))
		Response::dbError();
	$Post->__construct($update);
	Response::done(array('li' => Posts::getLi($Post)));
}
if (preg_match(new RegExp('^((?:un)?(?:finish|lock|reserve)|add|delete|pls-transfer)-(request|reservation)s?/(\d+)$'),$data,$_match)){
	$type = $_match[2];
	$action = $_match[1];

	if (empty($_match[3]))
		 Response::fail("Missing $type ID");
	$Post = $Database->where('id', $_match[3])->getOne("{$type}s");
	if (empty($Post)) Response::fail("There's no $type with the ID {$_match[3]}");

	if (!empty($Post->lock) && Permission::insufficient('developer') && $action !== 'unlock')
		Response::fail('This post has been approved and cannot be edited or removed.');

	if ($type === 'request' && $action === 'delete'){
		if (!Permission::sufficient('staff')){
			if (!$signedIn || $Post->requested_by !== $currentUser->id)
				Response::fail();

			if (!empty($Post->reserved_by))
				Response::fail('You cannot delete a request that has already been reserved by a group member');
		}

		if (!$Database->where('id', $Post->id)->delete('requests'))
			Response::dbError();

		if (!empty($Post->reserved_by))
			Posts::clearTransferAttempts($Post, $type, 'del');

		Logs::action('req_delete',array(
			'season' => $Post->season,
			'episode' => $Post->episode,
			'id' => $Post->id,
			'label' => $Post->label,
			'type' => $Post->type,
			'requested_by' => $Post->requested_by,
			'posted' => $Post->posted,
			'reserved_by' => $Post->reserved_by,
			'deviation_id' => $Post->deviation_id,
			'lock' => $Post->lock,
		));

		Response::done();
	}

	if (Permission::insufficient('member'))
		Response::fail();

	if ($action === 'pls-transfer'){
		$reserved_by = $Post->reserved_by ?? null;
		$checkIfUserCanReserve = function(&$message, &$data) use ($Post, $reserved_by, $type, $currentUser){
			Posts::clearTransferAttempts($Post, $type, 'free', $currentUser->id, $reserved_by);
			if (!Users::reservationLimitExceeded(RETURN_AS_BOOL)){
				$message .= '<br>Would you like to reserve it now?';
				$data = array('canreserve' => true);
			}
			else {
				$message .= "<br>However, you have 4 reservations already which means you can't reserve any more posts. Please review your pending reservations on your <a href='/user'>Account page</a> and cancel/finish at least one before trying to take on another.";
				$data = array();
			}
		};

		if (empty($reserved_by)){
			$message = 'This post is not reserved by anyone so there no need to ask for anyone\'s confirmation.';
			$checkIfUserCanReserve($message, $data, 'overdue');
			Response::fail($message, $data);
		}
		if ($reserved_by === $currentUser->id)
			Response::fail("You've already reserved this $type");
		if ($Post->isOverdue()){
			$message = "This post was reserved ".Time::tag($Post->reserved_at)." so anyone's free to reserve it now.";
			$checkIfUserCanReserve($message, $data, 'overdue');
			Response::fail($message, $data);
		}

		Users::reservationLimitExceeded();

		if (!$Post->isTransferable())
			Response::fail("This $type was reserved recently, please allow up to 5 days before asking for a transfer");

		$ReserverLink = Users::get($reserved_by, 'id', 'name')->getProfileLink();

		$PreviousAttempts = Posts::getTransferAttempts($Post, $type, $currentUser->id, $reserved_by);

		if (!empty($PreviousAttempts[0]) && empty($PreviousAttempts[0]['read_at']))
			Response::fail("You already expressed your interest in this post to $ReserverLink ".Time::tag($PreviousAttempts[0]['sent_at']).', please wait for them to respond.');

		$notifSent = Notifications::send($Post->reserved_by,'post-passon',array(
			'type' => $type,
			'id' => $Post->id,
			'user' => $currentUser->id,
		));

		Response::success("A notification has been sent to $ReserverLink, please wait for them to react.<br>If they don't visit the site often, it'd be a good idea to send them a note asking him to consider your inquiry.");
	}

	$isUserReserver = $Post->reserved_by === $currentUser->id;
	if (!empty($Post->reserved_by)){
		switch ($action){
			case 'reserve':
				if ($isUserReserver)
					Response::fail("You've already reserved this $type", array('li' => Posts::getLi($Post)));
				if ($Post->isOverdue()){
					$overdue = array(
						'reserved_by' => $Post->reserved_by,
						'reserved_at' => $Post->reserved_at,
					);
					$Post->reserved_by = null;
					break;
				}
				Response::fail("This $type has already been reserved by ".Users::get($Post->reserved_by)->getProfileLink(), array('li' => Posts::getLi($Post)));
			break;
			case 'lock':
				if (empty($Post->deviation_id))
					Response::fail("Only finished {$type}s can be locked");

				CoreUtils::checkDeviationInClub($Post->deviation_id);

				if (!$Database->where('id', $Post->id)->update("{$type}s", array('lock' => true)))
					Response::dbError();

				$postdata = Posts::approve($type, $Post->id, !$isUserReserver ? $Post->reserved_by : null);

				$Post->lock = true;
				$response = array(
					'message' => "The image appears to be in the group gallery and as such it is now marked as approved.",
					'li' => Posts::getLi($Post),
				);
				if ($isUserReserver)
					$response['message'] .= " Thank you for your contribution!<div class='align-center'><span class='sideways-smiley-face'>;)</span></div>";
				Response::done($response);
			break;
			case 'unlock':
				if (Permission::insufficient('staff'))
					Response::fail();
				if (empty($Post->lock))
					Response::fail("This $type has not been approved yet");

				if (Permission::insufficient('developer') && CoreUtils::isDeviationInClub($Post->deviation_id) === true)
					Response::fail("<a href='http://fav.me/{$Post->deviation_id}' target='_blank'>This deviation</a> is part of the group gallery, which prevents the post from being unlocked.");

				$Database->where('id', $Post->id)->update("{$type}s", array('lock' => false));
				$Post->lock = false;

				Response::done();
			break;
			case 'unreserve':
				if (!$isUserReserver && Permission::insufficient('staff'))
					Response::fail();

				if ($type === 'request'){
					$update = array(
						'reserved_by' => null,
						'reserved_at' => null,
						'finished_at' => null,
					);
					break;
				}
				else $_REQUEST['unbind'] = true;
			case 'unfinish':
				if (!$isUserReserver && Permission::insufficient('staff'))
					Response::fail();

				if (isset($_REQUEST['unbind'])){
					if ($type === 'reservation'){
						if (!$Database->where('id', $Post->id)->delete('reservations'))
							Response::dbError();

						Response::success('Reservation deleted');
					}
					else if ($type === 'request' && Permission::insufficient('staff') && !$isUserReserver)
						Response::fail('You cannot remove the reservation from this post');

					$update = array(
						'reserved_by' => null,
						'reserved_at' => null,
					);
				}
				else if ($type === 'reservation' && empty($Post->preview))
					Response::fail('This reservation was added directly and cannot be marked unfinished. To remove it, check the unbind from user checkbox.');

				$update['deviation_id'] = null;
				$update['finished_at'] = null;

				if (!$Database->where('id', $Post->id)->update("{$type}s",$update))
					Response::dbError();

				Response::done();
			break;
			case 'finish':
				if (!$isUserReserver && Permission::insufficient('staff'))
					Response::fail();

				$update = Posts::checkRequestFinishingImage($Post->reserved_by);

				$finished_at = Posts::validateFinishedAt();
				$update['finished_at'] = isset($finished_at) ? date('c', $finished_at) : date('c');

				if (!$Database->where('id', $Post->id)->update("{$type}s",$update))
					Response::dbError();

				$postdata = array(
					'type' => $type,
					'id' => $Post->id
				);
				$message = '';
				if (isset($update['lock'])){
					$message .= "<p>The image appears to be in the group gallery already, so we marked it as approved.</p>";

					Logs::action('post_lock',$postdata);
					if ($Post->reserved_by !== $currentUser->id)
						Notifications::send($Post->reserved_by, 'post-approved', $postdata);
				}
				if ($type === 'request'){
					$u = Users::get($Post->requested_by,'id','name,id');
					if (!empty($u) && $Post->requested_by !== $currentUser->id){
						$notifSent = Notifications::send($u->id, 'post-finished', $postdata);
						$message .= "<p><strong>{$u->name}</strong> ".($notifSent === 0?'has been notified':'will receive a notification shortly').'.'.(is_string($notifSent)?"</p><div class='notice fail'><strong>Error:</strong> $notifSent":'')."</div>";
					}
				}
				if (!empty($message))
					Response::success($message);
				Response::done();
			break;
		}
	}
	else if ($action === 'unreserve')
		Response::done(array('li' => Posts::getLi($Post)));
	else if ($action === 'finish')
		Response::fail("This $type has not been reserved by anypony yet");

	if (empty($Post->reserved_by) && $type === 'request' && $action === 'reserve'){
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
	}

	if (empty($update) || !$Database->where('id', $Post->id)->update("{$type}s",$update))
		Response::fail('Nothing has been changed<br>If you tried to do something, then this is actually an error, which you should <a class="send-feedback">tell us</a> about.');

	if (!empty($overdue))
		Logs::action('res_overtake',array_merge(
			array(
				'id' => $Post->id,
				'type' => $type
			),
			$overdue
		));

	if (!empty($update['reserved_by']))
		Posts::clearTransferAttempts($Post, $type, 'snatch');
	else if (!empty($Post->reserved_by)){
		Posts::clearTransferAttempts($Post, $type, 'free');
	}

	if ($type === 'request'){
		$Post->__construct($update);
		$response = array('li' => Posts::getLi($Post));
		if (isset($_POST['FROM_PROFILE']))
			$response['pendingReservations'] = Users::getPendingReservationsHTML($Post->reserved_by, $isUserReserver);
		Response::done($response);
	}
	else Response::done();
}
else if ($data === 'mass-approve'){
	if (!Permission::sufficient('staff'))
		Response::fail();

	$ids = (new Input('ids','int[]',array(
		Input::CUSTOM_ERROR_MESSAGES => array(
		    Input::ERROR_MISSING => 'List of deviation IDs is missing',
		    Input::ERROR_INVALID => 'List of deviation IDs (@value) is invalid',
		)
	)))->out();

	$list = "";
	foreach ($ids as $id)
		$list .= "'d".base_convert($id, 10, 36)."',";
	$list = rtrim($list, ',');

	$Posts = $Database->rawQuery(
		"SELECT 'request' as type, id, deviation_id FROM requests WHERE deviation_id IN ($list) && lock = false
		UNION ALL
		SELECT 'reservation' as type, id, deviation_id FROM reservations WHERE deviation_id IN ($list) && lock = false"
	);

	if (empty($Posts))
		Response::success('There were no posts in need of marking as approved');

	$approved = 0;
	foreach ($Posts as $p){
		if (CoreUtils::isDeviationInClub($p['deviation_id']) !== true)
			continue;

		Posts::approve($p['type'], $p['id']);
		$approved++;
	}

	if ($approved === 0)
		Response::success('There were no posts in need of marking as approved');

	Response::success('Marked '.CoreUtils::makePlural('post', $approved, PREPEND_NUMBER).' as approved. To see which ones, check the <a href="/admin/logs/1?type=post_lock&by=you">list of posts you\'ve approved</a>.',array('reload' => true));
}
else if ($data === 'add-reservation'){
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
else if (preg_match(new RegExp('^set-(request|reservation)-image/(\d+)$'), $data, $_match)){
	$thing = $_match[1];
	$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
	if (empty($Post))
		Response::fail("The specified $thing does not exist");
	if ($Post->lock)
		Response::fail('This post is locked, its image cannot be changed.');

	if (Permission::insufficient('staff'))
		switch ($thing){
			case 'request':
				if ($Post->requested_by !== $currentUser->id || !empty($Post->reserved_by))
					Response::fail();
			break;
			case 'reservation':
				if ($Post->reserved_by !== $currentUser->id)
					Response::fail();
			break;
		};

	$image_url = (new Input('image_url','string',array(
		Input::CUSTOM_ERROR_MESSAGES => array(
			Input::ERROR_MISSING => 'Image URL is missing',
		)
	)))->out();
	$Image = Posts::checkImage($image_url, $Post);

	// Check image availability
	if (!DeviantArt::isImageAvailable($Image->preview))
		Response::fail("<p class='align-center'>The specified image doesn't seem to exist. Please verify that you can reach the URL below and try again.<br><a href='{$Image->preview}' target='_blank'>{$Image->preview}</a></p>");

	if (!$Database->where('id', $Post->id)->update("{$thing}s",array(
		'preview' => $Image->preview,
		'fullsize' => $Image->fullsize,
	))) Response::dbError();

	Logs::action('img_update',array(
		'id' => $Post->id,
		'thing' => $thing,
		'oldpreview' => $Post->preview,
		'oldfullsize' => $Post->fullsize,
		'newpreview' => $Image->preview,
		'newfullsize' => $Image->fullsize,
	));

	Response::done(array('preview' => $Image->preview));
}
else if (preg_match(new RegExp('^fix-(request|reservation)-stash/(\d+)$'), $data, $_match)){
	if (!Permission::sufficient('staff'))
		Response::fail();

	$thing = $_match[1];
	$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
	if (empty($Post))
		Response::fail("The specified $thing does not exist");

	// Link is already full size, we're done
	if (preg_match($FULLSIZE_MATCH_REGEX, $Post->fullsize))
		Response::done(array('fullsize' => $Post->fullsize));

	// Reverse submission lookup
	$StashItem = $Database
		->where('fullsize', $Post->fullsize)
		->orWhere('preview', $Post->preview)
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
			else throw new Exception("Code $fullsize; Could not find the URL");
		}
	}
	catch (Exception $e){
		Response::fail('Error while finding URL: '.$e->getMessage());
	}
	// Check image availability
	if (!!DeviantArt::isImageAvailable($fullsize))
		Response::fail("The specified image doesn't seem to exist. Please verify that you can reach the URL below and try again.<br><a href='$fullsize' target='_blank'>$fullsize</a>");

	if (!$Database->where('id', $Post->id)->update("{$thing}s",array(
		'fullsize' => $fullsize,
	))) Response::dbError();

	Response::done(array('fullsize' => $fullsize));
}

$type = (new Input('what',function($value){
	if (!in_array($value,Posts::$TYPES))
		return Input::ERROR_INVALID;
},array(
	Input::IS_OPTIONAL => true,
	Input::CUSTOM_ERROR_MESSAGES => array(
		Input::ERROR_INVALID => 'Post type (@value) is invalid',
	)
)))->out();

if (!empty($type) && $type === 'reservation'){
	if (Permission::insufficient('member'))
		Response::fail();
	Users::reservationLimitExceeded();
}

$Image = Posts::checkImage(Posts::validateImageURL());

if (empty($type))
	Response::done(array(
		'preview' => $Image->preview,
		'title' => $Image->title,
	));

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

		if ($type === 'reservation' && !Permission::sufficient('member', $PostAs->role) && !isset($_POST['allow_nonmember']))
			Response::fail('The user you wanted to post as is not a club member, do you want to post as them anyway?',array('canforce' => true));

		$ByID = $PostAs->id;
	}
}

$insert[$type === 'reservation' ? 'reserved_by' : 'requested_by'] = $ByID;
Posts::checkPostDetails($type, $insert);

$PostID = $Database->insert("{$type}s",$insert,'id');
if (!$PostID)
	Response::dbError();
Response::done(array('id' => $PostID));
