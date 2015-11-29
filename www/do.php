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

			if (isset($_REQUEST['unlink']))
				da_request('https://www.deviantart.com/oauth2/revoke',array('token' => $currentUser['Session']['access']));

			if (isset($_REQUEST['unlink']) || isset($_REQUEST['everywhere'])){
				$col = 'user';
				$val = $currentUser['id'];
			}
			else {
				$col = 'id';
				$val = $currentUser['Session']['id'];
			}

			if (!$Database->where($col,$val)->delete('sessions'))
				respond('Could not remove information from database');

			Cookie::delete('access');
			respond((isset($_REQUEST['unlink'])?'Your account has been unlinked from our site':'You have been signed out successfully').'. Goodbye!',1);
		break;
		case "da-auth":
			if ($signedIn) header('Location: /');

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
					if ($thing === 'request')
						$response['type'] = $Post['type'];
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

				$Image = check_post_image();

				// Check image availability
				if (!@getimagesize($Image->preview)){
					sleep(1);
					if (!@getimagesize($Image->preview))
						respond("The specified file does not appear to exist. Please verify that you can reach the following URL: <a href='{$Image->preview}'>{$Image->preview}</a>");
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
							respond("You already reserved this $type");
						else respond("This $type has already been reserved by somepony else");
					}
					if ($locking && !empty($Thing['deviation_id'])){
						$Status = is_deviation_in_vectorclub($Thing['deviation_id']);
						if ($Status !== true)
							respond(
								$Status === false
								? "It looks like the deviation has not been accepted into the group yet"
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
						respond($message, 1);
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
			else {
				$data = "S{$CurrentEpisode['season']}E{$CurrentEpisode['episode']}";
				fix_path("/episode/$data", 302);
				loadEpisodePage();
			}
		break;
		case "episode":
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

				$_match = array();
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

					if (isset($_REQUEST['html']))
						respond(array('html' => get_episode_voting($Episode)));

					if (!PERM('user')) respond();

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
				else if (preg_match('/^([sg])etvideos\/'.EPISODE_ID_PATTERN.'$/', $data, $_match)){
					$Episode = get_real_episode($_match[2],$_match[3],ALLOW_SEASON_ZERO);
					if (empty($Episode))
						respond("There's no episode with this season & episode number");

					if ($Episode['season'] === 5 && $Episode['episode'] === 25 && $Episode['twoparter'] === true)
						respond("Don't mess with this episode until I can make a proper interface for this");

					$set = $_match[1] === 's';
					require_once "includes/Video.php";

					if (!$set){
						$return = array();
						$Vids = $Database->whereEp($Episode)->get('episodes__videos',null,'provider as name, id');
						foreach ($Vids as $i => $prov){
							if (!empty($prov['id'])) $return[$prov['name']] = Video::get_embed($prov['id'], $prov['name'], Video::URL_ONLY);
						}
						respond($return);
					}

					foreach (array('yt','dm') as $k){
						$set = null;
						if (!empty($_POST[$k])){
							try {
								$vid = new Video($_POST[$k]);
							}
							catch (Exception $e){
								respond("{$VIDEO_PROVIDER_NAMES[$k]} link issue: ".$e->getMessage());
							};
							if (!isset($vid->provider) || $vid->provider['name'] !== $k)
								respond("Incorrect {$VIDEO_PROVIDER_NAMES[$k]} URL specified");
							/** @noinspection PhpUndefinedFieldInspection */
							$set = $vid->id;
						}

						$videocount = $Database->whereEp($Episode)->where('provider', $k)->count('episodes__videos');
						if ($videocount === 0){
							if (!empty($set)) $Database->insert('episodes__videos',array(
								'season' => $Episode['season'],
								'episode' => $Episode['episode'],
								'provider' => $k,
								'id' => $set,
							));
						}
						else {
							$Database->whereEp($Episode)->where('provider', $k);
							if (empty($set))
								$Database->delete('episodes__videos');
							else $Database->update('episodes__videos', array('id' => $set));
						}
					}

					respond('Links updated',1,array(
						'epsection' => render_ep_video($Episode)
					));
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
			if (PERM('inspector')) $settings['js'][] = "$do-manage";
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

			fix_path("/eqg/$url");

			loadEpisodePage($data);
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
						respond(array('data' => json_decode(file_get_contents($CachePath), true)));

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
					file_put_contents($CachePath, json_encode($Data, JSON_UNESCAPED_SLASHES));

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
				if ($User === false){
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
					->get('sessions',null,'id,created,lastvisit,platform,browser_name,browser_ver,user_agent');
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

				$_match = array();
				if ($data === 'gettags'){
					$viaTypeahead = !empty($_GET['s']);
					$limit = null;
					$cols = "tid, name, type";
					if ($viaTypeahead){
						if (!preg_match('~'.TAG_NAME_PATTERN.'~u', $_GET['s']))
							typeahead_results('[]');

						$query = trim(strtolower($_GET['s']));
						ep_tag_name_check($query);
						$CGDb->where('name',"%$query%",'LIKE');
						$limit = 5;
						$cols = "tid, name, CONCAT('typ-', type) as type";
					}
					else $CGDb->orderBy('type','ASC');

					if (isset($_POST['not']) && is_numeric($_POST['not']))
						$CGDb->where('tid',intval($_POST['not'], 10),'!=');

					$Tags = $CGDb->orderBy('name','ASC')->get('tags',$limit,$cols);

					typeahead_results(empty($Tags) ? '[]' : $Tags);
				}

				if (preg_match('~^(rename|delete|make|[gs]et(?:sprite|cgs)?|tag|untag|clearrendercache|applytemplate)(?:/(\d+))?$~', $data, $_match)){
					$action = $_match[1];

					if ($action !== 'make'){
						if (empty($_match[2]))
							respond('Missing appearance ID');
						$AppearanceID = intval($_match[2], 10);
						$Appearance = $CGDb->where('id', $AppearanceID)->where('ishuman', $EQG)->getOne('appearances');
						if (empty($Appearance))
							respond("The specified appearance does not exist");
					}

					switch ($action){
						case "set":
						case "make":
						case "get":
							if ($action === 'get') respond(array(
								'label' => $Appearance['label'],
								'notes' => $Appearance['notes'],
								'cm_favme' => !empty($Appearance['cm_favme']) ? "http://fav.me/{$Appearance['cm_favme']}" : null,
							));

							$data = array(
								'notes' => '',
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
								if (strlen($notes) > 255)
									respond('Appearance notes cannot be longer than 255 characters');
								$data['notes'] = $notes;
							}

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
								catch (Exception $e){ respond($e->getMessage()); }
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
								$data['page'] = max(ceil($count / $ItemsPerPage), 1);

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
							else clear_rendered_image($Appearance['id']);

							$data['notes'] = get_notes_html($data, NOWRAP);
							respond($data);
						break;
						case "delete":
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
							foreach ($groups as $i => $GroupID){
								if (!$CGDb->where('groupid', $GroupID)->has('colorgroups'))
									respond("There's no group with the ID of  $GroupID");

								$CGDb->where('groupid', $GroupID)->update('colorgroups',array('order' => $i));
							}

							clear_rendered_image($Appearance['id']);

							respond(array('cgs' => get_colors_html($Appearance['id'], NOWRAP)));
						break;
						case "getsprite":
						case "setsprite":
							$fname = $Appearance['id'].'.png';
							$finalpath = $SpritePath.$fname;
							if ($action === 'setsprite'){
								process_uploaded_image('sprite', $finalpath, array('image/png'), 100);

								clear_rendered_image($Appearance['id']);
							}
							respond(array("path" => "$SpriteRelPath$fname?".filemtime($finalpath)));
						break;
						case "clearrendercache":
							if (!clear_rendered_image($Appearance['id']))
								respond('Cache could not be cleared');

							respond('Cached image removed, the image will be re-generated on the next request', 1);
						break;
						case "tag":
							if (empty($_POST['tag_name']))
								respond('Tag name is not specified');
							$tag_name = trim($_POST['tag_name']);
							if (!preg_match('~'.TAG_NAME_PATTERN.'~u',$tag_name))
								respond('Invalid tag name');

							ep_tag_name_check($tag_name);

							$Tag = $CGDb->where('name', $tag_name)->getOne('tags');
							if (empty($Tag))
								respond("The tag $tag_name does not exist.<br>Would you like to create it?",0,array('cancreate' => $tag_name));

							if ($CGDb->where('ponyid', $Appearance['id'])->where('tid', $Tag['tid'])->has('tagged'))
								respond('This appearance already has this tag');

							if (!$CGDb->insert('tagged',array(
								'ponyid' => $Appearance['id'],
								'tid' => $Tag['tid'],
							))) respond(ERR_DB_FAIL);
							update_tag_count($Tag['tid']);
							respond(array('tags' => get_tags_html($Appearance['id'], NOWRAP)));
						break;
						case "untag":
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
							respond(array('tags' => get_tags_html($Appearance['id'], NOWRAP)));
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
							if (!$CGDb->where('tid', $Tag['tid'])->delete('tags'))
								respond(ERR_DB_FAIL);
							respond('Tag deleted successfully', 1);
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
							if (in_array($ponyid, $TargetTagged)) continue;

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
					if ($nl < 4 || $nl > 30)
						respond("Tag name must be between 4 and 30 characters");
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
							$Appearance = $CGDb->where('id', $AppearanceID)->getOne('appearances');
							if (empty($Appearance))
								respond("Tag created, but target appearance (#$AppearanceID) does not exist. Please try adding the tag manually.");

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
						if (empty($_POST['ponyid']))
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
					$recvColors = json_decode($_POST['Colors'], true);
					if (empty($recvColors))
						respond("Missing list of {$color}s");
					$colorIDs = array();
					$colors = array();
					foreach ($recvColors as $i => $c){
						$append = array('order' => $i);
						$index = "(index: $i)";

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

					if ($new) $response = array('cgs' => get_colors_html($Appearance['id'], NOWRAP));
					else $response = array('cg' => get_cg_html($Group['groupid'], NOWRAP));

					$AppearanceID = $new ? $Appearance['id'] : $Group['ponyid'];
					if (isset($major)){
						LogAction('color_modify',array(
							'ponyid' => $AppearanceID,
							'reason' => $reason,
						));
						$response['update'] = get_update_html($AppearanceID);
					}
					clear_rendered_image($AppearanceID);

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

				$Tags = get_tags(null,array($ItemsPerPage*($Page-1), $ItemsPerPage));

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
				$heading = "$Color Changes";
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

			$_match = array();
			if (preg_match('~^appearance/(\d+)(\.png)?~',$data,$_match)){
				$asPNG = !empty($_match[2]);
				if ($asPNG){
					$Appearance = $CGDb->where('id', intval($_match[1]))->getOne('appearances');

					if (empty($Appearance))
						do404();
					$SpriteRelPath = "img/cg/{$Appearance['id']}.png";

					$OutputPath = APPATH."img/cg_render/{$Appearance['id']}.png";
					$FileRelPath = "$CGPath/appearance/{$Appearance['id']}.png";
					if (file_exists($OutputPath))
						outputpng($OutputPath);

					$OutWidth = 0;
					$OutHeight = 0;
					$SpriteWidth = $SpriteHeight = 0;
					$SpriteRightMargin = 10;
					$ColorSquareSize = 25;
					$FontFile = APPATH.'font/Celestia Medium Redux.ttf';
					$Name = $Appearance['label'];
					$NameVerticalMargin = 5;
					$NameFontSize = 22;
					$TextMargin = 10;

					// Detect if sprite exists and adjust image size & define starting positions
					$SpritePath = APPATH.$SpriteRelPath;
					$SpriteExists = file_exists($SpritePath);
					if ($SpriteExists){
						$SpriteSize = getimagesize($SpritePath);
						$Sprite = imagecreatefrompng($SpritePath);
						$SpriteHeight = $SpriteSize[HEIGHT];
						$SpriteWidth = $SpriteSize[WIDTH];
						$SpriteRealWidth = $SpriteWidth + $SpriteRightMargin;

						$OutWidth += $SpriteRealWidth;
						if ($SpriteHeight > $OutHeight)
							$OutHeight = $SpriteHeight;
					}
					else $SpriteRealWidth = 0;
					$origin = array(
						'x' => $SpriteExists ? $SpriteRealWidth : $TextMargin,
						'y' => 0,
					);

					// Get color groups & calculate the space they take up
					$ColorGroups = get_cgs($Appearance['id']);
					$CGCount = count($ColorGroups);
					$CGFontSize = $NameFontSize/1.5;
					$CGVerticalMargin = $NameVerticalMargin*1.5;
					$GroupLabelBox = imagettfsanebbox($CGFontSize, $FontFile, 'AGIJKFagijkf');
					$CGsHeight = $CGCount*($GroupLabelBox['height'] + ($CGVerticalMargin*2) + $ColorSquareSize);

					// Get export time & size
					$ExportTS = "Image last updated: ".format_timestamp(time(), FORMAT_FULL);
					$ExportFontSize = $CGFontSize/1.5;
					$ExportBox = imagettfsanebbox($ExportFontSize, $FontFile, $ExportTS);

					// Check how long & tall appearance name is, and set image width
					$NameBox = imagettfsanebbox($NameFontSize, $FontFile, $Name);
					$OutWidth = $origin['x'] + max($NameBox['width'], $ExportBox['width']) + $TextMargin;

					// Set image height
					$OutHeight = $origin['y'] + (($NameVerticalMargin*3) + $NameBox['height'] + $ExportBox['height']) + $CGsHeight;

					// Create base image
					$BaseImage = imageCreateTransparent($OutWidth, $OutHeight);
					$BLACK = imagecolorallocate($BaseImage, 0, 0, 0);

					// If sprite exists, output it on base image
					if ($SpriteExists)
						imageCopyExact($BaseImage, $Sprite, 0, 0, $SpriteWidth, $SpriteHeight);

					// Output appearance name
					$origin['y'] += $NameVerticalMargin;
					imageWrite($BaseImage, $Name, $origin['x'], $NameFontSize, $BLACK);
					$origin['y'] += $NameVerticalMargin;

					// Output generation time
					imageWrite($BaseImage, $ExportTS, $origin['x'], $ExportFontSize, $BLACK);
					$origin['y'] += $NameVerticalMargin;

					if (!empty($ColorGroups))
						foreach ($ColorGroups as $cg){
							imageWrite($BaseImage, $cg['label'], $origin['x'], $CGFontSize , $BLACK);
							$origin['y'] += $CGVerticalMargin;

							$Colors = get_colors($cg['groupid']);
							if (!empty($Colors)){
								$i = 0;
								foreach ($Colors as $c){
									$add = $i === 0 ? 0 : $i*5;
									$x = $origin['x']+($i*$ColorSquareSize)+$add;
									if ($x+$ColorSquareSize > $OutWidth){
										$i = 0;
										$SizeIncrease = $ColorSquareSize + $CGVerticalMargin;
										$origin['y'] += $SizeIncrease;
										$x = $origin['x'];

										// Create new base image since height will increase, and copy contents of old one
										$NewBaseImage = imageCreateTransparent($OutWidth, $OutHeight + $SizeIncrease);
										imageCopyExact($NewBaseImage, $BaseImage, 0, 0, $OutWidth, $OutHeight);
										imagedestroy($BaseImage);
										$BaseImage = $NewBaseImage;
									}

									imageDrawRectangle($BaseImage, $x, $origin['y'], $ColorSquareSize, $c['hex'], $BLACK);
									$i++;
								}

								$origin['y'] += $ColorSquareSize + $CGVerticalMargin;
							}
						};

					if (!upload_folder_create($OutputPath))
						respond('Failed to create render directory');
					outputpng($BaseImage, $OutputPath);
				}

				$Appearance = $CGDb->where('id', intval($_match[1]))->where('ishuman', $EQG)->getOne('appearances');
				if (empty($Appearance))
					do404();

				fix_path("$CGPath/appearance/{$Appearance['id']}");
				$heading = $Appearance['label'];
				$title = "{$Color}s for $heading";

				$Changes = get_updates($Appearance['id']);

				loadPage(array(
					'title' => $title,
					'heading' => $heading,
					'view' => "$do-single",
					'css' => array($do, "$do-single"),
					'js' => array('jquery.qtip', 'jquery.ctxmenu', $do),
				));
			}

			$title = '';
			if (empty($_GET['q']) || !PERM('user')){
				$EntryCount = $CGDb->where('ishuman',$EQG)->count('appearances');
				list($Page,$MaxPages) = calc_page($EntryCount);
				$Ponies = get_appearances($EQG, array($ItemsPerPage*($Page-1), $ItemsPerPage));
			}
			else {
				$tags = array_map('trim',explode(',',strtolower($_GET['q'])));
				$Tags = array();
				foreach ($tags as $i => $tag){
					$num = $i+1;
					$_MSG = check_string_valid($tag,"Tag #$num's name",INVERSE_TAG_NAME_PATTERN, !isset($_GET['js']));
					if (is_string($_MSG)) break;

					$Tag = $CGDb->where('name', $tag)->getOne('tags', 'tid');
					if (empty($Tag)){
						$_MSG = "The tag $tag does not exist";
						if (isset($_GET['js']))
							respond($_MSG);
					}
					if (!in_array($Tag['tid'], $Tags))
						$Tags[] = $Tag['tid'];
				}
				if (empty($_MSG)){
					if (empty($Tags)){
						$_MSG = 'Your search matched no tags';
						if (isset($_GET['js']))
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
								--limit
							) tg
						) AND p.ishuman = $IsHuman";
					$EntryCount = $CGDb->rawQuerySingle(str_replace('@coloumn','COUNT(*) as count',$query))['count'];
					list($Page,$MaxPages) = calc_page($EntryCount);
					$Offset = $ItemsPerPage*($Page-1);

					$SearchQuery = str_replace('@coloumn','p.*',$query);
					$SearchQuery = str_replace('--limit',"LIMIT $ItemsPerPage OFFSET $Offset",$SearchQuery);
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
				$settings['js'] = array_merge($settings['js'],array(
					'jquery.uploadzone',
					'twitter-typeahead',
					'handlebars-v3.0.3',
					'Sortable',
					"$do-manage"
				));
			}
			loadPage($settings);
		break;
		case "browser":
			$browser = browser();

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
		case "feedback":
			if (!PERM('user'))
				do404();

			if (!empty($data) && preg_match('~'.UUIDV4_REGEX.'~', $data)){
				$ChainID = $data;
				$Chain = $Database->where('chain',$ChainID)->getOne('feedback');
				$ChainID = $Chain['chain'];
				$CantSeeChain = empty($Chain) || (!PERM('developer') && $Chain['user'] !== $currentUser['id']);
			}

			if (RQMTHD === 'POST'){
				$insert = array();
				$creating = empty($ChainID);

				// Create new chain
				if ($creating){
					if (empty($_POST['subject']))
						respond('Subject cannot be empty');
					$subject = $_POST['subject'];
					$sln = strlen($subject);
					if ($sln < 5 ||$sln > 120)
						respond("The subject must be between 5 and 120 characters (you entered $sln).");

					$ChainID = $Database->insert('feedback',array(
						'user' => $currentUser['id'],
						'subject' => $subject,
					),'chain');
					if (empty($ChainID))
						respond('Your feedback could not be submitted: '.ERR_DB_FAIL);
				}
				else {
					if (isset($_REQUEST['close']) || isset($_REQUEST['reopen'])){
						if (!PERM('developer'))
							respond();

						$closed = isset($_REQUEST['close']);

						if (!$Database->where('chain', $ChainID)->update('feedback',array('open' => !$closed ? 'true' : 'false')))
							respond(ERR_DB_FAIL);

						$Database->insert('feedback__messages',array(
							'chain' => $ChainID,
							'author' => $currentUser['id'],
							'body' => $closed ? "@@close" : "@@open",
						));

						respond(array('chain' => render_feedback_chain_html($ChainID)));
					}

					if ($CantSeeChain)
						respond('The specified chain does not exist, or you\'re not allowed to respond to it');

					if (!$Chain['open'])
						respond('This feedback is closed; no responses are allowed.');
				}

				// Respond to existing chain
				if (empty($_POST['message']))
					respond('Message cannot be empty');
				$message = $_POST['message'];
				$mln = strlen($message);
				if ($mln < 10 ||$mln > 500)
					respond("The message must be between 10 and 500 characters (you entered $mln).");
				if (preg_match(FEEDBACK_SPECIAL_MESSAGE_REGEX, $message))
					respond("<p>Your message begins with text that has special meaning for the system. Please remove the <code>@@</code> from the beginning of your message and try again.</p>");

				$MessageID = $Database->insert('feedback__messages',array(
					'chain' => $ChainID,
					'author' => $currentUser['id'],
					'body' => $message,
				), 'mid');
				if (empty($MessageID))
					respond('Your message could not be submitted: '.ERR_DB_FAIL);
				if (!$creating)
					respond(array('chain' => render_feedback_chain_html($ChainID)));

				$Link = "feedback/$ChainID".(!$creating?"#msg$MessageID":'');
				respond("Your feedback was submitted successfuly. You can track its status here: <a href='/$Link'>".ABSPATH."$Link</a>", 1);
			}

			if (!empty($ChainID)){
				if ($CantSeeChain)
					do404();

				$Author = get_user($Chain['user']);
				$subject = str_trim($Chain['subject'], FEEDBACK_SUBJECT_LENGTH);

				fix_path("/feedback/$ChainID");

				loadPage(array(
					'title' => "$subject - Feedback",
					'view' => "$do-single",
				    'css' => "$do-single",
					'js' => "$do-single",
				));
			}

			$ItemsPerPage = 20;
			if (!PERM('developer'))
				$Database->where('user', $currentUser['id']);
			$EntryCount = $Database->count('feedback');
			list($Page,$MaxPages) = calc_page($EntryCount);
			fix_path("/feedback/$Page");

			$Pagination = get_pagination_html('feedback');

			if (!PERM('developer'))
				$Database->where('user', $currentUser['id']);
			$Feedback = $Database
				->orderByLiteral('CASE WHEN open = true THEN 1 ELSE 0 END',NEWEST_FIRST)
				->orderBy('created',NEWEST_FIRST)
				->get('feedback');

			if (isset($_GET['js']))
				pagination_response(render_feedback_list_html($Feedback, NOWRAP), '#feedback');

			loadPage(array(
				'title' => 'Feedback',
				'do-css',
				'js' => "paginate",
			));
		break;
		case "404":
		default:
			do404();
		break;
	}
