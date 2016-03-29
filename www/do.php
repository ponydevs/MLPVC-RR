<?php

	// Global variables \\
	$Color = 'Color';
	$color = 'color';
	$signedIn = false;
	$currentUser = null;
	
	require "init.php";

	# Chck activity
	$do = !empty($_GET['do']) ? $_GET['do'] : 'index';
	
	# Get additional details
	$data = !empty($_GET['data']) ? $_GET['data'] : '';

	switch ($do){
		case GH_WEBHOOK_DO:
			if (empty(GH_WEBHOOK_DO)) redirect('/', AND_DIE);

			if (!empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'GitHub-Hookshot/') === 0){
				if (empty($_SERVER['HTTP_X_GITHUB_EVENT']) || empty($_SERVER['HTTP_X_HUB_SIGNATURE']))
					do404();

				$payloadHash = hash_hmac('sha1', file_get_contents('php://input'), GH_WEBHOOK_SECRET);
				if ($_SERVER['HTTP_X_HUB_SIGNATURE'] !== "sha1=$payloadHash")
					do404();

				switch (strtolower($_SERVER['HTTP_X_GITHUB_EVENT'])) {
					case 'push':
						$output = array();
						exec("$git reset HEAD --hard",$output);
						exec("$git pull",$output);
						echo implode("\n", $output);
					break;
					case 'ping':
						echo "pong";
					break;
					default: do404();
				}

				exit;
			}
			do404();
		break;
		case "signout":
			if (!$signedIn) respond("You've already signed out",1);
			detectCSRF();

			if (isset($_REQUEST['unlink'])){
				try {
					da_request('https://www.deviantart.com/oauth2/revoke', null, array('token' => $currentUser['Session']['access']));
				}
				catch (cURLRequestException $e){
					respond("Coulnd not revoke the site's access: {$e->getMessage()} (HTTP {$e->getCode()})");
				}
			}

			if (isset($_REQUEST['unlink']) || isset($_REQUEST['everywhere'])){
				$col = 'user';
				$val = $currentUser['id'];
				if (!empty($_POST['username'])){
					if (!PERM('manager') || isset($_REQUEST['unlink']))
						respond();
					if (!$USERNAME_REGEX->match($_POST['username']))
						respond('Invalid username');
					$TargetUser = $Database->where('name', $_POST['username'])->getOne('users','id,name');
					if (empty($TargetUser))
						respond("Target user doesn't exist");
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
				respond('Could not remove information from database');

			if (empty($TargetUser))
				Cookie::delete('access');
			respond(true);
		break;
		case "da-auth":
			da_handle_auth();
		break;
		case "post":
			if (RQMTHD !== 'POST') do404();
			if (!PERM('user')) respond();
			detectCSRF();

			$_match = array();
			if (regex_match(new RegExp('^([gs]et)-(request|reservation)/(\d+)$'), $data, $_match)){
				if (!PERM('inspector'))
					respond();

				$thing = $_match[2];
				$Post = $Database->where('id', $_match[3])->getOne("{$thing}s");
				if (empty($Post))
					respond("The specified $thing does not exist");

				if ($_match[1] === 'get'){
					$response = array(
						'label' => $Post['label'],
					);
					if ($thing === 'request'){
						$response['type'] = $Post['type'];

						if (PERM('developer') && isset($Post['reserved_at']))
							$response['reserved_at'] = date('c', strtotime($Post['reserved_at']));
					}
					if (PERM('developer'))
						$response['posted'] = date('c', strtotime($Post['posted']));
					respond($response);
				}

				$update = array();
				check_post_post($thing, $update, $Post);

				if (empty($update))
					respond('Nothing was changed', 1);

				if (!$Database->where('id', $Post['id'])->update("{$thing}s", $update))
					respond(ERR_DB_FAIL);
				respond($update);
			}
			else if (regex_match(new RegExp('^set-(request|reservation)-image/(\d+)$'), $data, $_match)){
				if (!PERM('inspector'))
					respond();

				$thing = $_match[1];
				$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
				if (empty($Post))
					respond("The specified $thing does not exist");
				if ($Post['lock'])
					respond('This post is locked, its image cannot be changed.');

				$Image = check_post_image($Post);

				// Check image availability
				if (!@getimagesize($Image->preview)){
					sleep(1);
					if (!@getimagesize($Image->preview))
						respond("<p class='align-center'>The specified image doesn't seem to exist. Please verify that you can reach the URL below and try again.<br><a href='{$Image->preview}' target='_blank'>{$Image->preview}</a></p>");
				}

				if (!$Database->where('id', $Post['id'])->update("{$thing}s",array(
					'preview' => $Image->preview,
					'fullsize' => $Image->fullsize,
				))) respond(ERR_DB_FAIL);

				LogAction('img_update',array(
					'id' => $Post['id'],
					'thing' => $thing,
					'oldpreview' => $Post['preview'],
					'oldfullsize' => $Post['fullsize'],
					'newpreview' => $Image->preview,
					'newfullsize' => $Image->fullsize,
				));

				respond(array('preview' => $Image->preview));
			}
			else if (regex_match(new RegExp('^fix-(request|reservation)-stash/(\d+)$'), $data, $_match)){
				if (!PERM('inspector'))
					respond();

				$thing = $_match[1];
				$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
				if (empty($Post))
					respond("The specified $thing does not exist");

				// Link is already full size, we're done
				if (regex_match($FULLSIZE_MATCH_REGEX, $Post['fullsize']))
					respond(array('fullsize' => $Post['fullsize']));

				// Reverse submission lookup
				$StashItem = $Database
					->where('fullsize', $Post['fullsize'])
					->orWhere('preview', $Post['preview'])
					->getOne('deviation_cache','id,fullsize,preview');
				if (empty($StashItem['id']))
					respond('Stash URL lookup failed');

				try {
					$fullsize = get_fullsize_stash_url($StashItem['id']);
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
							respond('The original image has been deleted from Sta.sh',0,array('rmdirect' => true));
						}
						else throw new Exception("Code $fullsize; Could not find the URL");
					}
				}
				catch (Exception $e){
					respond('Error while finding URL: '.$e->getMessage());
				}
				// Check image availability
				if (!@getimagesize($fullsize)){
					sleep(1);
					if (!@getimagesize($fullsize))
						respond("The specified image doesn't seem to exist. Please verify that you can reach the URL below and try again.<br><a href='$fullsize' target='_blank'>$fullsize</a>");
				}

				if (!$Database->where('id', $Post['id'])->update("{$thing}s",array(
					'fullsize' => $fullsize,
				))) respond(ERR_DB_FAIL);

				respond(array('fullsize' => $fullsize));
			}

			$image_check = empty($_POST['what']);
			if (!$image_check){
				if (!in_array($_POST['what'],$POST_TYPES))
					respond('Invalid post type');

				$type = $_POST['what'];
				if ($type === 'reservation'){
					if (!PERM('member'))
						respond();
					res_limit_check();
				}
			}

			if (!empty($_POST['image_url'])){
				$Image = check_post_image();

				if ($image_check)
					respond(array(
						'preview' => $Image->preview,
						'title' => $Image->title,
					));
			}
			else if ($image_check)
				respond("Please provide an image URL!");

			$insert = array(
				'preview' => $Image->preview,
				'fullsize' => $Image->fullsize,
			);

			if (($_POST['season'] != '0' && empty($_POST['season'])) || empty($_POST['episode']))
				respond('Missing episode identifiers');
			$epdata = get_real_episode((int)$_POST['season'], (int)$_POST['episode'], ALLOW_SEASON_ZERO);
			if (empty($epdata))
				respond('This episode does not exist');
			$insert['season'] = $epdata['season'];
			$insert['episode'] = $epdata['episode'];

			$ByID = $currentUser['id'];
			if (PERM('developer') && !empty($_POST['post_as'])){
				$username = trim($_POST['post_as']);
				$PostAs = get_user($username, 'name', '');

				if (empty($PostAs))
					respond('The user you wanted to post as does not exist');

				if ($type === 'reservation' && !PERM('member', $PostAs['role']) && !isset($_POST['allow_nonmember']))
					respond('The user you wanted to post as is not a club member, so you want to post as them anyway?',0,array('canforce' => true));

				$ByID = $PostAs['id'];
			}

			$insert[$type === 'reservation' ? 'reserved_by' : 'requested_by'] = $ByID;
			check_post_post($type, $insert);

			$PostID = $Database->insert("{$type}s",$insert,'id');
			if (!$PostID)
				respond(ERR_DB_FAIL);
			respond(array('id' => $PostID));
		break;
		case "reserving":
			if (RQMTHD !== 'POST') do404();
			$match = array();
			if (empty($data) || !regex_match(new RegExp('^(requests?|reservations?)(?:/(\d+))?$'),$data,$match))
				respond('Invalid request');

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
					 respond("Missing $type ID");
				$ID = intval($match[2], 10);
				$Thing = $Database->where('id', $ID)->getOne("{$type}s");
				if (empty($Thing)) respond("There's no $type with that ID");

				if (!empty($Thing['lock']) && !PERM('developer'))
					respond('This post has been approved and cannot be edited or removed.'.(PERM('inspector') && !PERM('developer')?' If a change is necessary please ask the developer to do it for you.':''));

				if ($deleteing && $type === 'request'){
					if (!PERM('inspector')){
						if (!$signedIn || $Thing['requested_by'] !== $currentUser['id'])
							respond();

						if (!empty($Thing['reserved_by']))
							respond('You cannot delete a request that has already been reserved by a group member');
					}

					if (!$Database->where('id', $Thing['id'])->delete('requests'))
						respond(ERR_DB_FAIL);

					LogAction('req_delete',array(
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

					respond(true);
				}

				if (!PERM('member')) respond();
				$update = array(
					'reserved_by' => null,
					'reserved_at' => null
				);

				if (!empty($Thing['reserved_by'])){
					$usersMatch = $Thing['reserved_by'] === $currentUser['id'];
					if ($noaction){
						if ($usersMatch)
							respond("You've already reserved this $type");
						else respond("This $type has already been reserved by somepony else");
					}
					if ($locking){
						if (empty($Thing['deviation_id']))
							respond('Only finished {$type}s can be locked');
						$Status = is_deviation_in_vectorclub($Thing['deviation_id']);
						if ($Status !== true)
							respond(
								$Status === false
								? "The deviation has not been submitted to/accepted by the group yet"
								: "There was an issue while checking the acceptance status (Error code: $Status)"
							);

						if (!$Database->where('id', $Thing['id'])->update("{$type}s", array('lock' => 1)))
							respond("This $type is already approved", 1);

						LogAction('post_lock',array(
							'type' => $type,
							'id' => $Thing['id']
						));

						$message = "The image appears to be in the group gallery and as such it is now marked as approved.";
						if ($usersMatch)
							$message .= " Thank you for your contribution!";
						respond($message, 1, array('canedit' => PERM('developer')));
					}
					if ($canceling)
						$unfinishing = true;
					if ($unfinishing){
						if (($canceling && !$usersMatch) && !PERM('inspector')) respond();

						if (!$canceling && !isset($_REQUEST['unbind'])){
							if ($type === 'reservation' && empty($Thing['preview']))
								respond('This reservation was added directly and cannot be marked un-finished. To remove it, check the unbind from user checkbox.');
							unset($update['reserved_by']);
						}

						if (($canceling || isset($_REQUEST['unbind'])) && $type === 'reservation'){
							if (!$Database->where('id', $Thing['id'])->delete('reservations'))
								respond(ERR_DB_FAIL);

							if (!$canceling)
								respond('Reservation deleted', 1);
						}
						if (!$canceling){
							if (isset($_REQUEST['unbind']) && $type === 'request'){
								if (!PERM('inspector') && !$usersMatch)
									respond('You cannot remove the reservation from this post');
							}
							else $update = array();
							$update['deviation_id'] = null;
						}
					}
					else if ($finishing){
						if (!$usersMatch && !PERM('inspector'))
							respond();
						$update = check_request_finish_image($Thing['reserved_by']);
					}
				}
				else if ($finishing) respond("This $type has not yet been reserved");
				else if (!$canceling){
					res_limit_check();

					if (!empty($_POST['post_as'])){
						if (!PERM('developer'))
							respond('Reserving as other users is only allowed to the developer');

						$post_as = trim($_POST['post_as']);
						if (!$USERNAME_REGEX->match($post_as))
							respond('Username format is invalid');

						$User = get_user($post_as, 'name');
						if (empty($User))
							respond('User does not exist');
						if (!PERM('member',$User['role']))
							respond('User does not have permission to reserve posts');

						$update['reserved_by'] = $User['id'];
					}
					else $update['reserved_by'] = $currentUser['id'];
					$update['reserved_at'] = date('c');
				}

				if ((!$canceling || $type !== 'reservation') && !$Database->where('id', $Thing['id'])->update("{$type}s",$update))
					respond('Nothing has been changed');

				foreach ($update as $k => $v)
					$Thing[$k] = $v;

				if (!$canceling && ($finishing || $unfinishing)){
					$out = array();
					if ($finishing && $type === 'request'){
						$u = get_user($Thing['requested_by'],'id','name');
						if (!empty($u) && $Thing['requested_by'] !== $currentUser['id'])
							$out['message'] = "<p class='align-center'>You may want to mention <strong>{$u['name']}</strong> in the deviation description to let them know that their request has been fulfilled.</p>";
					}
					respond($out);
				}

				if ($type === 'request')
					respond(array('li' => get_r_li($Thing, true)));
				else if ($type === 'reservation' && $canceling)
					respond(array('remove' => true));
				else respond(true);
			}
			else if ($type === 'reservation'){
				if (!PERM('inspector'))
					respond();
				$_POST['allow_overwrite_reserver'] = true;
				$insert = check_request_finish_image();
				if (empty($insert['reserved_by']))
					$insert['reserved_by'] = $currentUser['id'];
				$epdata = episode_id_parse($_GET['add']);
				if (empty($epdata))
					respond('Invalid episode');
				$epdata = get_real_episode($epdata['season'], $epdata['episode']);
				if (empty($epdata))
					respond('The specified episode does not exist');
				$insert['season'] = $epdata['season'];
				$insert['episode'] = $epdata['episode'];

				if (!$Database->insert('reservations', $insert))
					respond(ERR_DB_FAIL);
				respond('Reservation added',1);
			}
			else respond('Invalid request');
		break;
		case "ping":
			if (RQMTHD !== 'POST')
				do404();

			respond(true);
		break;

		// PAGES
		case "index":
			$CurrentEpisode = get_latest_episode();
			if (empty($CurrentEpisode)){
				unset($CurrentEpisode);
				loadPage(array(
					'title' => 'Home',
					'view' => 'episode',
				));
			}

			loadEpisodePage($CurrentEpisode);
		break;
		case "muffin-rating":
			$ScorePercent = 100;
			if (isset($_GET['w']) && regex_match(new RegExp('^(\d|[1-9]\d|100)$'), $_GET['w']))
				$ScorePercent = intval($_GET['w'], 10);
			$RatingFile = file_get_contents(APPATH."img/muffin-rating.svg");
			header('Content-Type: image/svg+xml');;
			die(str_replace("width='100'", "width='$ScorePercent'", $RatingFile));
		break;
		case "episode":
			$_match = array();

			if (RQMTHD === 'POST'){
				detectCSRF();

				if (empty($data)) do404();

				$EpData = episode_id_parse($data);
				if (!empty($EpData)){
					$Ep = get_real_episode($EpData['season'],$EpData['episode']);
					$airs =  strtotime($Ep['airs']);
					unset($Ep['airs']);
					$Ep['airdate'] = gmdate('Y-m-d', $airs);
					$Ep['airtime'] = gmdate('H:i', $airs);
					respond(array(
						'ep' => $Ep,
						'epid' => format_episode_title($Ep, AS_ARRAY, 'id'),
					));
				}
				unset($EpData);

				if (regex_match(new RegExp('^delete/'.EPISODE_ID_PATTERN.'$'),$data,$_match)){
					if (!PERM('inspector')) respond();
					list($season,$episode) = array_splice($_match,1,2);

					$Episode = get_real_episode(intval($season, 10),intval($episode, 10));
					if (empty($Episode))
						respond("There's no episode with this season & episode number");

					if (!$Database->whereEp($Episode)->delete('episodes'))
						respond(ERR_DB_FAIL);
					LogAction('episodes',array(
						'action' => 'del',
						'season' => $Episode['season'],
						'episode' => $Episode['episode'],
						'twoparter' => $Episode['twoparter'],
						'title' => $Episode['title'],
						'airs' => $Episode['airs'],
					));
					$CGDb->where('name', "s{$Episode['season']}e{$Episode['episode']}")->delete('tags');
					respond('Episode deleted successfuly',1,array(
						'upcoming' => get_upcoming_eps(null, NOWRAP),
					));
				}
				else if (regex_match(new RegExp('^((?:request|reservation)s)/'.EPISODE_ID_PATTERN.'$'), $data, $_match)){
					$Episode = get_real_episode($_match[2],$_match[3],ALLOW_SEASON_ZERO);
					if (empty($Episode))
						respond("There's no episode with this season & episode number");
					$only = $_match[1] === 'requests' ? ONLY_REQUESTS : ONLY_RESERVATIONS;
					respond(array(
						'render' => call_user_func("{$_match[1]}_render",get_posts($Episode['season'], $Episode['episode'], $only)),
					));
				}
				else if (regex_match(new RegExp('^vote/'.EPISODE_ID_PATTERN.'$'), $data, $_match)){
					$Episode = get_real_episode($_match[1],$_match[2],ALLOW_SEASON_ZERO);
					if (empty($Episode))
						respond("There's no episode with this season & episode number");

					if (isset($_REQUEST['detail'])){
						$VoteCounts = $Database->rawQuery(
							"SELECT count(*) as value, vote as label
							FROM episodes__votes v
							WHERE season = ? && episode = ?
							GROUP BY v.vote
							ORDER BY v.vote DESC",array($Episode['season'],$Episode['episode']));

						respond(array('data' => $VoteCounts));
					}

					if (isset($_REQUEST['html']))
						respond(array('html' => get_episode_voting($Episode)));

					if (!PERM('user'))
						respond();

					if (!$Episode['aired'])
						respond('You can only vote on this episode after it has aired.');

					$UserVote = get_episode_user_vote($Episode);
					if (!empty($UserVote))
						respond('You already voted for this episode');

					if (empty($_POST['vote']) || !is_numeric($_POST['vote']))
						respond('Vote value missing from request');

					$Vote = intval($_POST['vote'], 10);
					if ($Vote < 1 || $Vote > 5)
						respond('Vote value must be an integer between 1 and 5 (inclusive)');

					if (!$Database->insert('episodes__votes',array(
						'season' => $Episode['season'],
						'episode' => $Episode['episode'],
						'user' => $currentUser['id'],
						'vote' => $Vote,
					))) respond(ERR_DB_FAIL);
					respond(array('newhtml' => get_episode_voting($Episode)));
				}
				else if (regex_match(new RegExp('^(([sg])et)?videos/'.EPISODE_ID_PATTERN.'$'), $data, $_match)){
					$Episode = get_real_episode($_match[3],$_match[4],ALLOW_SEASON_ZERO);
					if (empty($Episode))
						respond("There's no episode with this season & episode number");

					if (empty($_match[1]))
						respond(get_ep_video_embeds($Episode));

					$set = $_match[2] === 's';
					require_once "includes/Video.php";

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
						respond($return);
					}

					foreach (array('yt','dm') as $provider){
						for ($part = 1; $part <= ($Episode['twoparter']?2:1); $part++){
							$set = null;
							$PostKey = "{$provider}_$part";
							if (!empty($_POST[$PostKey])){
								try {
									$vid = new Video($_POST[$PostKey]);
								}
								catch (Exception $e){
									respond("{$VIDEO_PROVIDER_NAMES[$provider]} link issue: ".$e->getMessage());
								};
								if (!isset($vid->provider) || $vid->provider['name'] !== $provider)
									respond("Incorrect {$VIDEO_PROVIDER_NAMES[$provider]} URL specified");
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

					respond('Links updated',1,array('epsection' => render_ep_video($Episode)));
				}
				else {
					if (!PERM('inspector')) respond();
					$editing = regex_match(new RegExp('^edit\/'.EPISODE_ID_PATTERN.'$'),$data,$_match);
					if ($editing){
						list($season, $episode) = array_map('intval', array_splice($_match, 1, 2));
						$insert = array();
					}
					else if ($data === 'add') $insert = array('posted_by' => $currentUser['id']);
					else statusCodeHeader(404, AND_DIE);

					if (!isset($_POST['season']) || !is_numeric($_POST['season']))
						respond('Season number is missing or invalid');
					$insert['season'] = intval($_POST['season'], 10);
					if ($insert['season'] < 1 || $insert['season'] > 8) respond('Season number must be between 1 and 8');

					if (!isset($_POST['episode']) || !is_numeric($_POST['episode']))
						respond('Episode number is missing or invalid');
					$insert['episode'] = intval($_POST['episode'], 10);
					if ($insert['episode'] < 1 || $insert['episode'] > 26) respond('Season number must be between 1 and 26');

					if ($editing){
						$Current = get_real_episode($insert['season'],$insert['episode']);
						if (empty($Current))
							respond("This episode doesn't exist");
					}
					$Target = get_real_episode($insert['season'],$insert['episode']);
					if (!empty($Target) && (!$editing || ($editing && ($Target['season'] !== $Current['season'] || $Target['episode'] !== $Current['episode']))))
						respond("There's already an episode with the same season & episode number");

					$insert['twoparter'] = isset($_POST['twoparter']) ? 1 : 0;

					if (empty($_POST['title']))
						respond('Episode title is missing or invalid');
					$insert['title'] = trim($_POST['title']);
					if (strlen($insert['title']) < 5 || strlen($insert['title']) > 35)
						respond('Episode title must be between 5 and 35 characters');
					if (!$EP_TITLE_REGEX->match($insert['title']))
						respond('Episode title contains invalid charcaters');

					if (empty($_POST['airs']))
						repond('No air date &time specified');
					$airs = strtotime($_POST['airs']);
					if (empty($airs))
						respond('Invalid air time');
					$insert['airs'] = date('c',strtotime('this minute', $airs));

					if ($editing){
						if (!$Database->whereEp($season,$episode)->update('episodes', $insert))
							respond('Updating episode failed: '.ERR_DB_FAIL);
					}
					else if (!$Database->insert('episodes', $insert))
						respond('Episode creation failed: '.ERR_DB_FAIL);

					$SeasonChanged = $editing && $season !== $insert['season'];
					$EpisodeChanged = $editing && $episode !== $insert['episode'];
					if (!$editing || $SeasonChanged || $EpisodeChanged){
						$TagName = "s{$insert['season']}e{$insert['episode']}";
						$EpTag = $CGDb->where('name', $editing ? "s{$season}e{$episode}" : $TagName)->getOne('tags');

						if (empty($EpTag)){
							if (!$CGDb->insert('tags', array(
								'name' => $TagName,
								'type' => 'ep',
							))) respond('Episode tag creation failed: '.ERR_DB_FAIL);
						}
						else if ($SeasonChanged || $EpisodeChanged)
							$CGDb->where('name',$EpTag['name'])->update('tags', array(
								'name' => $TagName,
							));
					}

					if ($editing){
						$logentry = array('target' => format_episode_title($Current,AS_ARRAY,'id'));
						$changes = 0;
						foreach (array('season', 'episode', 'twoparter', 'title', 'airs') as $k){
							if (isset($insert[$k]) && $insert[$k] != $Current[$k]){
								$logentry["old$k"] = $Current[$k];
								$logentry["new$k"] = $insert[$k];
								$changes++;
							}
						}
						if ($changes > 0) LogAction('episode_modify',$logentry);
					}
					else LogAction('episodes',array(
						'action' => 'add',
						'season' => $insert['season'],
						'episode' => $insert['episode'],
						'twoparter' => isset($insert['twoparter']) ? $insert['twoparter'] : 0,
						'title' => $insert['title'],
						'airs' => $insert['airs'],
					));
					respond($editing ? true : array('epid' => format_episode_title($insert,AS_ARRAY,'id')));
				}
			}

			loadEpisodePage();
		break;
		case "episodes":
			$ItemsPerPage = 10;
			$EntryCount = $Database->where('season != 0')->count('episodes');
			list($Page,$MaxPages) = calc_page($EntryCount);
			$Pagination = get_pagination_html('episodes');

			fix_path("/episodes/$Page");
			$heading = "Episodes";
			$title = "Page $Page - $heading";
			$Episodes = get_episodes(array($ItemsPerPage*($Page-1), $ItemsPerPage));

			if (isset($_GET['js']))
				pagination_response(get_eptable_tbody($Episodes), '#episodes tbody');

			$settings = array(
				'title' => $title,
				'do-css',
				'js' => array('paginate',$do),
			);
			if (PERM('inspector'))
				$settings['js'] = array_merge(
					$settings['js'],
					array('moment-timezone',"$do-manage")
				);
			loadPage($settings);
		break;
		case "eqg":
			if (!regex_match(new RegExp('^([a-z\-]+|\d+)$'),$data))
				do404();

			$assoc = array('friendship-games' => 3);
			$flip_assoc = array_flip($assoc);

			if (!is_numeric($data)){
				if (empty($assoc[$data]))
					do404();
				$url = $data;
				$data = $assoc[$data];
			}
			else {
				$data = intval($data, 10);
				if (empty($flip_assoc[$data]))
					do404();
				$url = $flip_assoc[$data];
			}

			loadEpisodePage($data, true);
		break;
		case "about":
			if (RQMTHD === 'POST'){
				detectCSRF();
				$StatCacheDuration = 5*ONE_HOUR;

				$_match = array();
				if (!empty($data) && regex_match(new RegExp('^stats-(posts|approvals)$'),$data,$_match)){
					$stat = $_match[1];
					$CachePath = APPATH."../stats/$stat.json";
					if (file_exists($CachePath) && filemtime($CachePath) > time() - $StatCacheDuration)
						respond(array('data' => JSON::Decode(file_get_contents($CachePath))));

					$Data = array('datasets' => array(), 'timestamp' => date('c'));
					$LabelFormat = 'YYYY-MM-DD 00:00:00';

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

							process_stat_labels($Labels, $Data);

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
								process_usage_stats($RequestData, $Dataset);
								$Data['datasets'][] = $Dataset;
							}
							$ReservationData = $Database->rawQuery(str_replace('table_name', 'reservations', $query));
							if (!empty($ReservationData)){
								$Dataset = array('label' => 'Reservations', 'clrkey' => 1);
								process_usage_stats($ReservationData, $Dataset);
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

							process_stat_labels($Labels, $Data);

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
								process_usage_stats($Approvals, $Dataset);
								$Data['datasets'][] = $Dataset;
							}
						break;
					}

					timed_stat_data_postprocess($Data);

					upload_folder_create($CachePath);
					file_put_contents($CachePath, JSON::Encode($Data));

					respond(array('data' => $Data));
				}

				do404();
			}
			loadPage(array(
				'title' => 'About',
				'do-css',
				'js' => array('Chart', $do),
			));
		break;
		case "logs":
			redirect(rtrim("/admin/logs/$data",'/'), AND_DIE);
		break;
		case "admin":
			if (!PERM('inspector'))
				do404();

			$task = strtok($data, '/');
			$data = regex_replace(new RegExp('^[^/]*?(?:/(.*))?$'), '$1', $data);

			if (RQMTHD === "POST"){
				switch ($task){
					case "logs":
						if (regex_match(new RegExp('^details/(\d+)'), $data, $_match)){
							$EntryID = intval($_match[1], 10);

							$MainEntry = $Database->where('entryid', $EntryID)->getOne('log');
							if (empty($MainEntry)) respond('Log entry does not exist');
							if (empty($MainEntry['refid'])) respond('There are no details to show');

							$Details = $Database->where('entryid', $MainEntry['refid'])->getOne("log__{$MainEntry['reftype']}");
							if (empty($Details)) respond('Failed to retrieve details');

							respond(format_log_details($MainEntry['reftype'],$Details));
						}
						else do404();
					break;
					case "usefullinks":
						if (regex_match(new RegExp('^([gs]et|del|make)(?:/(\d+))?$'), $data, $_match)){
							$action = $_match[1];
							$creating = $action === 'make';

							if (!$creating){
								$Link = $Database->where('id', $_match[2])->getOne('usefullinks');
								if (empty($Link))
									respond('The specified link does not exist');
							}

							switch ($action){
								case 'get':
									respond(array(
										'label' => $Link['label'],
										'url' => $Link['url'],
										'title' => $Link['title'],
										'minrole' => $Link['minrole'],
									));
								case 'del':
									if (!$Database->where('id', $Link['id'])->delete('usefullinks'))
										respond(ERR_DB_FAIL);

									respond(true);
								break;
								case 'make':
								case 'set':
									$data = array();

									if (empty($_POST['label']))
										respond('Link label is missing');
									$label = trim($_POST['label']);
									if ($creating || $Link['label'] !== $label){
										$ll = strlen($label);
										if ($ll < 3 || $ll > 40)
											respond('Link label must be between 3 and 40 characters long');
										check_string_valid($label, 'Link label', INVERSE_PRINTABLE_ASCII_REGEX);
										$data['label'] = $label;
									}

									if (empty($_POST['url']))
										respond('Link URL is missing');
									$url = trim($_POST['url']);
									if ($creating || $Link['url'] !== $url){
										$ul = strlen($url);
										if (stripos($url, ABSPATH) === 0)
											$url = substr($url, strlen(ABSPATH)-1);
										if (!regex_match($REWRITE_REGEX,$url) && !regex_match(new RegExp('^#[a-z\-]+$'),$url)){
											if ($ul < 3 || $ul > 255)
												respond('Link URL must be between 3 and 255 characters long');
											if (!regex_match(new RegExp('^https?:\/\/.+$'), $url))
												respond('Link URL does not appear to be a valid link');
										}
										$data['url'] = $url;
									}

									if (!empty($_POST['title'])){
										$title = trim($_POST['title']);
										if ($creating || $Link['title'] !== $title){
											$tl = strlen($title);
											if ($tl < 3 || $tl > 255)
												respond('Link title must be between 3 and 255 characters long');
											check_string_valid($title, 'Link title', INVERSE_PRINTABLE_ASCII_REGEX);
											$data['title'] = trim($title);
										}
									}
									else $data['title'] = '';

									if (empty($_POST['minrole']))
										respond('Minimum role is missing');
									$minrole = trim($_POST['minrole']);
									if ($creating || $Link['minrole'] !== $minrole){
										if (!isset($ROLES_ASSOC[$minrole]) || !PERM('user', $minrole))
											respond('Minumum role is invalid');
										$data['minrole'] = $minrole;
									}

									if (empty($data))
										respond('Nothing was changed');
									$query = $creating
										? $Database->insert('usefullinks', $data)
										: $Database->where('id', $Link['id'])->update('usefullinks', $data);
									if (!$query)
										respond(ERR_DB_FAIL);

									respond(true);
								break;
								default: do404();
							}
						}
						else if ($data === 'reorder'){
							if (!isset($_POST['list']))
								respond('Missing ordering information');

							$list = explode(',',regex_replace(new RegExp('[^\d,]'),'',trim($_POST['list'])));
							$order = 1;
							foreach ($list as $id){
								if (!$Database->where('id', $id)->update('usefullinks', array('order' => $order++)))
									respond("Updating link #$id failed, process halted");
							}

							respond(true);
						}
						else do404();
					break;
					default:
						do404();
				}
			}

			if (empty($task))
				loadPage(array(
					'title' => 'Admin Area',
					'do-css',
					'js' => array('Sortable',$do),
				));

			switch ($task){
				case "logs":
					$ItemsPerPage = 20;
					$EntryCount = $Database->count('log');
					list($Page,$MaxPages) = calc_page($EntryCount);

					fix_path("/admin/logs/$Page");
					$heading = 'Logs';
					$title = "Page $Page - $heading";
					$Pagination = get_pagination_html('admin/logs');

					$LogItems = $Database
						->orderBy('timestamp')
						->orderBy('entryid')
						->get('log',array($ItemsPerPage*($Page-1), $ItemsPerPage));

					if (isset($_GET['js']))
						pagination_response(log_tbody_render($LogItems), '#logs tbody');

					loadPage(array(
						'title' => $title,
						'view' => "$do-logs",
						'css' => "$do-logs",
						'js' => array("$do-logs", 'paginate'),
					));
				break;
				default:
					do404();
			}
		break;
		case "u":
			$do = 'user';
		case "user":
			$_match = array();

			if (strtolower($data) === 'immortalsexgod')
				$data = 'DJDavid98';

			if (RQMTHD === 'POST'){
				if (!PERM('inspector')) respond();
				detectCSRF();

				if (empty($data)) do404();

				if (regex_match(new RegExp('^newgroup/'.USERNAME_PATTERN.'$'),$data,$_match)){
					$targetUser = get_user($_match[1], 'name');
					if (empty($targetUser))
						respond('User not found');

					if ($targetUser['id'] === $currentUser['id'])
						respond("You cannot modify your own group");
					if (!PERM($targetUser['role']))
						respond('You can only modify the group of users who are in the same or a lower-level group than you');
					if ($targetUser['role'] === 'ban')
						respond('This user is banished, and must be un-banished before changing their group.');

					if (!isset($_POST['newrole']))
						respond('The new group is not specified');
					$newgroup = trim($_POST['newrole']);
					if (!in_array($newgroup,$ROLES) || $newgroup === 'ban')
						respond('The specified group does not exist');
					if ($targetUser['role'] === $newgroup)
						respond(array('already_in' => true));

					update_role($targetUser,$newgroup);

					respond(true);
				}
				else if (regex_match(new RegExp('^sessiondel/(\d+)$'),$data,$_match)){
					$Session = $Database->where('id', $_match[1])->getOne('sessions');
					if (empty($Session))
						respond('This session does not exist');
					if ($Session['user'] !== $currentUser['id'] && !PERM('inspector'))
						respond('You are not allowed to delete this session');

					if (!$Database->where('id', $Session['id'])->delete('sessions'))
						respond('Session could not be deleted');
					respond('Session successfully removed',1);
				}
				else if (regex_match(new RegExp('^(un-)?banish/'.USERNAME_PATTERN.'$'), $data, $_match)){
					$Action = (empty($_match[1]) ? 'Ban' : 'Un-ban').'ish';
					$action = strtolower($Action);
					$un = $_match[2];

					$targetUser = get_user($un, 'name');
					if (empty($targetUser)) respond('User not found');

					if ($targetUser['id'] === $currentUser['id']) respond("You cannot $action yourself");
					if (PERM('inspector', $targetUser['role']))
						respond("You cannot $action people within the inspector or any higher group");
					if ($action == 'banish' && $targetUser['role'] === 'ban' || $action == 'un-banish' && $targetUser['role'] !== 'ban')
						respond("This user has already been {$action}ed");

					if (empty($_POST['reason']))
						respond('Please specify a reason');
					$reason = trim($_POST['reason']);
					$rlen = strlen($reason);
					if ($rlen < 5 || $rlen > 255)
						respond('Reason length must be between 5 and 255 characters');

					$changes = array('role' => $action == 'banish' ? 'ban' : 'user');
					$Database->where('id', $targetUser['id'])->update('users', $changes);
					LogAction($action,array(
						'target' => $targetUser['id'],
						'reason' => $reason
					));
					$changes['role'] = $ROLES_ASSOC[$changes['role']];
					$changes['badge'] = label_to_initials($changes['role']);
					if ($action == 'banish') respond($changes);
					else respond("We welcome {$targetUser['name']} back with open hooves!", 1, $changes);
				}
				else statusCodeHeader(404, AND_DIE);
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
			else $User = get_user($un, 'name');

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
				$canEdit = !$sameUser && PERM('inspector') && PERM($User['role']);
				$pagePath = "/@{$User['name']}";
				fix_path($pagePath);
			}
			if ($canEdit)
				$UsableRoles = $Database->where("value <= (SELECT value FROM roles WHERE name = '{$currentUser['role']}')")->where('value > 0')->get('roles',null,'name, label');

			if (isset($MSG)) statusCodeHeader(404);
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
				'title' => !isset($MSG) ? ($sameUser?'Your':s($User['name'])).' '.($sameUser || $canEdit?'account':'profile') : 'Account',
				'no-robots',
				'do-css',
				'js' => array('user'),
			);
			if ($canEdit) $settings['js'][] = 'user-manage';
			loadPage($settings);
		break;
		case "colourguides":
		case "colourguide":
		case "colorguides":
			$do = 'colorguide';
		case "colorguide":
			require_once "includes/CGUtils.php";

			$SpriteRelPath = '/img/cg/';
			$SpritePath = APPATH.substr($SpriteRelPath,1);
			$ItemsPerPage = 7;

			if (RQMTHD === 'POST' || (isset($_GET['s']) && $data === "gettags")){
				if (!PERM('inspector')) respond();
				IF (RQMTHD === "POST") detectCSRF();

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
								respond("This tag is already a synonym of <strong>{$Syn['name']}</strong>.<br>Would you like to remove the synonym?",0,array('undo' => true));
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
						if (isset($_REQUEST['reorder'])){
							if (!PERM('inspector'))
								respond();
							if (empty($_POST['list']))
								respond('The list of IDs is missing');

							$list = trim($_POST['list']);
							if (!regex_match(new RegExp('^\d+(?:,\d+)+$'), $list))
								respond('The list of IDs is not formatted properly');

							reorder_appearances($list);

							respond(array('html' => render_full_list_html(get_appearances($EQG,null,'id,label'), true, NOWRAP)));
						}
						else do404();
					break;
				}

				$_match = array();
				if (regex_match(new RegExp('^(rename|delete|make|(?:[gs]et|del)(?:sprite|cgs)?|tag|untag|clearrendercache|applytemplate)(?:/(\d+))?$'), $data, $_match)){
					$action = $_match[1];
					$creating = $action === 'make';

					if (!$creating){
						$AppearanceID = intval($_match[2], 10);
						if (strlen($_match[2]) === 0)
							respond('Missing appearance ID');
						$Appearance = $CGDb->where('id', $AppearanceID)->where('ishuman', $EQG)->getOne('appearances');
						if (empty($Appearance))
							respond("The specified appearance does not exist");
					}
					else $Appearance = array('id' => null);

					switch ($action){
						case "get":
							respond(array(
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
								respond('Label is missing');
							$label = trim($_POST['label']);
							$ll = strlen($label);
							check_string_valid($label, "Appearance name", INVERSE_PRINTABLE_ASCII_REGEX);
							if ($ll < 4 || $ll > 70)
								respond('Appearance name must be beetween 4 and 70 characters long');
							if ($creating && $CGDb->where('label', $label)->has('appearances'))
								respond('An appearance already esists with this name');
							$data['label'] = $label;

							if (!empty($_POST['notes'])){
								$notes = trim($_POST['notes']);
								check_string_valid($label, "Appearance notes", INVERSE_PRINTABLE_ASCII_REGEX);
								if (strlen($notes) > 1000 && ($creating || $Appearance['id'] !== 0))
									respond('Appearance notes cannot be longer than 1000 characters');
								if ($creating || $notes !== $Appearance['notes'])
									$data['notes'] = $notes;
							}
							else $data['notes'] = '';

							if (!empty($_POST['cm_favme'])){
								$cm_favme = trim($_POST['cm_favme']);
								try {
									require_once 'includes/Image.php';
									$Image = new Image($cm_favme, array('fav.me','dA'));
									$data['cm_favme'] = $Image->id;
								}
								catch (MismatchedProviderException $e){
									respond('The vector must be on DeviantArt, '.$e->getActualProvider().' links are not allowed');
								}
								catch (Exception $e){ respond("Cutie Mark link issue: ".$e->getMessage()); }

								if (empty($_POST['cm_dir']))
									respond('Cutie mark orientation must be set if a link is provided');
								if ($_POST['cm_dir'] !== 'th' && $_POST['cm_dir'] !== 'ht')
									respond('Invalid cutie mark orientation');
								$cm_dir = $_POST['cm_dir'] === 'ht' ? CM_DIR_HEAD_TO_TAIL : CM_DIR_TAIL_TO_HEAD;
								if ($creating || $Appearance['cm_dir'] !== $cm_dir)
									$data['cm_dir'] = $cm_dir;

								$cm_preview = trim($_POST['cm_preview']);
								if (empty($cm_preview))
									$data['cm_preview'] = null;
								else if ($creating || $cm_preview !== $Appearance['cm_preview']){
									try {
										require_once 'includes/Image.php';
										$Image = new Image($cm_preview);
										$data['cm_preview'] = $Image->preview;
									}
									catch (Exception $e){ respond("Cutie Mark preview issue: ".$e->getMessage()); }
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
								respond(ERR_DB_FAIL);

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
										respond($response, 1);
									}
								}
								respond($response);
							}
							else {
								clear_rendered_image($Appearance['id']);
								if ($AppearancePage)
									respond(true);
							}

							$Appearance = array_merge($Appearance, $data);
							respond(array(
								'label' => $Appearance['label'],
								'notes' => get_notes_html($Appearance, NOWRAP),
							));
						break;
						case "delete":
							if ($Appearance['id'] === 0)
								respond('This appearance cannot be deleted');

							if (!$CGDb->where('id', $Appearance['id'])->delete('appearances'))
								respond(ERR_DB_FAIL);

							$fpath = APPATH."img/cg/{$Appearance['id']}.png";
							if (file_exists($fpath))
								unlink($fpath);

							clear_rendered_image($Appearance['id']);

							respond('Appearance removed', 1);
						break;
						case "getcgs":
							$cgs = get_cgs($Appearance['id'],'groupid, label');
							if (empty($cgs))
								respond('This appearance does not have any color groups');
							respond(array('cgs' => $cgs));
						break;
						case "setcgs":
							if (empty($_POST['cgs']))
								respond("$Color group order data missing");

							$groups = array_unique(array_map('intval',explode(',',$_POST['cgs'])));
							foreach ($groups as $part => $GroupID){
								if (!$CGDb->where('groupid', $GroupID)->has('colorgroups'))
									respond("There's no group with the ID of  $GroupID");

								$CGDb->where('groupid', $GroupID)->update('colorgroups',array('order' => $part));
							}

							clear_rendered_image($Appearance['id']);

							respond(array('cgs' => get_colors_html($Appearance['id'], NOWRAP, !isset($_POST['NO_COLON']), isset($_POST['OUTPUT_COLOR_NAMES']))));
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
										respond('No sprite file found');

									if (!unlink($finalpath))
										respond('File could not be deleted');

									respond(array('sprite' => get_sprite_url($Appearance, DEFAULT_SPRITE)));
								break;
							}

							respond(array("path" => "$SpriteRelPath$fname?".filemtime($finalpath)));
						break;
						case "clearrendercache":
							if (!clear_rendered_image($Appearance['id']))
								respond('Cache could not be cleared');

							respond('Cached image removed, the image will be re-generated on the next request', 1);
						break;
						// TODO merge with untag
						case "tag":
							if ($Appearance['id'] === 0)
								respond('This appearance cannot be tagged');

							if (empty($_POST['tag_name']))
								respond('Tag name is not specified');
							$tag_name = strtolower(trim($_POST['tag_name']));
							if (!regex_match($TAG_NAME_REGEX,$tag_name))
								respond('Invalid tag name');

							$TagCheck = ep_tag_name_check($tag_name);
							if ($TagCheck !== false)
								$tag_name = $TagCheck;

							$Tag = $CGDb->where('name',$tag_name)->getOne('tags');
							if (empty($Tag))
								respond("The tag $tag_name does not exist.<br>Would you like to create it?",0,array(
									'cancreate' => $tag_name,
									'typehint' => $TagCheck !== false ? 'ep' : null,
								));

							if ($CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->has('tagged'))
								respond('This appearance already has this tag');

							if (!$CGDb->insert('tagged',array(
								'ponyid' => $Appearance['id'],
								'tid' => $Tag['tid'],
							))) respond(ERR_DB_FAIL);

							update_tag_count($Tag['tid']);
							if (isset($GroupTagIDs_Assoc[$Tag['tid']]))
								get_sort_reorder_appearances($EQG);

							$response = array('tags' => get_tags_html($Appearance['id'], NOWRAP));
							if ($AppearancePage && $Tag['type'] === 'ep'){
								$response['needupdate'] = true;
								$response['eps'] = get_episode_appearances($Appearance['id'], NOWRAP);
							}
							respond($response);
						break;
						case "untag":
							if ($Appearance['id'] === 0)
								respond('This appearance cannot be un-tagged');

							if (empty($_POST['tag']))
								respond('Tag ID is not specified');
							$Tag = $CGDb->where('tid',$_POST['tag'])->getOne('tags');
							if (empty($Tag))
								respond('Tag does not exist');
							if (!empty($Tag['synonym_of'])){
								$Syn = get_tag_synon($Tag,'name');
								respond('Synonym tags cannot be removed from appearances directly. '.
								        "If you want to remove this tag you must remove <strong>{$Syn['name']}</strong> or the synonymization.");
							}

							if ($CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->has('tagged')){
								if (!$CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->delete('tagged'))
									respond(ERR_DB_FAIL);
							}

							update_tag_count($Tag['tid']);
							if (isset($GroupTagIDs_Assoc[$Tag['tid']]))
								get_sort_reorder_appearances($EQG);

							$response = array('tags' => get_tags_html($Appearance['id'], NOWRAP));
							if ($AppearancePage && $Tag['type'] === 'ep'){
								$response['needupdate'] = true;
								$response['eps'] = get_episode_appearances($Appearance['id'], NOWRAP);
							}
							respond($response);
						break;
						case "applytemplate":
							try {
								apply_template($Appearance['id'], $EQG);
							}
							catch (Exception $e){
								respond("Applying the template failed. Reason: ".$e->getMessage());
							}

							respond(array('cgs' => get_colors_html($Appearance['id'], NOWRAP)));
						break;
						default: statusCodeHeader(400, AND_DIE);
					}
				}
				else if (regex_match(new RegExp('^([gs]et|make|del|merge|recount|(?:un)?synon)tag(?:/(\d+))?$'), $data, $_match)){
					$action = $_match[1];

					if ($action === 'recount'){
						if (empty($_POST['tagids']))
							respond('Missing list of tags to update');

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

						respond(
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
						if (empty($_match[2]))
							respond('Missing tag ID');
						$TagID = intval($_match[2], 10);
						$Tag = $CGDb->where('tid', $TagID)->getOne('tags',isset($query) ? 'tid, name, type':'*');
						if (empty($Tag))
							respond("There's no tag with the ID of $TagID");

						if ($getting) respond($Tag);

						if ($deleting){
							if (!isset($_POST['sanitycheck'])){
								$tid = !empty($Tag['synonym_of']) ? $Tag['synonym_of'] : $Tag['tid'];
								$Uses = $CGDb->where('tid',$tid)->count('tagged');
								if ($Uses > 0)
									respond('<p>This tag is currently used on '.plur('appearance',$Uses,PREPEND_NUMBER).'</p><p>Deleting will <strong class="color-red">permanently remove</strong> the tag from those appearances!</p><p>Are you <em class="color-red">REALLY</em> sure about this?</p>',0,array('confirm' => true));
							}

							if (!$CGDb->where('tid', $Tag['tid'])->delete('tags'))
								respond(ERR_DB_FAIL);

							if (isset($GroupTagIDs_Assoc[$Tag['tid']]))
								get_sort_reorder_appearances($EQG);

							respond('Tag deleted successfully', 1, $AppearancePage && $Tag['type'] === 'ep' ? array(
								'needupdate' => true,
								'eps' => get_episode_appearances($Appearance['id'], NOWRAP),
							) : null);
						}
					}
					$data = array();

					if ($merging || $synoning){
						if ($synoning && !empty($Tag['synonym_of']))
							respond('This tag is already synonymized with a different tag');

						if (empty($_POST['targetid']))
							respond('Missing target tag ID');
						$Target = $CGDb->where('tid', intval($_POST['targetid'], 10))->getOne('tags');
						if (empty($Target))
							respond('Target tag does not exist');
						if (!empty($Target['synonym_of']))
							respond('Synonym tags cannot be synonymization targets');

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
							))) respond('Tag '.($merging?'merging':'synonimizing')." failed, please re-try.<br>Technical details: ponyid={$tg['ponyid']} tid={$Target['tid']}");
						}
						if ($merging)
							// No need to delete "tagged" table entries, constraints do it for us
							$CGDb->where('tid', $Tag['tid'])->delete('tags');
						else {
							$CGDb->where('tid', $Tag['tid'])->delete('tagged');
							$CGDb->where('tid', $Tag['tid'])->update('tags', array('synonym_of' => $Target['tid'], 'uses' => 0));
						}

						update_tag_count($Target['tid']);
						respond('Tags successfully '.($merging?'merged':'synonymized'), 1, $synoning ? array('target' => $Target) : null);
					}
					else if ($unsynoning){
						if (empty($Tag['synonym_of']))
							respond(true);

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
									))) respond("Tag synonym removal process failed, please re-try.<br>Technical details: ponyid={$tg['ponyid']} tid={$Tag['tid']}");
									$uses++;
								}
							}
							else $keep_tagged = false;
						}

						if (!$CGDb->where('tid', $Tag['tid'])->update('tags', array('synonym_of' => null, 'uses' => $uses)))
							respond(ERR_DB_FAIL);

						respond(array('keep_tagged' => $keep_tagged));
					}

					$name = isset($_POST['name']) ? strtolower(trim($_POST['name'])) : null;
					$nl = !empty($name) ? strlen($name) : 0;
					if ($nl < 3 || $nl > 30)
						respond("Tag name must be between 3 and 30 characters");
					if ($name[0] === '-')
						respond('Tag name cannot start with a dash');
					check_string_valid($name,'Tag name',INVERSE_TAG_NAME_PATTERN);
					$sanitized_name = regex_replace(new RegExp('[^a-z\d]'),'',$name);
					if (regex_match(new RegExp('^(b+[a4]+w*d+|g+[uo0]+d+|(?:b+[ae3]+|w+[o0]+r+[s5]+[t7])[s5]+t+)(e+r+|e+s+t+)?p+[o0]+[wh]*n+[ye3]*$'),$sanitized_name))
						respond('Highly opinion-based tags are not allowed');
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
							respond("Invalid tag type: $type");

						if ($type == 'ep'){
							if (!$surelyAnEpisodeTag)
								respond('Episode tags must be in the format of <strong>s##e##[-##]</strong> where # represents a number<br>Allowed seasons: 1-8, episodes: 1-26');
							$data['name'] = $epTagName;
						}
						else if ($surelyAnEpisodeTag)
							$type = $ep;
						$data['type'] = $type;
					}

					if (!$new) $CGDb->where('tid',$Tag['tid'],'!=');
					if ($CGDb->where('name', $data['name'])->has('tags'))
						respond("There's already a tag with the same name");

					if (empty($_POST['title'])) $data['title'] = '';
					else {
						$title = trim($_POST['title']);
						$tl = strlen($title);
						if ($tl > 255)
							respond("Your title exceeds the 255 character limit by ".($tl-255)." characters. Please keep it short, or if more space is necessary, ask the developer to increase the limit.");
						$data['title'] = $title;
					}

					if ($new){
						$TagID = $CGDb->insert('tags', $data, 'tid');
						if (!$TagID) respond(ERR_DB_FAIL);
						$data['tid'] = $TagID;

						if (!empty($_POST['addto']) && is_numeric($_POST['addto'])){
							$AppearanceID = intval($_POST['addto'], 10);
							if ($AppearanceID === 0)
								respond("The tag was created, <strong>but</strong> it could not be added to the appearance because it can't be tagged.", 1);

							$Appearance = $CGDb->where('id', $AppearanceID)->getOne('appearances');
							if (empty($Appearance))
								respond("The tag was created, <strong>but</strong> it could not be added to the appearance (<a href='/{$color}guide/appearance/$AppearanceID'>#$AppearanceID</a>) because it doesn't seem to exist. Please try adding the tag manually.", 1);

							if (!$CGDb->insert('tagged',array(
								'tid' => $data['tid'],
								'ponyid' => $Appearance['id']
							))) respond(ERR_DB_FAIL);
							update_tag_count($data['tid']);
							respond(array('tags' => get_tags_html($Appearance['id'], NOWRAP)));
						}
					}
					else {
						$CGDb->where('tid', $Tag['tid'])->update('tags', $data);
						$data = array_merge($Tag, $data);
					}

					respond($data);
				}
				else if (regex_match(new RegExp('^([gs]et|make|del)cg(?:/(\d+))?$'), $data, $_match)){
					$setting = $_match[1] === 'set';
					$getting = $_match[1] === 'get';
					$deleting = $_match[1] === 'del';
					$new = $_match[1] === 'make';

					if (!$new){
						if (empty($_match[2]))
							respond('Missing color group ID');
						$GroupID = intval($_match[2], 10);
						$Group = $CGDb->where('groupid', $GroupID)->getOne('colorgroups');
						if (empty($GroupID))
							respond("There's no $color group with the ID of $GroupID");

						if ($getting){
							$Group['Colors'] = get_colors($Group['groupid']);
							respond($Group);
						}

						if ($deleting){
							if (!$CGDb->where('groupid', $Group['groupid'])->delete('colorgroups'))
								respond(ERR_DB_FAIL);
							respond("$Color group deleted successfully", 1);
						}
					}
					$data = array();

					if (empty($_POST['label']))
						respond('Please specify a group name');
					$name = $_POST['label'];
					check_string_valid($name, "$Color group name", INVERSE_PRINTABLE_ASCII_REGEX);
					$nl = strlen($name);
					if ($nl < 2 || $nl > 30)
						respond('The group name must be between 2 and 30 characters in length');
					$data['label'] = $name;

					if (!empty($_POST['major'])){
						$major = true;

						if (empty($_POST['reason']))
							respond('Please specify a reason');
						$reason = $_POST['reason'];
						check_string_valid($reason, "Change reason", INVERSE_PRINTABLE_ASCII_REGEX);
						$rl = strlen($reason);
						if ($rl < 1 || $rl > 255)
							respond('The reason must be between 1 and 255 characters in length');
					}

					if ($new){
						if (!isset($_POST['ponyid']) || !is_numeric($_POST['ponyid']))
							respond('Missing appearance ID');
						$AppearanceID = intval($_POST['ponyid'], 10);
						$Appearance = $CGDb->where('id', $AppearanceID)->where('ishuman', $EQG)->getOne('appearances');
						if (empty($Appearance))
							respond('The specified appearance odes not exist');
						$data['ponyid'] = $AppearanceID;

						// Attempt to get order number of last color group for the appearance
						order_cgs();
						$LastGroup = get_cgs($AppearanceID, '"order"', 'DESC', 1);
						$data['order'] =  !empty($LastGroup['order']) ? $LastGroup['order']+1 : 1;

						$GroupID = $CGDb->insert('colorgroups', $data, 'groupid');
						if (!$GroupID)
							respond(ERR_DB_FAIL);
						$Group = array('groupid' => $GroupID);
					}
					else $CGDb->where('groupid', $Group['groupid'])->update('colorgroups', $data);


					if (empty($_POST['Colors']))
						respond("Missing list of {$color}s");
					$recvColors = JSON::Decode($_POST['Colors'], true);
					if (empty($recvColors))
						respond("Missing list of {$color}s");
					$colors = array();
					foreach ($recvColors as $part => $c){
						$append = array('order' => $part);
						$index = "(index: $part)";

						if (empty($c['label']))
							respond("You must specify a $color name $index");
						$label = trim($c['label']);
						check_string_valid($label, "$Color $index name", INVERSE_PRINTABLE_ASCII_REGEX);
						$ll = strlen($label);
						if ($ll < 3 || $ll > 30)
							respond("The $color name must be between 3 and 30 characters in length $index");
						$append['label'] = $label;

						if (empty($c['hex']))
							respond("You must specify a $color code $index");
						$hex = trim($c['hex']);
						if (!$HEX_COLOR_PATTERN->match($hex, $_match))
							respond("HEX $color is in an invalid format $index");
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
						respond("There were some issues while saving some of the colors. Please let the developer know about this error, so he can look into why this might've happened.");

					$colon = !$AppearancePage;
					$outputNames = $AppearancePage;

					if ($new) $response = array('cgs' => get_colors_html($Appearance['id'], NOWRAP, $colon, $outputNames));
					else $response = array('cg' => get_cg_html($Group['groupid'], NOWRAP, $colon, $outputNames));

					$AppearanceID = $new ? $Appearance['id'] : $Group['ponyid'];
					if (isset($major)){
						LogAction('color_modify',array(
							'ponyid' => $AppearanceID,
							'reason' => $reason,
						));
						$response['update'] = get_update_html($AppearanceID);
					}
					clear_rendered_image($AppearanceID);

					if (isset($_POST['APPEARANCE_PAGE']))
						$response['cm_img'] = "/{$color}guide/appearance/$AppearanceID.svg?t=".time();
					else $response['notes'] = get_notes_html($CGDb->where('id', $AppearanceID)->getOne('appearances'),  NOWRAP);

					respond($response);
				}
				else do404();
			}

			if (regex_match(new RegExp('^tags'),$data)){
				$ItemsPerPage = 20;
				$EntryCount = $CGDb->count('tags');
				list($Page,$MaxPages) = calc_page($EntryCount);

				fix_path("/{$color}guide/tags/$Page");
				$heading = "Tags";
				$title = "Page $Page - $heading - $Color Guide";
				$Pagination = get_pagination_html("{$color}guide/tags");

				$Tags = get_tags(null,array($ItemsPerPage*($Page-1), $ItemsPerPage), true);

				if (isset($_GET['js']))
					pagination_response(get_taglist_html($Tags, NOWRAP), '#tags tbody');

				$js = array('paginate');
				if (PERM('inspector'))
					$js[] = "$do-tags";

				loadPage(array(
					'title' => $title,
					'heading' => $heading,
					'view' => "$do-tags",
					'css' => "$do-tags",
					'js' => $js,
				));
			}

			if (regex_match(new RegExp('^changes'),$data)){
				$ItemsPerPage = 50;
				$EntryCount = $Database->count('log__color_modify');
				list($Page,$MaxPages) = calc_page($EntryCount);

				fix_path("/{$color}guide/changes/$Page");
				$heading = "Major $Color Changes";
				$title = "Page $Page - $heading - $Color Guide";
				$Pagination = get_pagination_html("{$color}guide/changes");

				$Changes = get_updates(null, ($ItemsPerPage*($Page-1)).", $ItemsPerPage");

				if (isset($_GET['js']))
					pagination_response(render_changes_html($Changes, NOWRAP, SHOW_APPEARANCE_NAMES), '#changes');

				loadPage(array(
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
					do404();

				$asFile = !empty($_match[2]);
				if ($asFile){
					switch ($_match[2]){
						case 'png': render_appearance_png($Appearance);
						case 'svg': render_cm_direction_svg($Appearance['id'], $Appearance['cm_dir']);
					}
					# rendering functions internally call die(), so execution stops here #
				}

				$SafeLabel = trim(regex_replace(new RegExp('-+'),'-',regex_replace(new RegExp('[^A-Za-z\d\-]'),'-',$Appearance['label'])),'-');
				fix_path("$CGPath/appearance/$SafeLabel-{$Appearance['id']}");
				$heading = $Appearance['label'];
				if ($Appearance['id'] === 0){
					$title = $heading;
					if ($color !== 'color')
						$title = str_replace('color',$color,$title);
				}
				else $title = "{$Color}s for $heading";

				$Changes = get_updates($Appearance['id']);

				$settings = array(
					'title' => "$title - $Color Guide",
					'heading' => $heading,
					'view' => "$do-single",
					'css' => array($do, "$do-single"),
					'js' => array('jquery.qtip', 'jquery.ctxmenu', $do, "$do-single"),
				);
				if (PERM('inspector')){
					$settings['css'] = array_merge($settings['css'], $GUIDE_MANAGE_CSS);
					$settings['js'] = array_merge($settings['js'],$GUIDE_MANAGE_JS);
				}
				loadPage($settings);
			}
			else if ($data === 'full'){
				$GuideOrder = !isset($_REQUEST['alphabetically']) && !$EQG;
				if (!$GuideOrder)
					$CGDb->orderBy('label','ASC');
				$Appearances = get_appearances($EQG,null,'id,label');


				if (isset($_REQUEST['ajax']))
					respond(array('html' => render_full_list_html($Appearances, $GuideOrder, NOWRAP)));

				$js = array();
				if (PERM('inspector'))
					$js[] = 'Sortable';
				$js[] = "$do-full";

				loadPage(array(
					'title' => "Full List - $Color Guide",
					'view' => "$do-full",
					'css' => "$do-full",
					'js' => $js,
				));
			}

			$title = '';
			if (empty($_GET['q'])){
				$EntryCount = $CGDb->where('ishuman',$EQG)->count('appearances');
				list($Page,$MaxPages) = calc_page($EntryCount);
				$Ponies = get_appearances($EQG, array($ItemsPerPage*($Page-1), $ItemsPerPage));
			}
			else {
				$tags = array_map('trim',explode(',',strtolower($_GET['q'])));
				$Tags = array();
				foreach ($tags as $part => $tag){
					$num = $part+1;
					$_MSG = check_string_valid($tag,"Tag #$num's name",INVERSE_TAG_NAME_PATTERN, !isset($_GET['js']));
					if (is_string($_MSG)) break;

					$Tag = get_actual_tag($tag, 'name', false, 'tid, name');
					if (empty($Tag)){
						$_MSG = "The tag $tag does not exist";
						if (isset($_REQUEST['js']))
							respond($_MSG);
					}
					if (!in_array($Tag['tid'], $Tags))
						$Tags[] = $Tag['tid'];
				}
				if (empty($_MSG)){
					if (empty($Tags)){
						$_MSG = 'Your search matched no tags';
						if (isset($_REQUEST['js']))
							respond($_MSG);
						$tc = 0;
					}
					else $tc = count($Tags);
					if ($tc > 6){
						$_MSG = 'You cannot search for more than 6 tags';
						if (isset($_GET['js']))
							respond($_MSG);
					}
				}

				if (empty($_MSG)){
					$title .= "{$_GET['q']} - ";
					$IsHuman = $EQG ? 'true' : 'false';

					$query =
						"SELECT @coloumn FROM appearances p
						WHERE p.id IN (
							SELECT ponyid FROM (
								SELECT t.ponyid
								FROM tagged t
								WHERE t.tid IN (".implode(',', $Tags).")
								GROUP BY t.ponyid
								HAVING COUNT(t.tid) = $tc
							) tg
						) AND p.ishuman = $IsHuman
						--limit";
					$EntryCount = $CGDb->rawQuerySingle(str_replace('@coloumn','COUNT(*) as count',$query))['count'];
					list($Page,$MaxPages) = calc_page($EntryCount);
					$Offset = $ItemsPerPage*($Page-1);

					$SearchQuery = str_replace('@coloumn','p.*',$query);
					$SearchQuery = str_replace('--limit',"ORDER BY p.order ASC LIMIT $ItemsPerPage OFFSET $Offset",$SearchQuery);
					$Ponies = $CGDb->rawQuery($SearchQuery);
				}
				else {
					$Page = $MaxPages = 1;
					$Ponies = false;
				}
			}

			fix_path("$CGPath/$Page");
			$heading = ($EQG?'EQG ':'')."$Color Guide";
			$title .= "Page $Page - $heading";
			$Pagination = get_pagination_html("{$color}guide");

			if (isset($_GET['js']))
				pagination_response(render_ponies_html($Ponies, NOWRAP), '#list');

			$settings = array(
				'title' => $title,
				'heading' => $heading,
				'css' => array($do),
				'js' => array('jquery.qtip', 'jquery.ctxmenu', $do, 'paginate'),
			);
			if (PERM('inspector')){
				$settings['css'] = array_merge($settings['css'], $GUIDE_MANAGE_CSS);
				$settings['js'] = array_merge($settings['js'],$GUIDE_MANAGE_JS);
			}
			loadPage($settings);
		break;
		case "browser":
			$AgentString = null;
			if (is_numeric($data) && PERM('developer')){
				$SessionID = intval($data, 10);
				$Session = $Database->where('id', $SessionID)->getOne('sessions');
				if (!empty($Session))
					$AgentString = $Session['user_agent'];
			}
			$browser = browser($AgentString);

			fix_path('/browser'.(!empty($Session)?"/{$Session['id']}":''));

			loadPage(array(
				'title' => 'Browser recognition test page',
				'do-css',
				'no-robots',
			));
		break;
		case "users":
			if (!PERM('inspector'))
				do404();

			loadPage(array(
				'title' => 'Users',
				'do-css'
			));
		break;
		case "blending":
			$HexPattern = regex_replace(new RegExp('^/(.*)/.*$'),'$1',$HEX_COLOR_PATTERN->jsExport());
			loadPage(array(
				'title' => "$Color Blending Calculator",
				'do-css', 'do-js',
			));
		break;
		case "404":
		default:
			do404();
		break;
	}
