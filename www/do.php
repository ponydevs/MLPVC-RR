<?php

	// Global variables \\
	$Color = 'Color';
	$color = 'color';
	$signedIn = false;
	$currentUser = null;
	$do = !empty($_GET['do']) ? $_GET['do'] : 'index';
	$data = !empty($_GET['data']) ? $_GET['data'] : '';
	
	require "init.php";

	switch ($do){
		case GH_WEBHOOK_DO:
			if (empty(GH_WEBHOOK_DO)) CoreUtils::Redirect('/', AND_DIE);

			if (!empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'GitHub-Hookshot/') === 0){
				if (empty($_SERVER['HTTP_X_GITHUB_EVENT']) || empty($_SERVER['HTTP_X_HUB_SIGNATURE']))
					CoreUtils::NotFound();

				$payloadHash = hash_hmac('sha1', file_get_contents('php://input'), GH_WEBHOOK_SECRET);
				if ($_SERVER['HTTP_X_HUB_SIGNATURE'] !== "sha1=$payloadHash")
					CoreUtils::NotFound();

				switch (strtolower($_SERVER['HTTP_X_GITHUB_EVENT'])) {
					case 'push':
						$output = array();
						exec("git reset HEAD --hard",$output);
						exec("git pull",$output);
						$output = implode("\n", $output);
						if (empty($output))
							CoreUtils::StatusCode(500, AND_DIE);
						echo $output;
					break;
					case 'ping':
						echo "pong";
					break;
					default: CoreUtils::NotFound();
				}

				exit;
			}
			CoreUtils::NotFound();
		break;
		case "signout":
			if (!$signedIn) CoreUtils::Respond("You've already signed out",1);
			CSRFProtection::Protect();

			if (isset($_REQUEST['unlink'])){
				try {
					DeviantArt::Request('https://www.deviantart.com/oauth2/revoke', null, array('token' => $currentUser['Session']['access']));
				}
				catch (cURLRequestException $e){
					CoreUtils::Respond("Coulnd not revoke the site's access: {$e->getMessage()} (HTTP {$e->getCode()})");
				}
			}

			if (isset($_REQUEST['unlink']) || isset($_REQUEST['everywhere'])){
				$col = 'user';
				$val = $currentUser['id'];
				if (!empty($_POST['username'])){
					if (!Permission::Sufficient('manager') || isset($_REQUEST['unlink']))
						CoreUtils::Respond();
					if (!$USERNAME_REGEX->match($_POST['username']))
						CoreUtils::Respond('Invalid username');
					$TargetUser = $Database->where('name', $_POST['username'])->getOne('users','id,name');
					if (empty($TargetUser))
						CoreUtils::Respond("Target user doesn't exist");
					if ($TargetUser['id'] !== $currentUser['id'])
						$val = $TargetUser['id'];
					else unset($TargetUser);
				}
			}
			else {
				$col = 'id';
				$val = $currentUser['Session']['id'];
			}

			if (!$Database->where($col,$val)->delete('sessions'))
				CoreUtils::Respond('Could not remove information from database');

			if (empty($TargetUser))
				Cookie::delete('access');
			CoreUtils::Respond(true);
		break;
		case "da-auth":
			DeviantArt::HandleAuth();
		break;
		case "post":
			if (!POST_REQUEST) CoreUtils::NotFound();
			if (!$signedIn) CoreUtils::Respond();
			CSRFProtection::Protect();

			$_match = array();
			if (regex_match(new RegExp('^([gs]et)-(request|reservation)/(\d+)$'), $data, $_match)){
				$thing = $_match[2];
				$Post = $Database->where('id', $_match[3])->getOne("{$thing}s");
				if (empty($Post))
					CoreUtils::Respond("The specified $thing does not exist");

				if (!(Permission::Sufficient('inspector') || ($thing === 'request' && empty($Post['reserved_by']) && $Post['requested_by'] === $currentUser['id'])))
					CoreUtils::Respond();

				if ($_match[1] === 'get'){
					$response = array(
						'label' => $Post['label'],
					);
					if ($thing === 'request'){
						$response['type'] = $Post['type'];

						if (Permission::Sufficient('developer') && isset($Post['reserved_at']))
							$response['reserved_at'] = date('c', strtotime($Post['reserved_at']));
					}
					if (Permission::Sufficient('developer'))
						$response['posted'] = date('c', strtotime($Post['posted']));
					CoreUtils::Respond($response);
				}

				$update = array();
				Posts::CheckPostDetails($thing, $update, $Post);

				if (empty($update))
					CoreUtils::Respond('Nothing was changed', 1);

				if (!$Database->where('id', $Post['id'])->update("{$thing}s", $update))
					CoreUtils::Respond(ERR_DB_FAIL);
				CoreUtils::Respond($update);
			}
			else if (regex_match(new RegExp('^set-(request|reservation)-image/(\d+)$'), $data, $_match)){

				$thing = $_match[1];
				$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
				if (empty($Post))
					CoreUtils::Respond("The specified $thing does not exist");
				if ($Post['lock'])
					CoreUtils::Respond('This post is locked, its image cannot be changed.');

				if (!Permission::Sufficient('inspector') || $thing !== 'request' || !($Post['requested_by'] === $currentUser['id'] && empty($Post['reserved_by'])))
					CoreUtils::Respond();

				$Image = Posts::CheckImage($Post);

				// Check image availability
				if (!@getimagesize($Image->preview)){
					sleep(1);
					if (!@getimagesize($Image->preview))
						CoreUtils::Respond("<p class='align-center'>The specified image doesn't seem to exist. Please verify that you can reach the URL below and try again.<br><a href='{$Image->preview}' target='_blank'>{$Image->preview}</a></p>");
				}

				if (!$Database->where('id', $Post['id'])->update("{$thing}s",array(
					'preview' => $Image->preview,
					'fullsize' => $Image->fullsize,
				))) CoreUtils::Respond(ERR_DB_FAIL);

				Log::Action('img_update',array(
					'id' => $Post['id'],
					'thing' => $thing,
					'oldpreview' => $Post['preview'],
					'oldfullsize' => $Post['fullsize'],
					'newpreview' => $Image->preview,
					'newfullsize' => $Image->fullsize,
				));

				CoreUtils::Respond(array('preview' => $Image->preview));
			}
			else if (regex_match(new RegExp('^fix-(request|reservation)-stash/(\d+)$'), $data, $_match)){
				if (!Permission::Sufficient('inspector'))
					CoreUtils::Respond();

				$thing = $_match[1];
				$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
				if (empty($Post))
					CoreUtils::Respond("The specified $thing does not exist");

				// Link is already full size, we're done
				if (regex_match($FULLSIZE_MATCH_REGEX, $Post['fullsize']))
					CoreUtils::Respond(array('fullsize' => $Post['fullsize']));

				// Reverse submission lookup
				$StashItem = $Database
					->where('fullsize', $Post['fullsize'])
					->orWhere('preview', $Post['preview'])
					->getOne('deviation_cache','id,fullsize,preview');
				if (empty($StashItem['id']))
					CoreUtils::Respond('Stash URL lookup failed');

				try {
					$fullsize = CoreUtils::GetStashFullsizeURL($StashItem['id']);
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
							CoreUtils::Respond('The original image has been deleted from Sta.sh',0,array('rmdirect' => true));
						}
						else throw new Exception("Code $fullsize; Could not find the URL");
					}
				}
				catch (Exception $e){
					CoreUtils::Respond('Error while finding URL: '.$e->getMessage());
				}
				// Check image availability
				if (!@getimagesize($fullsize)){
					sleep(1);
					if (!@getimagesize($fullsize))
						CoreUtils::Respond("The specified image doesn't seem to exist. Please verify that you can reach the URL below and try again.<br><a href='$fullsize' target='_blank'>$fullsize</a>");
				}

				if (!$Database->where('id', $Post['id'])->update("{$thing}s",array(
					'fullsize' => $fullsize,
				))) CoreUtils::Respond(ERR_DB_FAIL);

				CoreUtils::Respond(array('fullsize' => $fullsize));
			}

			$image_check = empty($_POST['what']);
			if (!$image_check){
				if (!in_array($_POST['what'],Posts::$TYPES))
					CoreUtils::Respond('Invalid post type');

				$type = $_POST['what'];
				if ($type === 'reservation'){
					if (!Permission::Sufficient('member'))
						CoreUtils::Respond();
					User::ReservationLimitCheck();
				}
			}

			if (!empty($_POST['image_url'])){
				$Image = Posts::CheckImage();

				if ($image_check)
					CoreUtils::Respond(array(
						'preview' => $Image->preview,
						'title' => $Image->title,
					));
			}
			else if ($image_check)
				CoreUtils::Respond("Please provide an image URL!");

			$insert = array(
				'preview' => $Image->preview,
				'fullsize' => $Image->fullsize,
			);

			if (($_POST['season'] != '0' && empty($_POST['season'])) || empty($_POST['episode']))
				CoreUtils::Respond('Missing episode identifiers');
			$epdata = Episode::GetActual((int)$_POST['season'], (int)$_POST['episode'], ALLOW_SEASON_ZERO);
			if (empty($epdata))
				CoreUtils::Respond('This episode does not exist');
			$insert['season'] = $epdata['season'];
			$insert['episode'] = $epdata['episode'];

			$ByID = $currentUser['id'];
			if (Permission::Sufficient('developer') && !empty($_POST['post_as'])){
				$username = trim($_POST['post_as']);
				$PostAs = User::Get($username, 'name', '');

				if (empty($PostAs))
					CoreUtils::Respond('The user you wanted to post as does not exist');

				if ($type === 'reservation' && !Permission::Sufficient('member', $PostAs['role']) && !isset($_POST['allow_nonmember']))
					CoreUtils::Respond('The user you wanted to post as is not a club member, so you want to post as them anyway?',0,array('canforce' => true));

				$ByID = $PostAs['id'];
			}

			$insert[$type === 'reservation' ? 'reserved_by' : 'requested_by'] = $ByID;
			Posts::CheckPostDetails($type, $insert);

			$PostID = $Database->insert("{$type}s",$insert,'id');
			if (!$PostID)
				CoreUtils::Respond(ERR_DB_FAIL);
			CoreUtils::Respond(array('id' => $PostID));
		break;
		case "reserving":
			if (!POST_REQUEST) CoreUtils::NotFound();
			$match = array();
			if (empty($data) || !regex_match(new RegExp('^(requests?|reservations?)(?:/(\d+))?$'),$data,$match))
				CoreUtils::Respond('Invalid request');

			$noaction = true;
			$canceling = $finishing = $unfinishing = $adding = $deleteing = $locking = false;
			foreach (array('cancel','finish','unfinish','add','delete','lock') as $k){
				if (isset($_REQUEST[$k])){
					$var = "{$k}ing";
					$$var = true;
					$noaction = false;
					break;
				}
			}
			$type = rtrim($match[1],'s');

			if (!$adding){
				if (!isset($match[2]))
					 CoreUtils::Respond("Missing $type ID");
				$ID = intval($match[2], 10);
				$Thing = $Database->where('id', $ID)->getOne("{$type}s");
				if (empty($Thing)) CoreUtils::Respond("There's no $type with that ID");

				if (!empty($Thing['lock']) && !Permission::Sufficient('developer'))
					CoreUtils::Respond('This post has been approved and cannot be edited or removed.'.(Permission::Sufficient('inspector') && !Permission::Sufficient('developer')?' If a change is necessary please ask the developer to do it for you.':''));

				if ($deleteing && $type === 'request'){
					if (!Permission::Sufficient('inspector')){
						if (!$signedIn || $Thing['requested_by'] !== $currentUser['id'])
							CoreUtils::Respond();

						if (!empty($Thing['reserved_by']))
							CoreUtils::Respond('You cannot delete a request that has already been reserved by a group member');
					}

					if (!$Database->where('id', $Thing['id'])->delete('requests'))
						CoreUtils::Respond(ERR_DB_FAIL);

					Log::Action('req_delete',array(
						'season' => $Thing['season'],
						'episode' => $Thing['episode'],
						'id' => $Thing['id'],
						'label' => $Thing['label'],
						'type' => $Thing['type'],
						'requested_by' => $Thing['requested_by'],
						'posted' => $Thing['posted'],
						'reserved_by' => $Thing['reserved_by'],
						'deviation_id' => $Thing['deviation_id'],
						'lock' => $Thing['lock'],
					));

					CoreUtils::Respond(true);
				}

				if (!Permission::Sufficient('member')) CoreUtils::Respond();
				$update = array(
					'reserved_by' => null,
					'reserved_at' => null
				);

				if (!empty($Thing['reserved_by'])){
					$usersMatch = $Thing['reserved_by'] === $currentUser['id'];
					if ($noaction){
						if ($usersMatch)
							CoreUtils::Respond("You've already reserved this $type");
						else CoreUtils::Respond("This $type has already been reserved by somepony else");
					}
					if ($locking){
						if (empty($Thing['deviation_id']))
							CoreUtils::Respond('Only finished {$type}s can be locked');
						$Status = CoreUtils::IsDeviationInClub($Thing['deviation_id']);
						if ($Status !== true)
							CoreUtils::Respond(
								$Status === false
								? "The deviation has not been submitted to/accepted by the group yet"
								: "There was an issue while checking the acceptance status (Error code: $Status)"
							);

						if (!$Database->where('id', $Thing['id'])->update("{$type}s", array('lock' => 1)))
							CoreUtils::Respond("This $type is already approved", 1);

						Log::Action('post_lock',array(
							'type' => $type,
							'id' => $Thing['id']
						));

						$message = "The image appears to be in the group gallery and as such it is now marked as approved.";
						if ($usersMatch)
							$message .= " Thank you for your contribution!";
						CoreUtils::Respond($message, 1, array('canedit' => Permission::Sufficient('developer')));
					}
					if ($canceling)
						$unfinishing = true;
					if ($unfinishing){
						if (($canceling && !$usersMatch) && !Permission::Sufficient('inspector')) CoreUtils::Respond();

						if (!$canceling && !isset($_REQUEST['unbind'])){
							if ($type === 'reservation' && empty($Thing['preview']))
								CoreUtils::Respond('This reservation was added directly and cannot be marked un-finished. To remove it, check the unbind from user checkbox.');
							unset($update['reserved_by']);
						}

						if (($canceling || isset($_REQUEST['unbind'])) && $type === 'reservation'){
							if (!$Database->where('id', $Thing['id'])->delete('reservations'))
								CoreUtils::Respond(ERR_DB_FAIL);

							if (!$canceling)
								CoreUtils::Respond('Reservation deleted', 1);
						}
						if (!$canceling){
							if (isset($_REQUEST['unbind']) && $type === 'request'){
								if (!Permission::Sufficient('inspector') && !$usersMatch)
									CoreUtils::Respond('You cannot remove the reservation from this post');
							}
							else $update = array();
							$update['deviation_id'] = null;
						}
					}
					else if ($finishing){
						if (!$usersMatch && !Permission::Sufficient('inspector'))
							CoreUtils::Respond();
						$update = Posts::CheckRequestFinishingImage($Thing['reserved_by']);
					}
				}
				else if ($finishing) CoreUtils::Respond("This $type has not yet been reserved");
				else if (!$canceling){
					User::ReservationLimitCheck();

					if (!empty($_POST['post_as'])){
						if (!Permission::Sufficient('developer'))
							CoreUtils::Respond('Reserving as other users is only allowed to the developer');

						$post_as = trim($_POST['post_as']);
						if (!$USERNAME_REGEX->match($post_as))
							CoreUtils::Respond('Username format is invalid');

						$User = User::Get($post_as, 'name');
						if (empty($User))
							CoreUtils::Respond('User does not exist');
						if (!Permission::Sufficient('member', $User['role']))
							CoreUtils::Respond('User does not have permission to reserve posts');

						$update['reserved_by'] = $User['id'];
					}
					else $update['reserved_by'] = $currentUser['id'];
					$update['reserved_at'] = date('c');
				}

				if ((!$canceling || $type !== 'reservation') && !$Database->where('id', $Thing['id'])->update("{$type}s",$update))
					CoreUtils::Respond('Nothing has been changed');

				foreach ($update as $k => $v)
					$Thing[$k] = $v;

				if (!$canceling && ($finishing || $unfinishing)){
					$out = array();
					if ($finishing && $type === 'request'){
						$u = User::Get($Thing['requested_by'],'id','name');
						if (!empty($u) && $Thing['requested_by'] !== $currentUser['id'])
							$out['message'] = "<p class='align-center'>You may want to mention <strong>{$u['name']}</strong> in the deviation description to let them know that their request has been fulfilled.</p>";
					}
					CoreUtils::Respond($out);
				}

				if ($type === 'request')
					CoreUtils::Respond(array('li' => Posts::GetLi($Thing, true)));
				else if ($type === 'reservation' && $canceling)
					CoreUtils::Respond(array('remove' => true));
				else CoreUtils::Respond(true);
			}
			else if ($type === 'reservation'){
				if (!Permission::Sufficient('inspector'))
					CoreUtils::Respond();
				$_POST['allow_overwrite_reserver'] = true;
				$insert = Posts::CheckRequestFinishingImage();
				if (empty($insert['reserved_by']))
					$insert['reserved_by'] = $currentUser['id'];
				$epdata = Episode::ParseID($_GET['add']);
				if (empty($epdata))
					CoreUtils::Respond('Invalid episode');
				$epdata = Episode::GetActual($epdata['season'], $epdata['episode']);
				if (empty($epdata))
					CoreUtils::Respond('The specified episode does not exist');
				$insert['season'] = $epdata['season'];
				$insert['episode'] = $epdata['episode'];

				if (!$Database->insert('reservations', $insert))
					CoreUtils::Respond(ERR_DB_FAIL);
				CoreUtils::Respond('Reservation added',1);
			}
			else CoreUtils::Respond('Invalid request');
		break;
		case "ping":
			if (!POST_REQUEST)
				CoreUtils::NotFound();

			CoreUtils::Respond(true);
		break;
		case "setting":
			if (!Permission::Sufficient('inspector') || !POST_REQUEST)
				CoreUtils::NotFound();
			CSRFProtection::Protect();

			if (!regex_match(new RegExp('^([gs]et)/([a-z_]+)$'), trim($data), $_match))
				CoreUtils::Respond('Setting key invalid');

			$getting = $_match[1] === 'get';
			$key = $_match[2];

			$currvalue = Configuration::Get($key);
			if ($currvalue === false)
				CoreUtils::Respond('Setting does not exist');
			if ($getting)
				CoreUtils::Respond(array('value' => $currvalue));

			if (!isset($_POST['value']))
				CoreUtils::Respond('Missing setting value');

			$newvalue = Configuration::Process($key);
			if ($newvalue === $currvalue)
				CoreUtils::Respond(array('value' => $newvalue));
			if (!Configuration::Set($key, $newvalue))
				CoreUtils::Respond(ERR_DB_FAIL);

			CoreUtils::Respond(array('value' => $newvalue));
		break;

		// PAGES
		case "muffin-rating":
			$ScorePercent = 100;
			if (isset($_GET['w']) && regex_match(new RegExp('^(\d|[1-9]\d|100)$'), $_GET['w']))
				$ScorePercent = intval($_GET['w'], 10);
			$RatingFile = file_get_contents(APPATH."img/muffin-rating.svg");
			header('Content-Type: image/svg+xml');
			die(str_replace("width='100'", "width='$ScorePercent'", $RatingFile));
		break;
		case "index":
			$CurrentEpisode = Episode::GetLatest();
			if (empty($CurrentEpisode)){
				unset($CurrentEpisode);
				CoreUtils::LoadPage(array(
					'title' => 'Home',
					'view' => 'episode',
				));
			}

			Episode::LoadPage($CurrentEpisode);
		break;
		case "episode":
			$_match = array();

			if (POST_REQUEST){
				CoreUtils::CanIHas('CSRFProtection');
				CSRFProtection::Protect();

				if (empty($data)) CoreUtils::NotFound();

				$EpData = Episode::ParseID($data);
				if (!empty($EpData)){
					$Ep = Episode::GetActual($EpData['season'],$EpData['episode']);
					$airs =  strtotime($Ep['airs']);
					unset($Ep['airs']);
					$Ep['airdate'] = gmdate('Y-m-d', $airs);
					$Ep['airtime'] = gmdate('H:i', $airs);
					CoreUtils::Respond(array(
						'ep' => $Ep,
						'epid' => Episode::FormatTitle($Ep, AS_ARRAY, 'id'),
					));
				}
				unset($EpData);

				if (regex_match(new RegExp('^delete/'.EPISODE_ID_PATTERN.'$'),$data,$_match)){
					if (!Permission::Sufficient('inspector')) CoreUtils::Respond();
					list($season,$episode) = array_splice($_match,1,2);

					$Episode = Episode::GetActual(intval($season, 10),intval($episode, 10));
					if (empty($Episode))
						CoreUtils::Respond("There's no episode with this season & episode number");

					if (!$Database->whereEp($Episode)->delete('episodes'))
						CoreUtils::Respond(ERR_DB_FAIL);
					Log::Action('episodes',array(
						'action' => 'del',
						'season' => $Episode['season'],
						'episode' => $Episode['episode'],
						'twoparter' => $Episode['twoparter'],
						'title' => $Episode['title'],
						'airs' => $Episode['airs'],
					));
					$CGDb->where('name', "s{$Episode['season']}e{$Episode['episode']}")->delete('tags');
					CoreUtils::Respond('Episode deleted successfuly',1,array(
						'upcoming' => Episode::GetSidebarUpcoming(NOWRAP),
					));
				}
				else if (regex_match(new RegExp('^((?:request|reservation)s)/'.EPISODE_ID_PATTERN.'$'), $data, $_match)){
					$Episode = Episode::GetActual($_match[2],$_match[3],ALLOW_SEASON_ZERO);
					if (empty($Episode))
						CoreUtils::Respond("There's no episode with this season & episode number");
					$only = $_match[1] === 'requests' ? ONLY_REQUESTS : ONLY_RESERVATIONS;
					CoreUtils::Respond(array(
						'render' => call_user_func("{$_match[1]}_render", Posts::Get($Episode['season'], $Episode['episode'], $only)),
					));
				}
				else if (regex_match(new RegExp('^vote/'.EPISODE_ID_PATTERN.'$'), $data, $_match)){
					$Episode = Episode::GetActual($_match[1],$_match[2],ALLOW_SEASON_ZERO);
					if (empty($Episode))
						CoreUtils::Respond("There's no episode with this season & episode number");

					if (isset($_REQUEST['detail'])){
						$VoteCounts = $Database->rawQuery(
							"SELECT count(*) as value, vote as label
							FROM episodes__votes v
							WHERE season = ? && episode = ?
							GROUP BY v.vote
							ORDER BY v.vote DESC",array($Episode['season'],$Episode['episode']));

						CoreUtils::Respond(array('data' => $VoteCounts));
					}

					if (isset($_REQUEST['html']))
						CoreUtils::Respond(array('html' => Episode::GetSidebarVoting($Episode)));

					if (!Permission::Sufficient('user'))
						CoreUtils::Respond();

					if (!$Episode['aired'])
						CoreUtils::Respond('You can only vote on this episode after it has aired.');

					$UserVote = Episode::GetUserVote($Episode);
					if (!empty($UserVote))
						CoreUtils::Respond('You already voted for this episode');

					if (empty($_POST['vote']) || !is_numeric($_POST['vote']))
						CoreUtils::Respond('Vote value missing from request');

					$Vote = intval($_POST['vote'], 10);
					if ($Vote < 1 || $Vote > 5)
						CoreUtils::Respond('Vote value must be an integer between 1 and 5 (inclusive)');

					if (!$Database->insert('episodes__votes',array(
						'season' => $Episode['season'],
						'episode' => $Episode['episode'],
						'user' => $currentUser['id'],
						'vote' => $Vote,
					))) CoreUtils::Respond(ERR_DB_FAIL);
					CoreUtils::Respond(array('newhtml' => Episode::GetSidebarVoting($Episode)));
				}
				else if (regex_match(new RegExp('^(([sg])et)?videos/'.EPISODE_ID_PATTERN.'$'), $data, $_match)){
					$Episode = Episode::GetActual($_match[3],$_match[4],ALLOW_SEASON_ZERO);
					if (empty($Episode))
						CoreUtils::Respond("There's no episode with this season & episode number");

					if (empty($_match[1]))
						CoreUtils::Respond(Episode::GetVideoEmbeds($Episode));

					$set = $_match[2] === 's';
					CoreUtils::CanIHas('Video.php');

					if (!$set){
						$return = array(
							'twoparter' => $Episode['twoparter'],
							'vidlinks' => array(),
							'fullep' => array(),
							'airs' => date('c',strtotime($Episode['airs'])),
						);
						$Vids = $Database->whereEp($Episode)->get('episodes__videos',null,'provider as name, *');
						foreach ($Vids as $part => $prov){
							if (!empty($prov['id']))
								$return['vidlinks']["{$prov['name']}_{$prov['part']}"] = Video::get_embed($prov['id'], $prov['name'], Video::URL_ONLY);
							if ($prov['fullep'])
								$return['fullep'][] = $prov['name'];
						}
						CoreUtils::Respond($return);
					}

					foreach (array('yt','dm') as $provider){
						for ($part = 1; $part <= ($Episode['twoparter']?2:1); $part++){
							$set = null;
							$PostKey = "{$provider}_$part";
							if (!empty($_POST[$PostKey])){
								$Provider = Episode::$VIDEO_PROVIDER_NAMES[$provider];
								try {
									$vid = new Video($_POST[$PostKey]);
								}
								catch (Exception $e){
									CoreUtils::Respond("$Provider link issue: ".$e->getMessage());
								};
								if (!isset($vid->provider) || $vid->provider['name'] !== $provider)
									CoreUtils::Respond("Incorrect $Provider URL specified");
								/** @noinspection PhpUndefinedFieldInspection */
								$set = $vid->id;
							}

							$fullep = $Episode['twoparter'] ? false : true;
							if ($part === 1 && $Episode['twoparter'] && isset($_POST["{$PostKey}_full"])){
								$NextPart = $provider.'_'.($part+1);
								$_POST[$NextPart] = null;
								$fullep = true;
							}

							$videocount = $Database
								->whereEp($Episode)
								->where('provider', $provider)
								->where('part', $part)
								->count('episodes__videos');
							if ($videocount === 0){
								if (!empty($set))
									$Database->insert('episodes__videos', array(
										'season' => $Episode['season'],
										'episode' => $Episode['episode'],
										'provider' => $provider,
										'part' => $part,
										'id' => $set,
										'fullep' => $fullep,
									));
							}
							else {
								$Database
									->whereEp($Episode)
									->where('provider', $provider)
									->where('part', $part);
								if (empty($set))
									$Database->delete('episodes__videos');
								else $Database->update('episodes__videos', array(
									'id' => $set,
									'fullep' => $fullep,
									'modified' => date('c'),
								));
							}
						}
					}

					CoreUtils::Respond('Links updated',1,array('epsection' => Episode::RenderVideos($Episode)));
				}
				else {
					if (!Permission::Sufficient('inspector')) CoreUtils::Respond();
					$editing = regex_match(new RegExp('^edit\/'.EPISODE_ID_PATTERN.'$'),$data,$_match);
					if ($editing){
						list($season, $episode) = array_map('intval', array_splice($_match, 1, 2));
						$insert = array();
					}
					else if ($data === 'add') $insert = array('posted_by' => $currentUser['id']);
					else CoreUtils::StatusCode(404, AND_DIE);

					if (!isset($_POST['season']) || !is_numeric($_POST['season']))
						CoreUtils::Respond('Season number is missing or invalid');
					$insert['season'] = intval($_POST['season'], 10);
					if ($insert['season'] < 1 || $insert['season'] > 8) CoreUtils::Respond('Season number must be between 1 and 8');

					if (!isset($_POST['episode']) || !is_numeric($_POST['episode']))
						CoreUtils::Respond('Episode number is missing or invalid');
					$insert['episode'] = intval($_POST['episode'], 10);
					if ($insert['episode'] < 1 || $insert['episode'] > 26) CoreUtils::Respond('Season number must be between 1 and 26');

					if ($editing){
						$Current = Episode::GetActual($insert['season'],$insert['episode']);
						if (empty($Current))
							CoreUtils::Respond("This episode doesn't exist");
					}
					$Target = Episode::GetActual($insert['season'],$insert['episode']);
					if (!empty($Target) && (!$editing || ($editing && ($Target['season'] !== $Current['season'] || $Target['episode'] !== $Current['episode']))))
						CoreUtils::Respond("There's already an episode with the same season & episode number");

					$insert['twoparter'] = isset($_POST['twoparter']) ? 1 : 0;

					if (empty($_POST['title']))
						CoreUtils::Respond('Episode title is missing or invalid');
					$insert['title'] = trim($_POST['title']);
					if (strlen($insert['title']) < 5 || strlen($insert['title']) > 35)
						CoreUtils::Respond('Episode title must be between 5 and 35 characters');
					if (!$EP_TITLE_REGEX->match($insert['title']))
						CoreUtils::Respond('Episode title contains invalid charcaters');

					if (empty($_POST['airs']))
						repond('No air date &time specified');
					$airs = strtotime($_POST['airs']);
					if (empty($airs))
						CoreUtils::Respond('Invalid air time');
					$insert['airs'] = date('c',strtotime('this minute', $airs));

					if ($editing){
						if (!$Database->whereEp($season,$episode)->update('episodes', $insert))
							CoreUtils::Respond('Updating episode failed: '.ERR_DB_FAIL);
					}
					else if (!$Database->insert('episodes', $insert))
						CoreUtils::Respond('Episode creation failed: '.ERR_DB_FAIL);

					$SeasonChanged = $editing && $season !== $insert['season'];
					$EpisodeChanged = $editing && $episode !== $insert['episode'];
					if (!$editing || $SeasonChanged || $EpisodeChanged){
						$TagName = "s{$insert['season']}e{$insert['episode']}";
						$EpTag = $CGDb->where('name', $editing ? "s{$season}e{$episode}" : $TagName)->getOne('tags');

						if (empty($EpTag)){
							if (!$CGDb->insert('tags', array(
								'name' => $TagName,
								'type' => 'ep',
							))) CoreUtils::Respond('Episode tag creation failed: '.ERR_DB_FAIL);
						}
						else if ($SeasonChanged || $EpisodeChanged)
							$CGDb->where('name',$EpTag['name'])->update('tags', array(
								'name' => $TagName,
							));
					}

					if ($editing){
						$logentry = array('target' => Episode::FormatTitle($Current,AS_ARRAY,'id'));
						$changes = 0;
						foreach (array('season', 'episode', 'twoparter', 'title', 'airs') as $k){
							if (isset($insert[$k]) && $insert[$k] != $Current[$k]){
								$logentry["old$k"] = $Current[$k];
								$logentry["new$k"] = $insert[$k];
								$changes++;
							}
						}
						if ($changes > 0) Log::Action('episode_modify',$logentry);
					}
					else Log::Action('episodes',array(
						'action' => 'add',
						'season' => $insert['season'],
						'episode' => $insert['episode'],
						'twoparter' => isset($insert['twoparter']) ? $insert['twoparter'] : 0,
						'title' => $insert['title'],
						'airs' => $insert['airs'],
					));
					CoreUtils::Respond($editing ? true : array('epid' => Episode::FormatTitle($insert,AS_ARRAY,'id')));
				}
			}

			Episode::LoadPage();
		break;
		case "episodes":
			CoreUtils::CanIHas('Pagination');
			$Pagination = new Pagination('episodes', 10, $Database->where('season != 0')->count('episodes'));

			CoreUtils::FixPath("/episodes/{$Pagination->page}");
			$heading = "Episodes";
			$title = "Page {$Pagination->page} - $heading";
			$Episodes = Episode::Get($Pagination->GetLimit());

			if (isset($_GET['js']))
				$Pagination->Respond(Episode::GetTableTbody($Episodes), '#episodes tbody');

			$settings = array(
				'title' => $title,
				'do-css',
				'js' => array('paginate',$do),
			);
			if (Permission::Sufficient('inspector'))
				$settings['js'] = array_merge(
					$settings['js'],
					array('moment-timezone',"$do-manage")
				);
			CoreUtils::LoadPage($settings);
		break;
		case "eqg":
			if (!regex_match(new RegExp('^([a-z\-]+|\d+)$'),$data))
				CoreUtils::NotFound();

			$assoc = array('friendship-games' => 3);
			$flip_assoc = array_flip($assoc);

			if (!is_numeric($data)){
				if (empty($assoc[$data]))
					CoreUtils::NotFound();
				$url = $data;
				$data = $assoc[$data];
			}
			else {
				$data = intval($data, 10);
				if (empty($flip_assoc[$data]))
					CoreUtils::NotFound();
				$url = $flip_assoc[$data];
			}

			Episode::LoadPage($data);
		break;
		case "about":
			if (POST_REQUEST){
				CSRFProtection::Protect();
				$StatCacheDuration = 5*ONE_HOUR;

				$_match = array();
				if (!empty($data) && regex_match(new RegExp('^stats-(posts|approvals)$'),$data,$_match)){
					$stat = $_match[1];
					$CachePath = APPATH."../stats/$stat.json";
					if (file_exists($CachePath) && filemtime($CachePath) > time() - $StatCacheDuration)
						CoreUtils::Respond(array('data' => JSON::Decode(file_get_contents($CachePath))));

					$Data = array('datasets' => array(), 'timestamp' => date('c'));
					$LabelFormat = 'YYYY-MM-DD 00:00:00';

					CoreUtils::CanIHas('Statistics');

					switch ($stat){
						case 'posts':
							$Labels = $Database->rawQuery(
								"SELECT key FROM
								(
									SELECT posted, to_char(posted,'$LabelFormat') AS key FROM requests
									WHERE posted > NOW() - INTERVAL '20 DAYS'
									UNION ALL
									SELECT posted, to_char(posted,'$LabelFormat') AS key FROM reservations
									WHERE posted > NOW() - INTERVAL '20 DAYS'
								) t
								GROUP BY key
								ORDER BY MIN(t.posted)");

							Statistics::ProcessLabels($Labels, $Data);

							$query =
								"SELECT
									to_char(MIN(posted),'$LabelFormat') AS key,
									COUNT(*)::INT AS cnt
								FROM table_name t
								WHERE posted > NOW() - INTERVAL '20 DAYS'
								GROUP BY to_char(posted,'$LabelFormat')
								ORDER BY MIN(posted)";
							$RequestData = $Database->rawQuery(str_replace('table_name', 'requests', $query));
							if (!empty($RequestData)){
								$Dataset = array('label' => 'Requests', 'clrkey' => 0);
								Statistics::ProcessUsageData($RequestData, $Dataset);
								$Data['datasets'][] = $Dataset;
							}
							$ReservationData = $Database->rawQuery(str_replace('table_name', 'reservations', $query));
							if (!empty($ReservationData)){
								$Dataset = array('label' => 'Reservations', 'clrkey' => 1);
								Statistics::ProcessUsageData($ReservationData, $Dataset);
								$Data['datasets'][] = $Dataset;
							}
						break;
						case 'approvals':
							$Labels = $Database->rawQuery(
								"SELECT to_char(timestamp,'$LabelFormat') AS key
								FROM log
								WHERE timestamp > NOW() - INTERVAL '20 DAYS' AND reftype = 'post_lock'
								GROUP BY key
								ORDER BY MIN(timestamp)");

							Statistics::ProcessLabels($Labels, $Data);

							$Approvals = $Database->rawQuery(
								"SELECT
									to_char(MIN(timestamp),'$LabelFormat') AS key,
									COUNT(*)::INT AS cnt
								FROM log
								WHERE timestamp > NOW() - INTERVAL '20 DAYS' AND reftype = 'post_lock'
								GROUP BY to_char(timestamp,'$LabelFormat')
								ORDER BY MIN(timestamp)"
							);
							if (!empty($Approvals)){
								$Dataset = array('label' => 'Approved posts');
								Statistics::ProcessUsageData($Approvals, $Dataset);
								$Data['datasets'][] = $Dataset;
							}
						break;
					}

					Statistics::PostprocessTimedData($Data);

					CoreUtils::CreateUploadFolder($CachePath);
					file_put_contents($CachePath, JSON::Encode($Data));

					CoreUtils::Respond(array('data' => $Data));
				}

				CoreUtils::NotFound();
			}
			CoreUtils::LoadPage(array(
				'title' => 'About',
				'do-css',
				'js' => array('Chart', $do),
			));
		break;
		case "logs":
			CoreUtils::Redirect(rtrim("/admin/logs/$data",'/'), AND_DIE);
		break;
		case "admin":
			if (!Permission::Sufficient('inspector'))
				CoreUtils::NotFound();

			$task = strtok($data, '/');
			$data = regex_replace(new RegExp('^[^/]*?(?:/(.*))?$'), '$1', $data);

			if (POST_REQUEST){
				switch ($task){
					case "logs":
						if (regex_match(new RegExp('^details/(\d+)'), $data, $_match)){
							$EntryID = intval($_match[1], 10);

							$MainEntry = $Database->where('entryid', $EntryID)->getOne('log');
							if (empty($MainEntry)) CoreUtils::Respond('Log entry does not exist');
							if (empty($MainEntry['refid'])) CoreUtils::Respond('There are no details to show');

							$Details = $Database->where('entryid', $MainEntry['refid'])->getOne("log__{$MainEntry['reftype']}");
							if (empty($Details)) CoreUtils::Respond('Failed to retrieve details');

							CoreUtils::Respond(Log::FormatEntryDetails($MainEntry['reftype'],$Details));
						}
						else CoreUtils::NotFound();
					break;
					case "usefullinks":
						if (regex_match(new RegExp('^([gs]et|del|make)(?:/(\d+))?$'), $data, $_match)){
							$action = $_match[1];
							$creating = $action === 'make';

							if (!$creating){
								$Link = $Database->where('id', $_match[2])->getOne('usefullinks');
								if (empty($Link))
									CoreUtils::Respond('The specified link does not exist');
							}

							switch ($action){
								case 'get':
									CoreUtils::Respond(array(
										'label' => $Link['label'],
										'url' => $Link['url'],
										'title' => $Link['title'],
										'minrole' => $Link['minrole'],
									));
								case 'del':
									if (!$Database->where('id', $Link['id'])->delete('usefullinks'))
										CoreUtils::Respond(ERR_DB_FAIL);

									CoreUtils::Respond(true);
								break;
								case 'make':
								case 'set':
									$data = array();

									if (empty($_POST['label']))
										CoreUtils::Respond('Link label is missing');
									$label = trim($_POST['label']);
									if ($creating || $Link['label'] !== $label){
										$ll = strlen($label);
										if ($ll < 3 || $ll > 40)
											CoreUtils::Respond('Link label must be between 3 and 40 characters long');
										CoreUtils::CheckStringValidity($label, 'Link label', INVERSE_PRINTABLE_ASCII_REGEX);
										$data['label'] = $label;
									}

									if (empty($_POST['url']))
										CoreUtils::Respond('Link URL is missing');
									$url = trim($_POST['url']);
									if ($creating || $Link['url'] !== $url){
										$ul = strlen($url);
										if (stripos($url, ABSPATH) === 0)
											$url = substr($url, strlen(ABSPATH)-1);
										if (!regex_match($REWRITE_REGEX,$url) && !regex_match(new RegExp('^#[a-z\-]+$'),$url)){
											if ($ul < 3 || $ul > 255)
												CoreUtils::Respond('Link URL must be between 3 and 255 characters long');
											if (!regex_match(new RegExp('^https?:\/\/.+$'), $url))
												CoreUtils::Respond('Link URL does not appear to be a valid link');
										}
										$data['url'] = $url;
									}

									if (!empty($_POST['title'])){
										$title = trim($_POST['title']);
										if ($creating || $Link['title'] !== $title){
											$tl = strlen($title);
											if ($tl < 3 || $tl > 255)
												CoreUtils::Respond('Link title must be between 3 and 255 characters long');
											CoreUtils::CheckStringValidity($title, 'Link title', INVERSE_PRINTABLE_ASCII_REGEX);
											$data['title'] = trim($title);
										}
									}
									else $data['title'] = '';

									if (empty($_POST['minrole']))
										CoreUtils::Respond('Minimum role is missing');
									$minrole = trim($_POST['minrole']);
									if ($creating || $Link['minrole'] !== $minrole){
										if (!isset(Permission::$ROLES_ASSOC[$minrole]) || !Permission::Sufficient('user', $minrole))
											CoreUtils::Respond('Minumum role is invalid');
										$data['minrole'] = $minrole;
									}

									if (empty($data))
										CoreUtils::Respond('Nothing was changed');
									$query = $creating
										? $Database->insert('usefullinks', $data)
										: $Database->where('id', $Link['id'])->update('usefullinks', $data);
									if (!$query)
										CoreUtils::Respond(ERR_DB_FAIL);

									CoreUtils::Respond(true);
								break;
								default: CoreUtils::NotFound();
							}
						}
						else if ($data === 'reorder'){
							if (!isset($_POST['list']))
								CoreUtils::Respond('Missing ordering information');

							$list = explode(',',regex_replace(new RegExp('[^\d,]'),'',trim($_POST['list'])));
							$order = 1;
							foreach ($list as $id){
								if (!$Database->where('id', $id)->update('usefullinks', array('order' => $order++)))
									CoreUtils::Respond("Updating link #$id failed, process halted");
							}

							CoreUtils::Respond(true);
						}
						else CoreUtils::NotFound();
					break;
					default:
						CoreUtils::NotFound();
				}
			}

			if (empty($task))
				CoreUtils::LoadPage(array(
					'title' => 'Admin Area',
					'do-css',
					'js' => array('Sortable','jquery.countdown',$do),
				));

			switch ($task){
				case "logs":
					CoreUtils::CanIHas('Pagination');
					$Pagination = new Pagination('admin/logs', 20, $Database->count('log'));

					CoreUtils::FixPath("/admin/logs/{$Pagination->page}");
					$heading = 'Logs';
					$title = "Page {$Pagination->page} - $heading";

					$LogItems = $Database
						->orderBy('timestamp')
						->orderBy('entryid')
						->get('log', $Pagination->GetLimit());

					if (isset($_GET['js']))
						$Pagination->Respond(Log::GetTbody($LogItems), '#logs tbody');

					CoreUtils::LoadPage(array(
						'title' => $title,
						'view' => "$do-logs",
						'css' => "$do-logs",
						'js' => array("$do-logs", 'paginate'),
					));
				break;
				default:
					CoreUtils::NotFound();
			}
		break;
		case "u":
			$do = 'user';
		case "user":
			$_match = array();

			if (strtolower($data) === 'immortalsexgod')
				$data = 'DJDavid98';

			if (POST_REQUEST){
				if (!Permission::Sufficient('inspector')) CoreUtils::Respond();
				CSRFProtection::Protect();

				if (empty($data)) CoreUtils::NotFound();

				if (regex_match(new RegExp('^newgroup/'.USERNAME_PATTERN.'$'),$data,$_match)){
					$targetUser = User::Get($_match[1], 'name');
					if (empty($targetUser))
						CoreUtils::Respond('User not found');

					if ($targetUser['id'] === $currentUser['id'])
						CoreUtils::Respond("You cannot modify your own group");
					if (!Permission::Sufficient($targetUser['role']))
						CoreUtils::Respond('You can only modify the group of users who are in the same or a lower-level group than you');
					if ($targetUser['role'] === 'ban')
						CoreUtils::Respond('This user is banished, and must be un-banished before changing their group.');

					if (!isset($_POST['newrole']))
						CoreUtils::Respond('The new group is not specified');
					$newgroup = trim($_POST['newrole']);
					if (empty(Permission::$ROLES[$newgroup]))
						CoreUtils::Respond('The specified group does not exist');
					if ($targetUser['role'] === $newgroup)
						CoreUtils::Respond(array('already_in' => true));

					User::UpdateRole($targetUser,$newgroup);

					CoreUtils::Respond(true);
				}
				else if (regex_match(new RegExp('^sessiondel/(\d+)$'),$data,$_match)){
					$Session = $Database->where('id', $_match[1])->getOne('sessions');
					if (empty($Session))
						CoreUtils::Respond('This session does not exist');
					if ($Session['user'] !== $currentUser['id'] && !Permission::Sufficient('inspector'))
						CoreUtils::Respond('You are not allowed to delete this session');

					if (!$Database->where('id', $Session['id'])->delete('sessions'))
						CoreUtils::Respond('Session could not be deleted');
					CoreUtils::Respond('Session successfully removed',1);
				}
				else if (regex_match(new RegExp('^(un-)?banish/'.USERNAME_PATTERN.'$'), $data, $_match)){
					$Action = (empty($_match[1]) ? 'Ban' : 'Un-ban').'ish';
					$action = strtolower($Action);
					$un = $_match[2];

					$targetUser = User::Get($un, 'name');
					if (empty($targetUser)) CoreUtils::Respond('User not found');

					if ($targetUser['id'] === $currentUser['id']) CoreUtils::Respond("You cannot $action yourself");
					if (Permission::Sufficient('inspector', $targetUser['role']))
						CoreUtils::Respond("You cannot $action people within the inspector or any higher group");
					if ($action == 'banish' && $targetUser['role'] === 'ban' || $action == 'un-banish' && $targetUser['role'] !== 'ban')
						CoreUtils::Respond("This user has already been {$action}ed");

					if (empty($_POST['reason']))
						CoreUtils::Respond('Please specify a reason');
					$reason = trim($_POST['reason']);
					$rlen = strlen($reason);
					if ($rlen < 5 || $rlen > 255)
						CoreUtils::Respond('Reason length must be between 5 and 255 characters');

					$changes = array('role' => $action == 'banish' ? 'ban' : 'user');
					$Database->where('id', $targetUser['id'])->update('users', $changes);
					Log::Action($action,array(
						'target' => $targetUser['id'],
						'reason' => $reason
					));
					$changes['role'] = Permission::$ROLES_ASSOC[$changes['role']];
					$changes['badge'] = Permission::LabelInitials($changes['role']);
					if ($action == 'banish') CoreUtils::Respond($changes);
					else CoreUtils::Respond("We welcome {$targetUser['name']} back with open hooves!", 1, $changes);
				}
				else CoreUtils::StatusCode(404, AND_DIE);
			}

			if (empty($data)){
				if ($signedIn) $un = $currentUser['name'];
				else $MSG = 'Sign in to view your settings';
			}
			else if (regex_match($USERNAME_REGEX, $data, $_match))
				$un = $_match[1];

			if (!isset($un)){
				if (!isset($MSG)) $MSG = 'Invalid username';
			}
			else $User = User::Get($un, 'name');

			if (empty($User)){
				if (isset($User) && $User === false){
					$MSG = "User does not exist";
					$SubMSG = "Check the name for typos and try again";
				}
				if (!isset($MSG)){
					$MSG = 'Local user data missing';
					if (!$signedIn){
						$exists = 'exsists on DeviantArt';
						if (isset($un)) $exists = "<a href='http://$un.deviantart.com/'>$exists</a>";
						$SubMSG = "If this user $exists, sign in to import their details.";
					}
				}
				$canEdit = $sameUser = false;
			}
			else {
				$sameUser = $signedIn && $User['id'] === $currentUser['id'];
				$canEdit = !$sameUser && Permission::Sufficient('inspector') && Permission::Sufficient($User['role']);
				$pagePath = "/@{$User['name']}";
				CoreUtils::FixPath($pagePath);
			}

			if (isset($MSG)) CoreUtils::StatusCode(404);
			else {
				if ($sameUser){
					$CurrentSession = $currentUser['Session'];
					$Database->where('id != ?',array($CurrentSession['id']));
				}
				$Sessions = $Database
					->where('user',$User['id'])
					->orderBy('lastvisit','DESC')
					->get('sessions',null,'id,created,lastvisit,platform,browser_name,browser_ver,user_agent,scope');
			}

			$settings = array(
				'title' => !isset($MSG) ? ($sameUser?'Your':CoreUtils::Posess($User['name'])).' '.($sameUser || $canEdit?'account':'profile') : 'Account',
				'no-robots',
				'do-css',
				'js' => array('user'),
			);
			if ($canEdit) $settings['js'][] = 'user-manage';
			CoreUtils::LoadPage($settings);
		break;
		case "colourguides":
		case "colourguide":
		case "colorguides":
			$do = 'colorguide';
		case "colorguide":
			require_once "includes/CGUtils.php";

			$SpriteRelPath = '/img/cg/';
			$SpritePath = APPATH.substr($SpriteRelPath,1);

			if (POST_REQUEST || (isset($_GET['s']) && $data === "gettags")){
				if (!Permission::Sufficient('inspector')) CoreUtils::Respond();
				if (POST_REQUEST) CSRFProtection::Protect();

				$EQG = isset($_REQUEST['eqg']) ? 1 : 0;
				$AppearancePage = isset($_POST['APPEARANCE_PAGE']);

				switch ($data){
					case 'gettags':
						if (isset($_POST['not']) && is_numeric($_POST['not']))
							$not_tid = intval($_POST['not'], 10);
						if (!empty($_POST['action']) && $_POST['action'] === 'synon'){
							$Tag = $CGDb->where('tid',$not_tid)->where('"synonym_of" IS NOT NULL')->getOne('tags');
							if (!empty($Tag)){
								$Syn = get_tag_synon($Tag,'name');
								CoreUtils::Respond("This tag is already a synonym of <strong>{$Syn['name']}</strong>.<br>Would you like to remove the synonym?",0,array('undo' => true));
							}
						}

						$viaTypeahead = !empty($_GET['s']);
						$limit = null;
						$cols = "tid, name, type";
						if ($viaTypeahead){
							if (!regex_match($TAG_NAME_REGEX, $_GET['s']))
								typeahead_results('[]');

							$query = trim(strtolower($_GET['s']));
							$TagCheck = ep_tag_name_check($query);
							if ($TagCheck !== false)
								$query = $TagCheck;
							$CGDb->where('name',"%$query%",'LIKE');
							$limit = 5;
							$cols = "tid, name, CONCAT('typ-', type) as type";
						}
						else $CGDb->orderBy('type','ASC')->where('"synonym_of" IS NULL');

						if (isset($_POST['not']) && is_numeric($_POST['not']))
							$CGDb->where('tid',$not_tid,'!=');
						$Tags = $CGDb->orderBy('name','ASC')->get('tags',$limit,"$cols, uses, synonym_of");
						if ($viaTypeahead)
							foreach ($Tags as $i => $t){
								if (empty($t['synonym_of']))
									continue;
								$Syn = $CGDb->where('tid', $t['synonym_of'])->getOne('tags','name');
								if (!empty($Syn))
									$Tags[$i]['synonym_target'] = $Syn['name'];
							};

						typeahead_results(empty($Tags) ? '[]' : $Tags);
					break;
					case 'full':
						if (!isset($_REQUEST['reorder']))
							CoreUtils::NotFound();

						if (!Permission::Sufficient('inspector'))
							CoreUtils::Respond();
						if (empty($_POST['list']))
							CoreUtils::Respond('The list of IDs is missing');

						$list = trim($_POST['list']);
						if (!regex_match(new RegExp('^\d+(?:,\d+)+$'), $list))
							CoreUtils::Respond('The list of IDs is not formatted properly');

						reorder_appearances($list);

						CoreUtils::Respond(array('html' => render_full_list_html(get_appearances($EQG,null,'id,label'), true, NOWRAP)));
					break;
					case "export":
						if (!Permission::Sufficient('inspector'))
							CoreUtils::NotFound();
						$JSON = array(
							'Appearances' => array(),
							'Tags' => array(),
						);

						$Tags = $CGDb->orderBy('tid','ASC')->get('tags');
						if (!empty($Tags)) foreach ($Tags as $t){
							$JSON['Tags'][$t['tid']] = $t;
						}

						$Appearances = get_appearances(null);
						if (!empty($Appearances)) foreach ($Appearances as $p){
							$AppendAppearance = $p;

							$AppendAppearance['notes'] = regex_replace(new RegExp('(\r\n|\r|\n)'),"\n",$AppendAppearance['notes']);

							$AppendAppearance['ColorGroups'] = array();
							$ColorGroups = get_cgs($p['id']);
							if (!empty($ColorGroups)) foreach ($ColorGroups as $cg){
								$AppendColorGroup = $cg;
								unset($AppendColorGroup['ponyid']);

								$AppendColorGroup['Colors'] = array();
								$Colors = get_colors($cg['groupid']);
								if (!empty($Colors)) foreach ($Colors as $c){
									unset($c['groupid']);
									$AppendColorGroup['Colors'][] = $c;
								}

								$AppendAppearance['ColorGroups'][$cg['groupid']] = $AppendColorGroup;
							}

							$AppendAppearance['TagIDs'] = array();
							$TagIDs = get_tags($p['id'],null,null,true);
							if (!empty($TagIDs))
								foreach ($TagIDs as $t)
									$AppendAppearance['TagIDs'][] = $t['tid'];

							$JSON['Appearances'][$p['id']] = $AppendAppearance;
						}

						$data = JSON::Encode($JSON, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
						$data = preg_replace_callback('/^\s+/m', function($match){
							return str_pad('',strlen($match[0])/4,"\t", STR_PAD_LEFT);
						}, $data);

						header('Content-Type: application/octet-stream');
						header('Content-Transfer-Encoding: Binary');
						header('Content-disposition: attachment; filename="mlpvc-colorguide.json"');
						die($data);
					break;
				}

				$_match = array();
				if (regex_match(new RegExp('^(rename|delete|make|(?:[gs]et|del)(?:sprite|cgs)?|tag|untag|clearrendercache|applytemplate)(?:/(\d+))?$'), $data, $_match)){
					$action = $_match[1];
					$creating = $action === 'make';

					if (!$creating){
						$AppearanceID = intval($_match[2], 10);
						if (strlen($_match[2]) === 0)
							CoreUtils::Respond('Missing appearance ID');
						$Appearance = $CGDb->where('id', $AppearanceID)->where('ishuman', $EQG)->getOne('appearances');
						if (empty($Appearance))
							CoreUtils::Respond("The specified appearance does not exist");
					}
					else $Appearance = array('id' => null);

					switch ($action){
						case "get":
							CoreUtils::Respond(array(
								'label' => $Appearance['label'],
								'notes' => $Appearance['notes'],
								'cm_favme' => !empty($Appearance['cm_favme']) ? "http://fav.me/{$Appearance['cm_favme']}" : null,
								'cm_preview' => $Appearance['cm_preview'],
								'cm_dir' => isset($Appearance['cm_dir'])
									? ($Appearance['cm_dir'] === CM_DIR_HEAD_TO_TAIL ? 'ht' : 'th')
									: null
							));
						break;
						case "set":
						case "make":
							$data = array(
								'ishuman' => $EQG,
							    'cm_favme' => null,
							);

							if (empty($_POST['label']))
								CoreUtils::Respond('Label is missing');
							$label = trim($_POST['label']);
							$ll = strlen($label);
							CoreUtils::CheckStringValidity($label, "Appearance name", INVERSE_PRINTABLE_ASCII_REGEX);
							if ($ll < 4 || $ll > 70)
								CoreUtils::Respond('Appearance name must be beetween 4 and 70 characters long');
							if ($creating && $CGDb->where('label', $label)->has('appearances'))
								CoreUtils::Respond('An appearance already esists with this name');
							$data['label'] = $label;

							if (!empty($_POST['notes'])){
								$notes = trim($_POST['notes']);
								CoreUtils::CheckStringValidity($label, "Appearance notes", INVERSE_PRINTABLE_ASCII_REGEX);
								if ($Appearance['id'] === 0)
									$notes = trim(CoreUtils::SanitizeHtml($notes));
								if (strlen($notes) > 1000 && ($creating || $Appearance['id'] !== 0))
									CoreUtils::Respond('Appearance notes cannot be longer than 1000 characters');
								if ($creating || $notes !== $Appearance['notes'])
									$data['notes'] = $notes;
							}
							else $data['notes'] = '';

							if (!empty($_POST['cm_favme'])){
								$cm_favme = trim($_POST['cm_favme']);
								try {
									CoreUtils::CanIHas('Image');
									$Image = new Image($cm_favme, array('fav.me','dA'));
									$data['cm_favme'] = $Image->id;
								}
								catch (MismatchedProviderException $e){
									CoreUtils::Respond('The vector must be on DeviantArt, '.$e->getActualProvider().' links are not allowed');
								}
								catch (Exception $e){ CoreUtils::Respond("Cutie Mark link issue: ".$e->getMessage()); }

								if (empty($_POST['cm_dir']))
									CoreUtils::Respond('Cutie mark orientation must be set if a link is provided');
								if ($_POST['cm_dir'] !== 'th' && $_POST['cm_dir'] !== 'ht')
									CoreUtils::Respond('Invalid cutie mark orientation');
								$cm_dir = $_POST['cm_dir'] === 'ht' ? CM_DIR_HEAD_TO_TAIL : CM_DIR_TAIL_TO_HEAD;
								if ($creating || $Appearance['cm_dir'] !== $cm_dir)
									$data['cm_dir'] = $cm_dir;

								$cm_preview = trim($_POST['cm_preview']);
								if (empty($cm_preview))
									$data['cm_preview'] = null;
								else if ($creating || $cm_preview !== $Appearance['cm_preview']){
									try {
										CoreUtils::CanIHas('Image');
										$Image = new Image($cm_preview);
										$data['cm_preview'] = $Image->preview;
									}
									catch (Exception $e){ CoreUtils::Respond("Cutie Mark preview issue: ".$e->getMessage()); }
								}
							}
							else {
								$data['cm_dir'] = null;
								$data['cm_preview'] = null;
							}

							$query = $creating
								? $CGDb->insert('appearances', $data, 'id')
								: $CGDb->where('id', $Appearance['id'])->update('appearances', $data);
							if (!$query)
								CoreUtils::Respond(ERR_DB_FAIL);

							if ($creating){
								$data['id'] = $query;
								$response = array(
									'message' => 'Appearance added successfully',
									'id' => $query,
								);
								if (isset($_POST['template'])){
									try {
										apply_template($query, $EQG);
									}
									catch (Exception $e){
										$response['message'] .= ", but applying the template failed";
										$response['info'] = "The common color groups could not be added.<br>Reason: ".$e->getMessage();
										CoreUtils::Respond($response, 1);
									}
								}
								CoreUtils::Respond($response);
							}
							else {
								clear_rendered_image($Appearance['id']);
								if ($AppearancePage)
									CoreUtils::Respond(true);
							}

							$Appearance = array_merge($Appearance, $data);
							CoreUtils::Respond(array(
								'label' => $Appearance['label'],
								'notes' => get_notes_html($Appearance, NOWRAP),
							));
						break;
						case "delete":
							if ($Appearance['id'] === 0)
								CoreUtils::Respond('This appearance cannot be deleted');

							if (!$CGDb->where('id', $Appearance['id'])->delete('appearances'))
								CoreUtils::Respond(ERR_DB_FAIL);

							$fpath = APPATH."img/cg/{$Appearance['id']}.png";
							if (file_exists($fpath))
								unlink($fpath);

							clear_rendered_image($Appearance['id']);

							CoreUtils::Respond('Appearance removed', 1);
						break;
						case "getcgs":
							$cgs = get_cgs($Appearance['id'],'groupid, label');
							if (empty($cgs))
								CoreUtils::Respond('This appearance does not have any color groups');
							CoreUtils::Respond(array('cgs' => $cgs));
						break;
						case "setcgs":
							if (empty($_POST['cgs']))
								CoreUtils::Respond("$Color group order data missing");

							$groups = array_unique(array_map('intval',explode(',',$_POST['cgs'])));
							foreach ($groups as $part => $GroupID){
								if (!$CGDb->where('groupid', $GroupID)->has('colorgroups'))
									CoreUtils::Respond("There's no group with the ID of  $GroupID");

								$CGDb->where('groupid', $GroupID)->update('colorgroups',array('order' => $part));
							}

							clear_rendered_image($Appearance['id']);

							CoreUtils::Respond(array('cgs' => get_colors_html($Appearance['id'], NOWRAP, !$AppearancePage, $AppearancePage)));
						break;
						case "delsprite":
						case "getsprite":
						case "setsprite":
							$fname = $Appearance['id'].'.png';
							$finalpath = $SpritePath.$fname;

							switch ($action){
								case "setsprite":
									process_uploaded_image('sprite', $finalpath, array('image/png'), 100);
									clear_rendered_image($Appearance['id']);
								break;
								case "delsprite":
									if (!file_exists($finalpath))
										CoreUtils::Respond('No sprite file found');

									if (!unlink($finalpath))
										CoreUtils::Respond('File could not be deleted');

									CoreUtils::Respond(array('sprite' => get_sprite_url($Appearance, DEFAULT_SPRITE)));
								break;
							}

							CoreUtils::Respond(array("path" => "$SpriteRelPath$fname?".filemtime($finalpath)));
						break;
						case "clearrendercache":
							if (!clear_rendered_image($Appearance['id']))
								CoreUtils::Respond('Cache could not be cleared');

							CoreUtils::Respond('Cached image removed, the image will be re-generated on the next request', 1);
						break;
						case "tag":
						case "untag":
							if ($Appearance['id'] === 0)
								CoreUtils::Respond("This appearance cannot be tagged");

							switch ($action){
								case "tag":
									if (empty($_POST['tag_name']))
										CoreUtils::Respond('Tag name is not specified');
									$tag_name = strtolower(trim($_POST['tag_name']));
									if (!regex_match($TAG_NAME_REGEX,$tag_name))
										CoreUtils::Respond('Invalid tag name');

									$TagCheck = ep_tag_name_check($tag_name);
									if ($TagCheck !== false)
										$tag_name = $TagCheck;

									$Tag = $CGDb->where('name',$tag_name)->getOne('tags');
									if (empty($Tag))
										CoreUtils::Respond("The tag $tag_name does not exist.<br>Would you like to create it?",0,array(
											'cancreate' => $tag_name,
											'typehint' => $TagCheck !== false ? 'ep' : null,
										));

									if ($CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->has('tagged'))
										CoreUtils::Respond('This appearance already has this tag');

									if (!$CGDb->insert('tagged',array(
										'ponyid' => $Appearance['id'],
										'tid' => $Tag['tid'],
									))) CoreUtils::Respond(ERR_DB_FAIL);
								break;
								case "untag":
									if (!isset($_POST['tag']) || !is_numeric($_POST['tag']))
										CoreUtils::Respond('Tag ID is not specified');
									$Tag = $CGDb->where('tid',$_POST['tag'])->getOne('tags');
									if (empty($Tag))
										CoreUtils::Respond('This tag does not exist');
									if (!empty($Tag['synonym_of'])){
										$Syn = get_tag_synon($Tag,'name');
										CoreUtils::Respond('Synonym tags cannot be removed from appearances directly. '.
										        "If you want to remove this tag you must remove <strong>{$Syn['name']}</strong> or the synonymization.");
									}

									if ($CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->has('tagged')){
										if (!$CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->delete('tagged'))
											CoreUtils::Respond(ERR_DB_FAIL);
									}
								break;
							}

							update_tag_count($Tag['tid']);
							if (isset($GroupTagIDs_Assoc[$Tag['tid']]))
								get_sort_reorder_appearances($EQG);

							$response = array('tags' => get_tags_html($Appearance['id'], NOWRAP));
							if ($AppearancePage && $Tag['type'] === 'ep'){
								$response['needupdate'] = true;
								$response['eps'] = get_episode_appearances($Appearance['id'], NOWRAP);
							}
							CoreUtils::Respond($response);
						break;
						case "applytemplate":
							try {
								apply_template($Appearance['id'], $EQG);
							}
							catch (Exception $e){
								CoreUtils::Respond("Applying the template failed. Reason: ".$e->getMessage());
							}

							CoreUtils::Respond(array('cgs' => get_colors_html($Appearance['id'], NOWRAP, !$AppearancePage, $AppearancePage)));
						break;
						default: CoreUtils::StatusCode(404, AND_DIE);
					}
				}
				else if (regex_match(new RegExp('^([gs]et|make|del|merge|recount|(?:un)?synon)tag(?:/(\d+))?$'), $data, $_match)){
					$action = $_match[1];

					if ($action === 'recount'){
						if (empty($_POST['tagids']))
							CoreUtils::Respond('Missing list of tags to update');

						$tagIDs = array_map('intval', explode(',',trim($_POST['tagids'])));
						$counts = array();
						$updates = 0;
						foreach ($tagIDs as $tid){
							if (get_actual_tag($tid,'tid',RETURN_AS_BOOL)){
								$result = update_tag_count($tid, true);
								if ($result['status'])
									$updates++;
								$counts[$tid] = $result['count'];
							}
						}

						CoreUtils::Respond(
							(
								!$updates
								? 'There was no change in the tag usage counts'
								: "$updates tag".($updates!==1?"s'":"'s").' use count'.($updates!==1?'s were':' was').' updated'
							),
							1,
							array('counts' => $counts)
						);
					}

					$getting = $action === 'get';
					$deleting = $action === 'del';
					$new = $action === 'make';
					$merging = $action === 'merge';
					$synoning = $action === 'synon';
					$unsynoning = $action === 'unsynon';

					if (!$new){
						if (!isset($_match[2]))
							CoreUtils::Respond('Missing tag ID');
						$TagID = intval($_match[2], 10);
						$Tag = $CGDb->where('tid', $TagID)->getOne('tags',isset($query) ? 'tid, name, type':'*');
						if (empty($Tag))
							CoreUtils::Respond("This tag does not exist");

						if ($getting) CoreUtils::Respond($Tag);

						if ($deleting){
							if (!isset($_POST['sanitycheck'])){
								$tid = !empty($Tag['synonym_of']) ? $Tag['synonym_of'] : $Tag['tid'];
								$Uses = $CGDb->where('tid',$tid)->count('tagged');
								if ($Uses > 0)
									CoreUtils::Respond('<p>This tag is currently used on '.CoreUtils::MakePlural('appearance',$Uses,PREPEND_NUMBER).'</p><p>Deleting will <strong class="color-red">permanently remove</strong> the tag from those appearances!</p><p>Are you <em class="color-red">REALLY</em> sure about this?</p>',0,array('confirm' => true));
							}

							if (!$CGDb->where('tid', $Tag['tid'])->delete('tags'))
								CoreUtils::Respond(ERR_DB_FAIL);

							if (isset($GroupTagIDs_Assoc[$Tag['tid']]))
								get_sort_reorder_appearances($EQG);

							CoreUtils::Respond('Tag deleted successfully', 1, $AppearancePage && $Tag['type'] === 'ep' ? array(
								'needupdate' => true,
								'eps' => get_episode_appearances($Appearance['id'], NOWRAP),
							) : null);
						}
					}
					$data = array();

					if ($merging || $synoning){
						if ($synoning && !empty($Tag['synonym_of']))
							CoreUtils::Respond('This tag is already synonymized with a different tag');

						if (empty($_POST['targetid']))
							CoreUtils::Respond('Missing target tag ID');
						$Target = $CGDb->where('tid', intval($_POST['targetid'], 10))->getOne('tags');
						if (empty($Target))
							CoreUtils::Respond('Target tag does not exist');
						if (!empty($Target['synonym_of']))
							CoreUtils::Respond('Synonym tags cannot be synonymization targets');

						$_TargetTagged = $CGDb->where('tid', $Target['tid'])->get('tagged',null,'ponyid');
						$TargetTagged = array();
						foreach ($_TargetTagged as $tg)
							$TargetTagged[] = $tg['ponyid'];

						$Tagged = $CGDb->where('tid', $Tag['tid'])->get('tagged',null,'ponyid');
						foreach ($Tagged as $tg){
							if (in_array($tg['ponyid'], $TargetTagged)) continue;

							if (!$CGDb->insert('tagged',array(
								'tid' => $Target['tid'],
								'ponyid' => $tg['ponyid']
							))) CoreUtils::Respond('Tag '.($merging?'merging':'synonimizing')." failed, please re-try.<br>Technical details: ponyid={$tg['ponyid']} tid={$Target['tid']}");
						}
						if ($merging)
							// No need to delete "tagged" table entries, constraints do it for us
							$CGDb->where('tid', $Tag['tid'])->delete('tags');
						else {
							$CGDb->where('tid', $Tag['tid'])->delete('tagged');
							$CGDb->where('tid', $Tag['tid'])->update('tags', array('synonym_of' => $Target['tid'], 'uses' => 0));
						}

						update_tag_count($Target['tid']);
						CoreUtils::Respond('Tags successfully '.($merging?'merged':'synonymized'), 1, $synoning ? array('target' => $Target) : null);
					}
					else if ($unsynoning){
						if (empty($Tag['synonym_of']))
							CoreUtils::Respond(true);

						$keep_tagged = isset($_POST['keep_tagged']);
						$uses = 0;
						if ($keep_tagged){
							$Target = $CGDb->where('tid', $Tag['synonym_of'])->getOne('tags','tid');
							if (!empty($Target)){
								$TargetTagged = $CGDb->where('tid', $Target['tid'])->get('tagged',null,'ponyid');
								foreach ($TargetTagged as $tg){
									if (!$CGDb->insert('tagged',array(
										'tid' => $Tag['tid'],
										'ponyid' => $tg['ponyid']
									))) CoreUtils::Respond("Tag synonym removal process failed, please re-try.<br>Technical details: ponyid={$tg['ponyid']} tid={$Tag['tid']}");
									$uses++;
								}
							}
							else $keep_tagged = false;
						}

						if (!$CGDb->where('tid', $Tag['tid'])->update('tags', array('synonym_of' => null, 'uses' => $uses)))
							CoreUtils::Respond(ERR_DB_FAIL);

						CoreUtils::Respond(array('keep_tagged' => $keep_tagged));
					}

					$name = isset($_POST['name']) ? strtolower(trim($_POST['name'])) : null;
					$nl = !empty($name) ? strlen($name) : 0;
					if ($nl < 3 || $nl > 30)
						CoreUtils::Respond("Tag name must be between 3 and 30 characters");
					if ($name[0] === '-')
						CoreUtils::Respond('Tag name cannot start with a dash');
					CoreUtils::CheckStringValidity($name,'Tag name',INVERSE_TAG_NAME_PATTERN);
					$sanitized_name = regex_replace(new RegExp('[^a-z\d]'),'',$name);
					if (regex_match(new RegExp('^(b+[a4]+w*d+|g+[uo0]+d+|(?:b+[ae3]+|w+[o0]+r+[s5]+[t7])[s5]+t+)(e+r+|e+s+t+)?p+[o0]+[wh]*n+[ye3]*$'),$sanitized_name))
						CoreUtils::Respond('Highly opinion-based tags are not allowed');
					$data['name'] = $name;

					$epTagName = ep_tag_name_check($data['name']);
					$surelyAnEpisodeTag = $epTagName !== false;
					if (empty($_POST['type'])){
						if ($surelyAnEpisodeTag)
							$data['name'] = $epTagName;
						$data['type'] = $epTagName === false ? null : 'ep';
					}
					else {
						$type = trim($_POST['type']);
						if (!in_array($type, $TAG_TYPES))
							CoreUtils::Respond("Invalid tag type: $type");

						if ($type == 'ep'){
							if (!$surelyAnEpisodeTag)
								CoreUtils::Respond('Episode tags must be in the format of <strong>s##e##[-##]</strong> where # represents a number<br>Allowed seasons: 1-8, episodes: 1-26');
							$data['name'] = $epTagName;
						}
						else if ($surelyAnEpisodeTag)
							$type = $ep;
						$data['type'] = $type;
					}

					if (!$new) $CGDb->where('tid',$Tag['tid'],'!=');
					if ($CGDb->where('name', $data['name'])->where('type', $data['type'])->has('tags') || $data['name'] === 'wrong cutie mark')
						CoreUtils::Respond("A tag with the same name and type already exists");

					if (empty($_POST['title'])) $data['title'] = null;
					else {
						$title = trim($_POST['title']);
						$tl = strlen($title);
						if ($tl > 255)
							CoreUtils::Respond("Your title exceeds the 255 character limit by ".($tl-255)." characters.");
						$data['title'] = $title;
					}

					if ($new){
						$TagID = $CGDb->insert('tags', $data, 'tid');
						if (!$TagID) CoreUtils::Respond(ERR_DB_FAIL);
						$data['tid'] = $TagID;

						if (!empty($_POST['addto']) && is_numeric($_POST['addto'])){
							$AppearanceID = intval($_POST['addto'], 10);
							if ($AppearanceID === 0)
								CoreUtils::Respond("The tag was created, <strong>but</strong> it could not be added to the appearance because it can't be tagged.", 1);

							$Appearance = $CGDb->where('id', $AppearanceID)->getOne('appearances');
							if (empty($Appearance))
								CoreUtils::Respond("The tag was created, <strong>but</strong> it could not be added to the appearance (<a href='/{$color}guide/appearance/$AppearanceID'>#$AppearanceID</a>) because it doesn't seem to exist. Please try adding the tag manually.", 1);

							if (!$CGDb->insert('tagged',array(
								'tid' => $data['tid'],
								'ponyid' => $Appearance['id']
							))) CoreUtils::Respond(ERR_DB_FAIL);
							update_tag_count($data['tid']);
							CoreUtils::Respond(array('tags' => get_tags_html($Appearance['id'], NOWRAP)));
						}
					}
					else {
						$CGDb->where('tid', $Tag['tid'])->update('tags', $data);
						$data = array_merge($Tag, $data);
					}

					CoreUtils::Respond($data);
				}
				else if (regex_match(new RegExp('^([gs]et|make|del)cg(?:/(\d+))?$'), $data, $_match)){
					$setting = $_match[1] === 'set';
					$getting = $_match[1] === 'get';
					$deleting = $_match[1] === 'del';
					$new = $_match[1] === 'make';

					if (!$new){
						if (empty($_match[2]))
							CoreUtils::Respond('Missing color group ID');
						$GroupID = intval($_match[2], 10);
						$Group = $CGDb->where('groupid', $GroupID)->getOne('colorgroups');
						if (empty($GroupID))
							CoreUtils::Respond("There's no $color group with the ID of $GroupID");

						if ($getting){
							$Group['Colors'] = get_colors($Group['groupid']);
							CoreUtils::Respond($Group);
						}

						if ($deleting){
							if (!$CGDb->where('groupid', $Group['groupid'])->delete('colorgroups'))
								CoreUtils::Respond(ERR_DB_FAIL);
							CoreUtils::Respond("$Color group deleted successfully", 1);
						}
					}
					$data = array();

					if (empty($_POST['label']))
						CoreUtils::Respond('Please specify a group name');
					$name = $_POST['label'];
					CoreUtils::CheckStringValidity($name, "$Color group name", INVERSE_PRINTABLE_ASCII_REGEX);
					$nl = strlen($name);
					if ($nl < 2 || $nl > 30)
						CoreUtils::Respond('The group name must be between 2 and 30 characters in length');
					$data['label'] = $name;

					if (!empty($_POST['major'])){
						$major = true;

						if (empty($_POST['reason']))
							CoreUtils::Respond('Please specify a reason');
						$reason = $_POST['reason'];
						CoreUtils::CheckStringValidity($reason, "Change reason", INVERSE_PRINTABLE_ASCII_REGEX);
						$rl = strlen($reason);
						if ($rl < 1 || $rl > 255)
							CoreUtils::Respond('The reason must be between 1 and 255 characters in length');
					}

					if ($new){
						if (!isset($_POST['ponyid']) || !is_numeric($_POST['ponyid']))
							CoreUtils::Respond('Missing appearance ID');
						$AppearanceID = intval($_POST['ponyid'], 10);
						$Appearance = $CGDb->where('id', $AppearanceID)->where('ishuman', $EQG)->getOne('appearances');
						if (empty($Appearance))
							CoreUtils::Respond('The specified appearance odes not exist');
						$data['ponyid'] = $AppearanceID;

						// Attempt to get order number of last color group for the appearance
						order_cgs();
						$LastGroup = get_cgs($AppearanceID, '"order"', 'DESC', 1);
						$data['order'] =  !empty($LastGroup['order']) ? $LastGroup['order']+1 : 1;

						$GroupID = $CGDb->insert('colorgroups', $data, 'groupid');
						if (!$GroupID)
							CoreUtils::Respond(ERR_DB_FAIL);
						$Group = array('groupid' => $GroupID);
					}
					else $CGDb->where('groupid', $Group['groupid'])->update('colorgroups', $data);


					if (empty($_POST['Colors']))
						CoreUtils::Respond("Missing list of {$color}s");
					$recvColors = JSON::Decode($_POST['Colors'], true);
					if (empty($recvColors))
						CoreUtils::Respond("Missing list of {$color}s");
					$colors = array();
					foreach ($recvColors as $part => $c){
						$append = array('order' => $part);
						$index = "(index: $part)";

						if (empty($c['label']))
							CoreUtils::Respond("You must specify a $color name $index");
						$label = trim($c['label']);
						CoreUtils::CheckStringValidity($label, "$Color $index name", INVERSE_PRINTABLE_ASCII_REGEX);
						$ll = strlen($label);
						if ($ll < 3 || $ll > 30)
							CoreUtils::Respond("The $color name must be between 3 and 30 characters in length $index");
						$append['label'] = $label;

						if (empty($c['hex']))
							CoreUtils::Respond("You must specify a $color code $index");
						$hex = trim($c['hex']);
						if (!$HEX_COLOR_PATTERN->match($hex, $_match))
							CoreUtils::Respond("HEX $color is in an invalid format $index");
						$append['hex'] = '#'.strtoupper($_match[1]);

						$colors[] = $append;
					}
					if (!$new)
						$CGDb->where('groupid', $Group['groupid'])->delete('colors');
					$colorError = false;
					foreach ($colors as $i => $c){
						$c['groupid'] = $Group['groupid'];
						if (!$CGDb->insert('colors', $c) && !$colorError)
							$colorError = true;
					}
					if ($colorError)
						CoreUtils::Respond("There were some issues while saving some of the colors. Please let the developer know about this error, so he can look into why this might've happened.");

					$colon = !$AppearancePage;
					$outputNames = $AppearancePage;

					if ($new) $response = array('cgs' => get_colors_html($Appearance['id'], NOWRAP, $colon, $outputNames));
					else $response = array('cg' => get_cg_html($Group['groupid'], NOWRAP, $colon, $outputNames));

					$AppearanceID = $new ? $Appearance['id'] : $Group['ponyid'];
					if (isset($major)){
						Log::Action('color_modify',array(
							'ponyid' => $AppearanceID,
							'reason' => $reason,
						));
						$response['update'] = get_update_html($AppearanceID);
					}
					clear_rendered_image($AppearanceID);

					if (isset($_POST['APPEARANCE_PAGE']))
						$response['cm_img'] = "/{$color}guide/appearance/$AppearanceID.svg?t=".time();
					else $response['notes'] = get_notes_html($CGDb->where('id', $AppearanceID)->getOne('appearances'),  NOWRAP);

					CoreUtils::Respond($response);
				}
				else CoreUtils::NotFound();
			}

			if (regex_match(new RegExp('^tags'),$data)){
				CoreUtils::CanIHas('Pagination');
				$Pagination = new Pagination("{$color}guide/tags", 20, $CGDb->count('tags'));

				CoreUtils::FixPath("/{$color}guide/tags/{$Pagination->page}");
				$heading = "Tags";
				$title = "Page $Pagination->page - $heading - $Color Guide";

				$Tags = get_tags(null,$Pagination->GetLimit(), true);

				if (isset($_GET['js']))
					$Pagination->Respond(get_taglist_html($Tags, NOWRAP), '#tags tbody');

				$js = array('paginate');
				if (Permission::Sufficient('inspector'))
					$js[] = "$do-tags";

				CoreUtils::LoadPage(array(
					'title' => $title,
					'heading' => $heading,
					'view' => "$do-tags",
					'css' => "$do-tags",
					'js' => $js,
				));
			}

			if (regex_match(new RegExp('^changes'),$data)){
				CoreUtils::CanIHas('Pagination');
				$Pagination = new Pagination("{$color}guide/changes", 50, $Database->count('log__color_modify'));

				CoreUtils::FixPath("/{$color}guide/changes/{$Pagination->page}");
				$heading = "Major $Color Changes";
				$title = "Page $Pagination->page - $heading - $Color Guide";

				$Changes = get_updates(null, $Pagination->GetLimitString());

				if (isset($_GET['js']))
					$Pagination->Respond(render_changes_html($Changes, NOWRAP, SHOW_APPEARANCE_NAMES), '#changes');

				CoreUtils::LoadPage(array(
					'title' => $title,
					'heading' => $heading,
					'view' => "$do-changes",
					'css' => "$do-changes",
					'js' => 'paginate',
				));
			}

			$EQG = $EQG_URL_PATTERN->match($data) ? 1 : 0;
			if ($EQG)
				$data = $EQG_URL_PATTERN->replace('', $data);
			$CGPath = "/{$color}guide".($EQG?'/eqg':'');

			$GUIDE_MANAGE_JS = array(
				'jquery.uploadzone',
				'twitter-typeahead',
				'handlebars-v3.0.3',
				'Sortable',
				'ace',
				'ace-mode-colorguide',
				'ace-theme-colorguide',
				"$do-tags",
				"$do-manage",
			);
			$GUIDE_MANAGE_CSS = array(
				'ace-theme-colorguide',
				"$do-manage",
			);

			$_match = array();
			if (regex_match(new RegExp('^appearance/(?:[A-Za-z\d\-]+-)?(\d+)(?:\.(png|svg))?'),$data,$_match)){
				$Appearance = $CGDb->where('id', (int)$_match[1])->where('ishuman', $EQG)->getOne('appearances');
				if (empty($Appearance))
					CoreUtils::NotFound();

				$asFile = !empty($_match[2]);
				if ($asFile){
					switch ($_match[2]){
						case 'png': render_appearance_png($Appearance);
						case 'svg': render_cm_direction_svg($Appearance['id'], $Appearance['cm_dir']);
					}
					# rendering functions internally call die(), so execution stops here #
				}

				$SafeLabel = trim(regex_replace(new RegExp('-+'),'-',regex_replace(new RegExp('[^A-Za-z\d\-]'),'-',$Appearance['label'])),'-');
				CoreUtils::FixPath("$CGPath/appearance/$SafeLabel-{$Appearance['id']}");
				$title = $heading = $Appearance['label'];
				if ($Appearance['id'] === 0 && $color !== 'color')
					$title = str_replace('color',$color,$title);

				$Changes = get_updates($Appearance['id']);

				$settings = array(
					'title' => "$title - $Color Guide",
					'heading' => $heading,
					'view' => "$do-single",
					'css' => array($do, "$do-single"),
					'js' => array('jquery.qtip', 'jquery.ctxmenu', $do, "$do-single"),
				);
				if (Permission::Sufficient('inspector')){
					$settings['css'] = array_merge($settings['css'], $GUIDE_MANAGE_CSS);
					$settings['js'] = array_merge($settings['js'],$GUIDE_MANAGE_JS);
				}
				CoreUtils::LoadPage($settings);
			}
			else if ($data === 'full'){
				$GuideOrder = !isset($_REQUEST['alphabetically']) && !$EQG;
				if (!$GuideOrder)
					$CGDb->orderBy('label','ASC');
				$Appearances = get_appearances($EQG,null,'id,label');


				if (isset($_REQUEST['ajax']))
					CoreUtils::Respond(array('html' => render_full_list_html($Appearances, $GuideOrder, NOWRAP)));

				$js = array();
				if (Permission::Sufficient('inspector'))
					$js[] = 'Sortable';
				$js[] = "$do-full";

				CoreUtils::LoadPage(array(
					'title' => "Full List - $Color Guide",
					'view' => "$do-full",
					'css' => "$do-full",
					'js' => $js,
				));
			}

			CoreUtils::CanIHas('Pagination');
			$title = '';
			$AppearancesPerPage = 7;
			if (empty($_GET['q']) || regex_match(new RegExp('^\*+$'),$_GET['q'])){
				$_EntryCount = $CGDb->where('ishuman',$EQG)->where('id != 0')->count('appearances');

				$Pagination = new Pagination("{$color}guide", $AppearancesPerPage, $_EntryCount);
				$Ponies = get_appearances($EQG, $Pagination->GetLimit());
			}
			else {
				$SearchQuery = $_GET['q'];
				$Page = $MaxPages = 1;
				$Ponies = false;

				try {
					$Search = process_search_query($SearchQuery);
					$title .= "$SearchQuery - ";
					$IsHuman = $EQG ? 'true' : 'false';

					$Restrictions = array();
					$Params = array();
					if (!empty($Search['tid'])){
						$tc = count($Search['tid']);
						$Restrictions[] = 'p.id IN (
							--SELECT ponyid FROM (
								SELECT t.ponyid
								FROM tagged t
								WHERE t.tid IN ('.implode(',', $Search['tid']).")
								GROUP BY t.ponyid
								HAVING COUNT(t.tid) = $tc
							--) tg
						)";
						$Search['tid_assoc'] = array();
						foreach ($Search['tid'] as $tid)
							$Search['tid_assoc'][$tid] = true;
					}
					if (!empty($Search['label'])){
						$collect = array();
						foreach ($Search['label'] as $l){
							$collect[] = 'lower(p.label) LIKE ?';
							$Params[] = $l;
						}
						$Restrictions[] = implode(' AND ', $collect);
					}

					if (count($Restrictions)){
						$Params[] = $EQG;
						$Query = "SELECT @coloumn FROM appearances p WHERE ".implode(' AND ',$Restrictions)." AND p.ishuman = ? AND p.id != 0";
						$EntryCount = $CGDb->rawQuerySingle(str_replace('@coloumn','COUNT(*) as count',$Query),$Params)['count'];
						$Pagination = new Pagination("{$color}guide", $AppearancesPerPage, $_EntryCount);

						$SearchQuery = str_replace('@coloumn','p.*',$Query);
						$SearchQuery .= " ORDER BY p.order ASC {$Pagination->GetLimitString()}";
						$Ponies = $CGDb->rawQuery($SearchQuery,$Params);
					}
				}
				catch (Exception $e){
					$_MSG = $e->getMessage();
					if (isset($_REQUEST['js']))
						CoreUtils::Respond($_MSG);
				}
			}

			CoreUtils::FixPath("$CGPath/{$Pagination->page}");
			$heading = ($EQG?'EQG ':'')."$Color Guide";
			$title .= "Page {$Pagination->page} - $heading";

			if (isset($_GET['js']))
				$Pagination->Respond(render_ponies_html($Ponies, NOWRAP), '#list');

			$settings = array(
				'title' => $title,
				'heading' => $heading,
				'css' => array($do),
				'js' => array('jquery.qtip', 'jquery.ctxmenu', $do, 'paginate'),
			);
			if (Permission::Sufficient('inspector')){
				$settings['css'] = array_merge($settings['css'], $GUIDE_MANAGE_CSS);
				$settings['js'] = array_merge($settings['js'],$GUIDE_MANAGE_JS);
			}
			CoreUtils::LoadPage($settings);
		break;
		case "browser":
			$AgentString = null;
			if (is_numeric($data) && Permission::Sufficient('developer')){
				$SessionID = intval($data, 10);
				$Session = $Database->where('id', $SessionID)->getOne('sessions');
				if (!empty($Session))
					$AgentString = $Session['user_agent'];
			}
			$browser = CoreUtils::DetectBrowser($AgentString);

			CoreUtils::FixPath('/browser'.(!empty($Session)?"/{$Session['id']}":''));

			CoreUtils::LoadPage(array(
				'title' => 'Browser recognition test page',
				'do-css',
				'no-robots',
			));
		break;
		case "users":
			if (!Permission::Sufficient('inspector'))
				CoreUtils::NotFound();

			CoreUtils::LoadPage(array(
				'title' => 'Users',
				'do-css'
			));
		break;
		case "blending":
			$HexPattern = regex_replace(new RegExp('^/(.*)/.*$'),'$1',$HEX_COLOR_PATTERN->jsExport());
			CoreUtils::LoadPage(array(
				'title' => "$Color Blending Calculator",
				'do-css', 'do-js',
			));
		break;
		case "404":
		default:
			CoreUtils::NotFound();
		break;
	}
