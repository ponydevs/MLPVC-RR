<?php
	
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
			if (!$signedIn) respond('Already signed out',1);
			detectCSRF();

			if (isset($_REQUEST['unlink'])){
				try {
					da_request('https://www.deviantart.com/oauth2/revoke', array('token' => $currentUser['Session']['access']));
					$Database->where('id', $currentUser['id'])->update('users',array('stash_allowed' => false));
					$UserStashCache = APPATH."../stash-cache/{$currentUser['id']}.json";
					if (file_exists($UserStashCache))
						unlink($UserStashCache);
				}
				catch (DARequestException $e){
					respond("Coulnd not revoke the site's access: {$e->getMessage()} (HTTP {$e->getCode()})");
				}
			}

			if (isset($_REQUEST['unlink']) || isset($_REQUEST['everywhere'])){
				$col = 'user';
				$val = $currentUser['id'];
				if (!empty($_POST['username'])){
					if (!PERM('manager') || isset($_REQUEST['unlink']))
						respond();
					if (!preg_match(USERNAME_PATTERN, $_POST['username']))
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

			if (empty($TargetUser)){
				Cookie::delete('access');
				respond((isset($_REQUEST['unlink'])?'Your account has been unlinked from our site':'You have been signed out successfully').'. Goodbye!',1);
			}
			else respond("All sessions of {$TargetUser['name']} have been removed", 1);
		break;
		case "da-auth":
			da_handle_auth();
		break;
		case "post":
			if (RQMTHD !== 'POST') do404();
			if (!PERM('user')) respond();
			detectCSRF();

			$_match = array();
			if (preg_match('~^([gs]et)-(request|reservation)/(\d+)$~ ', $data, $_match)){
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
			else if (preg_match('~^set-(request|reservation)-image/(\d+)$~ ', $data, $_match)){
				if (!PERM('inspector'))
					respond();

				$thing = $_match[1];
				$Post = $Database->where('id', $_match[2])->getOne("{$thing}s");
				if (empty($Post))
					respond("The specified $thing does not exist");
				if ($Post['lock'])
					respond('This post is locked, its image cannot be changed.');

				$Image = check_post_image(false, $Post);

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

			if (!empty($_POST['what'])){
				if (!in_array($_POST['what'],$POST_TYPES))
					respond('Invalid post type');
				$what = $_POST['what'];
				if ($what === 'reservation'){
					if (!PERM('member'))
						respond();
					res_limit_check();
				}
			}

			if (!empty($_POST['image_url']))
				$Image = check_post_image(empty($what));
			else if (empty($what)) respond("Please provide an image URL ");

			$insert = array(
				'preview' => $Image->preview,
				'fullsize' => $Image->fullsize,
			);

			if (($_POST['season'] != '0' && empty($_POST['season'])) || empty($_POST['episode']))
				respond('Missing episode identifiers');
			$epdata = get_real_episode(intval($_POST['season'], 10), intval($_POST['episode'], 10), ALLOW_SEASON_ZERO);
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

				if ($what === 'reservation' && !PERM('member', $PostAs['role']))
					respond('The user you wanted to post as is not a club member');

				$ByID = $PostAs['id'];
			}

			switch ($what){
				case "request": $insert['requested_by'] = $ByID; break;
				case "reservation": $insert['reserved_by'] = $ByID; break;
			}

			check_post_post($what, $insert);

			if (!$Database->insert("{$what}s",$insert))
				respond(ERR_DB_FAIL);
			respond('Submission complete',1);
		break;
		case "reserving":
			if (RQMTHD !== 'POST') do404();
			$match = array();
			if (empty($data) || !preg_match('/^(requests?|reservations?)(?:\/(\d+))?$/',$data,$match))
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
						if (!preg_match('~^'.USERNAME_PATTERN.'$~', $post_as))
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
		case "upload-stash":
			if (empty($data))
				do404();
			if (RQMTHD !== 'POST'){
				if (preg_match('~close-dialog/(\d{5})~', $data, $_match))
					die("<script>var k=' authHandle'+$_match[1];if(typeof window.opener[k]==='function')window.opener[k]({$currentUser['stash_allowed']});window.close()</script>");
				do404();
			}

			if (!PERM('user'))
				respond();
			detectCSRF();

			$_match = array();
			if ($data === 'get'){
				$StashCache = APPATH.'../stash-cache';
				$Post = array();
				$CachedData = array();

				$UserStashCache = "$StashCache/{$currentUser['id']}.json";
				upload_folder_create($UserStashCache);
				if (file_exists($UserStashCache)){
					$CachedData = JSON::Decode(file_get_contents($UserStashCache));
					$Post['cursor'] = $CachedData['cursor'];
				}

				$Request = stash_get_all_items($Post);
				if (!empty($CachedData)){
					if (is_array($Request)){
						$Data = array_replace_recursive($CachedData, $Request);
						if ($Request['has_more'])
							write_stash_cache($UserStashCache, $Data);
					}
					else {
						$Data = $CachedData;
						trigger_error('Could not fetch Sta.sh folder data, falling back to cache', E_USER_WARNING);
					}
				}
				else {
					if (!is_array($Request))
						respond('Error retrieving folders');
					$Data = $Request;
					write_stash_cache($UserStashCache, $Data);
				}

				if (empty($Data['entries']))
					respond('Your Sta.sh is empty');

				$Items = array();
				foreach ($Data['entries'] as $entry){
					$metadata = $entry['metadata'];

					if (!empty($metadata['files'][2]['src']))
						$image = $metadata['files'][2]['src'];
					else $image = end($metadata['files'])['src'];

					$Items[] = array(
						'name' => $metadata['title'],
						'image' => makeHttps($image),
						'itemid' => $entry['itemid'],
					);
				}

				respond(array('items' => $Items));
			}
			else do404();
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

				if (preg_match('/^delete\/'.EPISODE_ID_PATTERN.'$/',$data,$_match)){
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
				else if (preg_match('/^((?:request|reservation)s)\/'.EPISODE_ID_PATTERN.'$/', $data, $_match)){
					$Episode = get_real_episode($_match[2],$_match[3],ALLOW_SEASON_ZERO);
					if (empty($Episode))
						respond("There's no episode with this season & episode number");
					$only = $_match[1] === 'requests' ? ONLY_REQUESTS : ONLY_RESERVATIONS;
					respond(array(
						'render' => call_user_func("{$_match[1]}_render",get_posts($Episode['season'], $Episode['episode'], $only)),
					));
				}
				else if (preg_match('/^vote\/'.EPISODE_ID_PATTERN.'$/', $data, $_match)){
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
				else if (preg_match('/^(([sg])et)?videos\/'.EPISODE_ID_PATTERN.'$/', $data, $_match)){
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
								));
							}
						}
					}

					respond('Links updated',1,array('epsection' => render_ep_video($Episode)));
				}
				else {
					if (!PERM('inspector')) respond();
					$editing = preg_match('/^edit\/'.EPISODE_ID_PATTERN.'$/',$data,$_match);
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
					$insert['title'] = $_POST['title'];
					if (strlen($insert['title']) < 5 || strlen($insert['title']) > 35)
						respond('Episode title must be between 5 and 35 characters');
					if (!preg_match(EP_TITLE_REGEX, $insert['title']))
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
					respond('Episode saved successfuly', 1);
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
			if (!preg_match('/^([a-z\-]+|\d+)$/',$data))
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
				if (!empty($data) && preg_match('~^stats-(posts|approvals)$~',$data,$_match)){
					$stat = $_match[1];
					$CachePath = APPATH."../stats/$stat.json";
					if (file_exists($CachePath) && filemtime($CachePath) > time() - $StatCacheDuration)
						respond(array('data' => JSON::Decode(file_get_contents($CachePath))));

					$Data = array('datasets' => array(), 'timestamp' => date('c'));

					switch ($stat){
						case 'posts':
							$Labels = $Database->rawQuery(
								"SELECT key FROM
								(
									SELECT posted, to_char(posted,'FMDDth FMMon') AS key FROM requests
									WHERE posted > NOW() - INTERVAL '20 DAYS'
									UNION ALL
									SELECT posted, to_char(posted,'FMDDth FMMon') AS key FROM reservations
									WHERE posted > NOW() - INTERVAL '20 DAYS'
								) t
								GROUP BY key
								ORDER BY MIN(t.posted)");

							process_stat_labels();

							$query =
								"SELECT
									to_char(MIN(posted),'FMDDth FMMon') AS key,
									COUNT(*)::INT AS cnt
								FROM table_name t
								WHERE posted > NOW() - INTERVAL '20 DAYS'
								GROUP BY DATE(posted)
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
								"SELECT to_char(timestamp,'FMDDth FMMon') AS key
								FROM log
								WHERE timestamp > NOW() - INTERVAL '1 MONTH' AND reftype = 'post_lock'
								GROUP BY key
								ORDER BY MIN(timestamp)");

							process_stat_labels();

							$Approvals = $Database->rawQuery(
								"SELECT
									to_char(MIN(timestamp),'FMDDth FMMon') AS key,
									COUNT(*)::INT AS cnt
								FROM log
								WHERE timestamp > NOW() - INTERVAL '1 MONTH' AND reftype = 'post_lock'
								GROUP BY DATE(timestamp)
								ORDER BY MIN(timestamp)"
							);
							if (!empty($Approvals)){
								$Dataset = array('label' => 'Approved posts');
								process_usage_stats($Approvals, $Dataset);
								$Data['datasets'][] = $Dataset;
							}
						break;
					}

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
			if (RQMTHD === "POST"){
				if (!PERM('inspector')) respond();
				$_match = array();
				if (preg_match('/^details\/(\d+)/', $data, $_match)){
					$EntryID = intval($_match[1], 10);

					$MainEntry = $Database->where('entryid', $EntryID)->getOne('log');
					if (empty($MainEntry)) respond('Log entry does not exist');
					if (empty($MainEntry['refid'])) respond('There are no details to show');

					$Details = $Database->where('entryid', $MainEntry['refid'])->getOne("log__{$MainEntry['reftype']}");
					if (empty($Details)) respond('Failed to retrieve details');

					respond(format_log_details($MainEntry['reftype'],$Details));
				}
			}

			if (!PERM('inspector')) do404();

			$ItemsPerPage = 20;
			$EntryCount = $Database->count('log');
			list($Page,$MaxPages) = calc_page($EntryCount);

			fix_path("/logs/$Page");
			$heading = 'Logs';
			$title = "Page $Page - $heading";
			$Pagination = get_pagination_html('logs');

			$LogItems = $Database
				->orderBy('timestamp')
				->orderBy('entryid')
				->get('log',array($ItemsPerPage*($Page-1), $ItemsPerPage));

			if (isset($_GET['js']))
				pagination_response(log_tbody_render($LogItems), '#logs tbody');

			loadPage(array(
				'title' => $title,
				'do-css',
				'js' => array($do, 'paginate'),
			));
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

				if (preg_match('/^newgroup\/'.USERNAME_PATTERN.'$/',$data,$_match)){
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

					respond('Group changed successfully', 1);
				}
				else if (preg_match('/^sessiondel\/(\d+)$/',$data,$_match)){
					$Session = $Database->where('id', $_match[1])->getOne('sessions');
					if (empty($Session))
						respond('This session does not exist');
					if ($Session['user'] !== $currentUser['id'] && !PERM('inspector'))
						respond('You are not allowed to delete this session');

					if (!$Database->where('id', $Session['id'])->delete('sessions'))
						respond('Session could not be deleted');
					respond('Session successfully removed',1);
				}
				else if (preg_match('/^(un-)?banish\/'.USERNAME_PATTERN.'$/', $data, $_match)){
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
			else if (preg_match('/^'.USERNAME_PATTERN.'$/', $data, $_match))
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

				switch ($data){
					case 'gettags':
						$viaTypeahead = !empty($_GET['s']);
						$limit = null;
						$cols = "tid, name, type";
						if ($viaTypeahead){
							if (!preg_match('~'.TAG_NAME_PATTERN.'~u', $_GET['s']))
								typeahead_results('[]');

							$query = trim(strtolower($_GET['s']));
							$TagCheck = ep_tag_name_check($query);
							if ($TagCheck !== false)
								$query = $TagCheck;
							$CGDb->where('name',"%$query%",'LIKE');
							$limit = 5;
							$cols = "tid, name, CONCAT('typ-', type) as type";
						}
						else $CGDb->orderBy('type','ASC');

						if (isset($_POST['not']) && is_numeric($_POST['not']))
							$CGDb->where('tid',intval($_POST['not'], 10),'!=');

						$Tags = $CGDb->orderBy('name','ASC')->get('tags',$limit,$cols);

						typeahead_results(empty($Tags) ? '[]' : $Tags);
					break;
					case 'full':
						if (isset($_REQUEST['reorder'])){
							if (!PERM('inspector'))
								respond();
							if (empty($_POST['list']))
								respond('The list of IDs is missing');

							$list = trim($_POST['list']);
							if (!preg_match('~^\d+(?:,\d+)+$~', $list))
								respond('The list of IDs is not formatted properly');

							reorder_appearances($list);

							respond(array('html' => render_full_list_html(get_appearances($EQG,null,'id,label'), true, NOWRAP)));
						}
						else do404();
					break;
				}

				$_match = array();
				if (preg_match('~^(rename|delete|make|(?:[gs]et|del)(?:sprite|cgs)?|tag|untag|clearrendercache|applytemplate)(?:/(\d+))?$~', $data, $_match)){
					$action = $_match[1];

					if ($action !== 'make'){
						if (strlen($_match[2]) === 0)
							respond('Missing appearance ID');
						$Appearance = $CGDb->where('id', intval($_match[2], 10))->where('ishuman', $EQG)->getOne('appearances');
						if (empty($Appearance))
							respond("The specified appearance does not exist");
					}

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
							$data['label'] = $label;

							if (!empty($_POST['notes'])){
								$notes = trim($_POST['notes']);
								check_string_valid($label, "Appearance notes", INVERSE_PRINTABLE_ASCII_REGEX);
								if (strlen($notes) > 1000 && $Appearance['id'] !== 0)
									respond('Appearance notes cannot be longer than 1000 characters');
								if ($action === 'make' || $notes !== $Appearance['notes'])
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
								if ($Appearance['cm_dir'] !== $cm_dir)
									$data['cm_dir'] = $cm_dir;

								$cm_preview = trim($_POST['cm_preview']);
								if (empty($cm_preview))
									$data['cm_preview'] = null;
								else if ($cm_preview !== $Appearance['cm_preview']){
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

							$query = $action === 'set'
								? $CGDb->where('id', $Appearance['id'])->update('appearances', $data)
								: $CGDb->insert('appearances', $data, 'id');
							if (!$query)
								respond(ERR_DB_FAIL);

							if ($action === 'make'){
								$data['message'] = 'Appearance added successfully';
								$count = $CGDb->where('ishuman', $EQG)->count('appearances');

								$data['id'] = $query;

								if (isset($_POST['template'])){
									try {
										apply_template($query, $EQG);
									}
									catch (Exception $e){
										$data['message'] .= ", but applying the template failed";
										$data['info'] = "The common color groups could not be added.<br>Reason: ".$e->getMessage();
										respond($data);
									}
								}
							}
							else {
								clear_rendered_image($Appearance['id']);
								$data['id'] = $Appearance['id'];
								if (empty($data['notes']))
									$data['notes'] = $Appearance['notes'];
							}

							if (isset($_POST['noreturn']))
								respond(true);
							$data['notes'] = get_notes_html($data, NOWRAP);
							respond($data);
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
						case "setcgs":
							if ($action === 'getcgs'){
								$cgs = get_cgs($Appearance['id'],'groupid, label');
								if (empty($cgs))
									respond('This appearance does not have any color groups');
								respond(array('cgs' => $cgs));
							}

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
						case "tag":
							if ($Appearance['id'] === 0)
								respond('This appearance cannot be tagged');

							if (empty($_POST['tag_name']))
								respond('Tag name is not specified');
							$tag_name = strtolower(trim($_POST['tag_name']));
							if (!preg_match('~'.TAG_NAME_PATTERN.'~u',$tag_name))
								respond('Invalid tag name');

							$TagCheck = ep_tag_name_check($tag_name);
							if ($TagCheck !== false)
								$tag_name = $TagCheck;

							$Tag = $CGDb->where('name', $tag_name)->getOne('tags');
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
							if (isset($_POST['needupdate']) && $Tag['type'] === 'ep'){
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
							$TagID = intval($_POST['tag'], 10);
							$Tag = $CGDb->where('tid', $TagID)->getOne('tags');
							if (empty($Tag))
								respond('Tag does not exist');

							if (!$CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->has('tagged'))
								respond('This appearance does not have this tag');

							if (!$CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->delete('tagged'))
								respond(ERR_DB_FAIL);

							update_tag_count($Tag['tid']);
							if (isset($GroupTagIDs_Assoc[$Tag['tid']]))
								get_sort_reorder_appearances($EQG);

							$response = array('tags' => get_tags_html($Appearance['id'], NOWRAP));
							if (isset($_POST['needupdate']) && $Tag['type'] === 'ep'){
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
				else if (preg_match('~^([gs]et|make|del|merge|recount)tag(?:/(\d+))?$~', $data, $_match)){
					$action = $_match[1];

					if ($action === 'recount'){
						if (empty($_POST['tagids']))
							respond('Missing list of tags to update');

						$tagIDs = array_map('intval', explode(',',trim($_POST['tagids'])));
						$counts = array();
						$updates = 0;
						foreach ($tagIDs as $tid){
							if ($CGDb->where('tid', $tid)->has('tags')){
								$result = update_tag_count($tid, true);
								if ($result['status'])
									$updates++;
								$counts[$tid] = $result['count'];
							}
						}

						respond(
							(
								!$updates
								? 'There was no change in the tag useage counts'
								: "$updates tag".($updates!==1?"s'":"'s").' use count'.($updates!==1?'s were':' was').' updated'
							),
							1,
							array('counts' => $counts)
						);
					}

					$setting = $action === 'set';
					$getting = $action === 'get';
					$deleting = $action === 'del';
					$new = $action === 'make';
					$merging = $action === 'merge';

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
								$Uses = $CGDb->where('tid', $Tag['tid'])->count('tagged');
								if ($Uses > 0)
									respond('<p>This tag is currently used on '.plur('appearance',$Uses,PREPEND_NUMBER).'</p><p>Deleting will <strong class="color-red">permanently remove</strong> the tag from those appearances!</p><p>Are you <em class="color-red">REALLY</em> sure about this?</p>',0,array('confirm' => true));
							}

							if (!$CGDb->where('tid', $Tag['tid'])->delete('tags'))
								respond(ERR_DB_FAIL);

							if (isset($GroupTagIDs_Assoc[$Tag['tid']]))
								get_sort_reorder_appearances($EQG);

							respond('Tag deleted successfully', 1, isset($_POST['needupdate']) && $Tag['type'] === 'ep' ? array(
								'needupdate' => true,
								'eps' => get_episode_appearances($Appearance['id'], NOWRAP),
							) : null);
						}
					}
					$data = array();

					if ($merging){
						if (empty($_POST['targetid']))
							respond('Missing target tag ID');
						$TargetID = intval($_POST['targetid'], 10);
						$Target = $CGDb->where('tid', $TargetID)->getOne('tags','tid');
						if (empty($Target))
							respond('Target tag does not exist');

						$_TargetTagged = $CGDb->where('tid', $Target['tid'])->get('tagged','ponyid');
						$TargetTagged = array();
						foreach ($_TargetTagged as $tg)
							$TargetTagged[] = $tg['ponyid'];

						$Tagged = $CGDb->where('tid', $Tag['tid'])->get('tagged','ponyid');
						foreach ($Tagged as $tg){
							if (in_array($tg['ponyid'], $TargetTagged)) continue;

							if (!$CGDb->insert('tagged',array(
								'tid' => $Target['tid'],
								'ponyid' => $tg['ponyid']
							))) respond("Tag merge process failed, please re-try.<br>Technical details: ponyid={$tg['ponyid']} tid={$Target['tid']}");
						}
						$CGDb->where('tid', $Tag['tid'])->delete('tags');

						update_tag_count($Target['tid']);
						respond('Tags successfully merged', 1);
					}

					$name = isset($_POST['name']) ? strtolower(trim($_POST['name'])) : null;
					$nl = !empty($name) ? strlen($name) : 0;
					if ($nl < 3 || $nl > 30)
						respond("Tag name must be between 3 and 30 characters");
					if ($name[0] === '-')
						respond('Tag name cannot start with a dash');
					check_string_valid($name,'Tag name',INVERSE_TAG_NAME_PATTERN);
					$data['name'] = $name;

					if (empty($_POST['type'])) $data['type'] = null;
					else {
						$type = trim($_POST['type']);
						if (!in_array($type, $TAG_TYPES))
							respond("Invalid tag type: $type");

						$tagName = ep_tag_name_check($data['name']);

						if ($type == 'ep'){
							if ($tagName === false)
								respond('Episode tags must be in the format of <strong>s##e##[-##]</strong> where # represents a number<br>Allowed seasons: 1-8, episodes: 1-26');
							$data['name'] = $tagName;
						}
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
				else if (preg_match('~^([gs]et|make|del)cg(?:/(\d+))?$~', $data, $_match)){
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
					$colorIDs = array();
					$colors = array();
					foreach ($recvColors as $part => $c){
						$append = array('order' => $part);
						$index = "(index: $part)";

						if (!empty($c['colorid']) && is_numeric($c['colorid'])){
							$append['colorid'] = intval($c['colorid'], 10);
							$colorIDs[] = $append['colorid'];
						}

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
						if (!preg_match(HEX_COLOR_PATTERN, $hex, $_match))
							respond("HEX $color is in an invalid format $index");
						$append['hex'] = '#'.strtoupper($_match[1]);

						$colors[] = $append;
					}
					if (!$new && !empty($colorIDs))
						$CGDb->where('groupid', $Group['groupid'])->where('colorid NOT IN ('.implode(',', $colorIDs).')')->delete('colors');
					$colorErrors = array();
					foreach ($colors as $c){
						if (isset($c['colorid']))
							$CGDb->where('groupid', $Group['groupid'])->where('colorid', $c['colorid'])->update('colors',$c);
						else {
							$c['groupid'] = $Group['groupid'];
							if (!$CGDb->insert('colors', $c))
								$colorErrors[] = ERR_DB_FAIL;
						}
					}
					if (!empty($colorErrors))
						respond("There were some issues while saving your changes. Details:\n".implode("\n",$colorErrors));

					$colon = !isset($_POST['NO_COLON']);
					$outputNames = isset($_POST['OUTPUT_COLOR_NAMES']);

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
					if (isset($_POST['RETURN_CM_IMAGE']))
						$response['cm_img'] = "/{$color}guide/appearance/$AppearanceID.svg?t=".time();

					respond($response);
				}
				else do404();
			}

			if (preg_match('~^tags~',$data)){
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

			if (preg_match('~^changes~',$data)){
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

			$EQG = preg_match(EQG_URL_PATTERN, $data);
			if ($EQG)
				$data = preg_replace(EQG_URL_PATTERN, '', $data);
			$CGPath = "/{$color}guide".($EQG?'/eqg':'');

			$GUIDE_MANAGE_JS = array(
				'jquery.uploadzone',
				'twitter-typeahead',
				'handlebars-v3.0.3',
				'Sortable',
				"$do-manage"
			);

			$_match = array();
			if (preg_match('~^appearance/(?:[A-Za-z\d\-]+-)?(\d+)(?:\.(png|svg))?~',$data,$_match)){
				$Appearance = $CGDb->where('id', (int)$_match[1])->where('ishuman', $EQG)->getOne('appearances');
				if (empty($Appearance))
					do404();

				$asFile = !empty($_match[2]);
				if ($asFile){
					switch ($_match[2]){
						case 'png': render_appearance_png($Appearance);
						case 'svg': render_cm_direction_svg($Appearance['id'], $Appearance['cm_dir']);
					}
				}

				$SafeLabel = trim(preg_replace('~-+~','-',preg_replace('~[^A-Za-z\d\-]~','-',$Appearance['label'])),'-');
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
					'js' => array('jquery.qtip', 'jquery.ctxmenu', $do),
				);
				if (PERM('inspector')){
					$settings['css'][] = "$do-manage";
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

					$Tag = $CGDb->where('name', $tag)->getOne('tags', 'tid');
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
				$settings['css'][] = "$do-manage";
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
				'title' => 'Your browser',
				'do-css'
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
			$HexPattern = preg_replace('~^/(.+)/u$~','$1',HEX_COLOR_PATTERN);
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
