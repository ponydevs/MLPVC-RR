<?php
	
	require "init.php";

	# Chck activity
	if (isset($_POST['do'])) $do = $_POST['do'];
	else if (!isset($_POST['do']) && isset($_GET['do'])) $do = $_GET['do'];
	
	# Get additional details
	if (isset($_POST['data'])) $data = $_POST['data'];
	else if (!isset($_POST['data']) && isset($_GET['data'])) $data = $_GET['data'];
	else $data = '';

	// Stored here for quick reference
	$IndexSettings = array(
		'title' => 'Home',
		'view' => 'index',
		'css' => array('index','jquery.fluidbox'),
		'js' => array('jquery.fluidbox.min','index'),
	);

	if (isset($do)){
		switch ($do){
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

				if (!isset($_GET['error']) && (empty($_GET['code']) || (empty($_GET['state']) || ($_GET['state'] !== '/' && !preg_match(REWRITE_REGEX,$_GET['state'])))))
					$_GET['error'] = 'unauthorized_client';
				if (isset($_GET['error'])){
					$err = $_GET['error'];
					if (isset($_GET['error_description']))
						$errdesc = $_GET['error_description'];
					loadPage($IndexSettings);
				}

				da_get_token($_GET['code']);

				redirect($_GET['state']);
			break;
			case "post":
				if (RQMTHD !== 'POST') do404();
				if (!PERM('user')) respond();
				detectCSRF();

				if (!empty($_POST['what'])){
					if(!in_array($_POST['what'],$POST_TYPES)) respond('Invalid post type');
					$what = $_POST['what'];
					if ($what === 'reservation'){
						if (!PERM('member'))
							respond();
						res_limit_check();
					}
				}

				if (!empty($_POST['image_url'])){
					require 'includes/Image.php';
					try {
						$Image = new Image($_POST['image_url']);
					}
					catch (Exception $e){ respond($e->getMessage()); }

					foreach ($POST_TYPES as $type){
						if ($Database->where('preview', $Image->preview)->has("{$type}s"))
							respond("This exact image has already been used for a $type");
					}

					if (empty($what)) respond(array('preview' => $Image->preview, 'title' => $Image->title));
				}
				else if (empty($what)) respond("Please provide an image URL ");

				$insert = array(
					'preview' => $Image->preview,
					'fullsize' => $Image->fullsize,
				);

				if (empty($_POST['season']) || empty($_POST['episode'])) respond('Missing episode identifiers');
				$epdata = get_real_episode(intval($_POST['season'], 10), intval($_POST['episode'], 10));
				if (empty($epdata)) respond('This episode does not exist');
				$insert['season'] = $epdata['season'];
				$insert['episode'] = $epdata['episode'];

				switch ($what){
					case "request": $insert['requested_by'] = $currentUser['id']; break;
					case "reservation": $insert['reserved_by'] = $currentUser['id']; break;
				}

				if ($what !== 'reservation' && empty($_POST['label']))
					respond('Missing label');
				if (!empty($_POST['label'])){
					$insert['label'] = trim($_POST['label']);
					$labellen = strlen($insert['label']);
					if ($labellen < 3 || $labellen > 255) respond("The label must be between 3 and 255 characters in length");
				}

				if ($what === 'request'){
					if (!isset($_POST['type']) || !in_array($_POST['type'],array('chr','obj','bg'))) respond("Invalid request type");
					$insert['type'] = $_POST['type'];
				}

				if ($Database->insert("{$what}s",$insert)) respond('Submission complete',1);
				else respond(ERR_DB_FAIL);
			break;
			case "reserving":
				if (RQMTHD !== 'POST') do404();
				$match = array();
				if (empty($data) || !preg_match('/^(requests?|reservations?)(?:\/(\d+))?$/',$data,$match)) respond('Invalid request #1');

				$noaction = true;
				$canceling = $finishing = $unfinishing = $adding = $deleteing = false;
				foreach (array('cancel','finish','unfinish','add','delete') as $k){
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

					$update = array('reserved_by' => null);
					if (!PERM('member')){
						if ($type === 'request' && $deleteing){
							if (!PERM('inspector') && !$signedIn && $Thing['requested_by'] !== $currentUser['id'])
								respond();

							if (!PERM('inspector') && !empty($Thing['reserved_by']))
								respond('You cannot delete a request that has been reserved');

							if (!$Database->where('id', $Thing['id'])->delete('requests'))
								respond(ERR_DB_FAIL);

							respond(array());
						}
						else respond();
					}
					else if (!empty($Thing['reserved_by'])){
						$usersMatch = $Thing['reserved_by'] === $currentUser['id'];
						if ($noaction){
							if ($usersMatch)
								respond("You already reserved this $type");
							else respond("This $type has already been reserved by somepony else");
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
							if (!$canceling) $update = array('deviation_id' => null);
						}
						else if ($finishing){
							if (!$usersMatch && !PERM('inspector'))
								respond();
							$update = check_request_finish_image();
						}
					}
					else if ($finishing) respond("This $type has not yet been reserved");
					else if (!$canceling){
						res_limit_check();
						$update['reserved_by'] = $currentUser['id'];
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
								$out['message'] = "<p class=align-center>You may want to mention <strong>{$u['name']}</strong> in the deviation description to let them know that their request has been fulfilled.</p>";
						}
						respond($out);
					}
					if ($type === 'request')
						respond(array('btnhtml' => get_reserver_button(get_user($Thing['reserved_by']), $Thing, true)));
					else if ($type === 'reservation' && $canceling)
						respond(array('remove' => true));
					else respond('Invalid request');
				}
				else if ($type === 'reservation'){
					if (!PERM('inspector')) respond();
					$insert = check_request_finish_image();
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
				if ($_SERVER['REQUEST_URI'] === '/index'){
					statusCodeHeader(301);
					redirect('/',false);
				}

				loadPage($IndexSettings);
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

						if (!$Database->whereEp($Episode)->delete('episodes')) respond(ERR_DB_FAIL);
						LogAction('episodes',array(
							'action' => 'del',
							'season' => $Episode['season'],
							'episode' => $Episode['episode'],
							'twoparter' => $Episode['twoparter'],
							'title' => $Episode['title'],
							'airs' => $Episode['airs'],
						));
						respond('Episode deleted successfuly',1,array(
							'tbody' => get_eptable_tbody(),
							'upcoming' => get_upcoming_eps(),
						));
					}
					else if (preg_match('/^((?:request|reservation)s)\/'.EPISODE_ID_PATTERN.'$/', $data, $_match)){
						$Episode = get_real_episode($_match[2],$_match[3]);
						if (empty($Episode))
							respond("There's no episode with this season & episode number");
						$only = $_match[1] === 'requests' ? ONLY_REQUESTS : ONLY_RESERVATIONS;
						respond(array(
							'render' => call_user_func("{$_match[1]}_render",get_posts($Episode['season'], $Episode['episode'], $only)),
						));
					}
					else if (preg_match('/^vote\/'.EPISODE_ID_PATTERN.'$/', $data, $_match)){
						$Episode = get_real_episode($_match[1],$_match[2]);
						if (empty($Episode))
							respond("There's no episode with this season & episode number");

						if (isset($_REQUEST['html']))
							respond(array('html' => get_episode_voting($Episode)));

						if (!PERM('user')) respond();

						if (!$Episode['aired'])
							respond('You cannot vote on this episode until after it had aired.');

						$UserVote = get_episode_user_vote($Episode);
						if (!empty($UserVote))
							respond('You already voted for this episode');

						if (empty($_POST['vote']) || !is_numeric($_POST['vote']))
							respond('Vote value missing from request');

						if (!$Database->insert('episodes__votes',array(
							'season' => $Episode['season'],
							'episode' => $Episode['episode'],
							'user' => $currentUser['id'],
							'vote' => intval($_POST['vote'], 10) > 0 ? 1 : -1
						))) respond(ERR_DB_FAIL);
						respond(array('newhtml' => get_episode_voting($Episode)));
					}
					else if (preg_match('/^export\/'.EPISODE_ID_PATTERN.'$/', $data, $_match)){
						if (!PERM('inspector')) respond();
						$Episode = get_real_episode($_match[1],$_match[2]);
						if (empty($Episode))
							respond("There's no episode with this season & episode number");

						list($req, $res) = get_posts($Episode['season'], $Episode['episode']);
						respond(array('export' => export_posts($req, $res)));
					}
					else if (preg_match('/^([sg])etvideos\/'.EPISODE_ID_PATTERN.'$/', $data, $_match)){
						$Episode = get_real_episode($_match[2],$_match[3]);
						if (empty($Episode))
							respond("There's no episode with this season & episode number");

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

							$video = $Database->whereEp($Episode)->where('provider', $k)->getOne('episodes__videos','COUNT(*) as count');
							if ($video['count'] === 0){
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
						else if ($data === 'add') $insert = array(
							'posted' => date('c'),
							'posted_by' => $currentUser['id'],
						);
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
							if (empty($Current)) respond("This episode doesn't exist");
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
						if (empty($airs)) respond('Invalid air time');
						$insert['airs'] = date('c',strtotime('this minute', $airs));

						if ($editing){
							if (!$Database->whereEp($season,$episode)->update('episodes', $insert))
								respond('No changes were made', 1);
						}
						else if (!$Database->insert('episodes', $insert))
							respond(ERR_DB_FAIL);

						if ($editing){
							$logentry = array('target' => format_episode_title($Current,AS_ARRAY,'id'));
							$changes = 0;
							foreach (array('season', 'episode', 'twoparter', 'title', 'airs') as $k){
								if (isset($insert[$k]) && $insert[$k] !== $Current[$k]){
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
						respond('Episode saved successfuly',1,array(
							'tbody' => get_eptable_tbody(),
							'upcoming' => get_upcoming_eps(),
						));
					}
				}

				$EpData = episode_id_parse($data);
				if (empty($EpData)) redirect('/episodes');
				$CurrentEpisode = get_real_episode($EpData['season'],$EpData['episode']);
				if (empty($CurrentEpisode)) redirect('/episodes');

				list($Requests, $Reservations) = get_posts($CurrentEpisode['season'], $CurrentEpisode['episode']);

				loadPage(array_merge($IndexSettings,array('title' => format_episode_title($CurrentEpisode))));
			break;
			case "episodes":
				$Episodes = get_episodes();
				$settings = array(
					'title' => 'Episodes',
					'do-css',
					'js' => array($do),
				);
				if (PERM('inspector')) $settings['js'][] = "$do-manage";
				loadPage($settings);
			break;
			case "about":
				loadPage(array(
					'title' => 'About',
					'do-css',
				));
			break;
			case "logs":
				if (RQMTHD === "POST"){
					if (!PERM('inspector')) respond();
					$_match = array();
					if (isset($_POST['page']) && is_numeric($_POST['page']))
						$Page = intval($_POST['page'], 10);
					else if (preg_match('/^details\/(\d+)/', $data, $_match)){
						$EntryID = intval($_match[1], 10);

						$MainEntry = $Database->where('entryid', $EntryID)->getOne('log');
						if (empty($MainEntry)) respond('Log entry does not exist');
						if (empty($MainEntry['refid'])) respond('There are no details to show');

						$Details = $Database->where('entryid', $MainEntry['refid'])->getOne("`log__{$MainEntry['reftype']}`");
						if (empty($Details)) respond('Failed to retrieve details');

						respond(format_log_details($MainEntry['reftype'],$Details));
					}
				}
				else {
					if (!PERM('inspector')) $MSG = "You do not have permission to view the log entries";
					else if (is_numeric($data))
						$Page = intval($data, 10);
				}

				if (empty($MSG)){
					if (empty($Page) || $Page < 1)
						$Page = 1;

					$ItemsPerPage = 10;
					$EntryCount = $Database->getOne('log', 'COUNT(*) as rows')['rows'];
					$MaxPages = ceil($EntryCount/$ItemsPerPage);

					if ($Page > $MaxPages)
						$Page = $MaxPages;

					fix_path("/logs/$Page");

					$LogItems = $Database->orderBy('timestamp')->get('log',array($ItemsPerPage*($Page-1), $ItemsPerPage));
				}
				else statusCodeHeader(403);

				loadPage(array(
					'title' => (empty($MSG) ? "Page $Page - ":'').'Logs',
					'do-css',
					'do-js',
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
						$un = $_match[1];

						$targetUser = get_user($un, 'name');
						if (empty($targetUser)) respond('User not found');

						if ($targetUser['id'] === $currentUser['id']) respond("You cannot modify your own group");
						if (!PERM($targetUser['role']))
							respond('You can only modify the group of users who are in the same or a lower-level group than you');
						if ($targetUser['role'] === 'ban')
							respond('This user is banished, and must be un-banished before changing their group.');

						if (!isset($_POST['newrole'])) respond('The new group is not specified');
						$newgroup = trim($_POST['newrole']);
						if (!in_array($newgroup,$ROLES) || $newgroup === 'ban') respond('The specified group does not exist');
						if ($targetUser['role'] === $newgroup) respond('This user is already in the specified group');

						update_role($targetUser,$newgroup);

						respond('Group changed successfully',1,array(
							'ng' => $newgroup,
							'badge' => label_to_initials($ROLES_ASSOC[$newgroup]),
							'canbebanned' => !PERM('inspector', $newgroup)
						));
					}
					else if (preg_match('/^sessiondel\/(\d+)$/',$data,$_match)){
						if (!$signedIn) respond();
						detectCSRF();

						$Session = $Database->where('id', $_match[1])->getOne('sessions');
						if (empty($Session)) respond('This session does not exist');
						if ($Session['user'] !== $currentUser['id'] && !PERM('inspector'))
							respond('You are not allowed to delete this session');

						if (!$Database->where('id', $Session['id'])->delete('sessions'))
							respond('Session could not be deleted');
						respond('Session successfully removed',1);
					}
					else if (preg_match('/^(un-)?banish\/'.USERNAME_PATTERN.'$/', $data, $_match)){
						if (!$signedIn) respond();
						detectCSRF();

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
					if (!isset($MSG)){
						$MSG = 'Local user not found';
						if (!$signedIn){
							$exists = 'exsists on deviantArt';
							if (isset($un)) $exists = "<a href='http://$un.deviantart.com/'>$exists</a>";
							$SubMSG = "If this user $exists, sign in to import their details.";
						}
					}
					$canEdit = $sameUser = false;
				}
				else {
					$sameUser = $signedIn && $User['id'] === $currentUser['id'];
					$canEdit = !$sameUser && PERM('inspector') && PERM($User['role']);
					$pagePath = "/u/{$User['name']}";
					if ($_SERVER['REQUEST_URI'] !== $pagePath)
						redirect($pagePath, STAY_ALIVE);
				}
				if ($canEdit)
					$UsableRoles = $Database->where("value <= (SELECT value FROM roles WHERE name = '{$currentUser['role']}')")->where('value > 0')->get('roles',null,'name, label');

				if (isset($MSG)) statusCodeHeader(404);
				else {
					if ($sameUser){
						$CurrentSession = $currentUser['Session'];
						$Database->where('id != ?',array($CurrentSession['id']));
					}
					$Sessions = $Database->where('user',$User['id'])->orderBy('lastvisit','DESC')->get('sessions',null,'id,created,lastvisit,browser_name,browser_ver');
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
				if (!PERM('inspector')) do404();

				$CGDb = new MysqliDbWrapper(DB_HOST,DB_USER,DB_PASS,'mlpvc-colorguide');
				include "includes/CGUtils.php";

				if (RQMTHD === 'POST'){
					if (!PERM('inspector')) respond();
					detectCSRF();

					$_match = array();
					if (preg_match('~^(rename|delete)/(\d+)$~', $data, $_match)){
						$PonyID = intval($_match[2], 10);

						$Pony = $CGDb->where('id', $PonyID)->getOne('ponies');
						if (empty($Pony))
							respond("There's no pony with the ID of $PonyID");

						$update = array();
						switch ($_match[1]) {
							case "rename":
								$newname = isset($_POST['newname']) ? trim($_POST['newname']) : null;
								$nnl = !empty($newname) ? strlen($newname) : null;
								if (empty($newname) || $nnl < 4)
									respond("The new name cannot be shorter than 4 characters");
								if ($nnl > 255)
									respond("The new name cannot be longer than 255 characters");

								$update['label'] = $newname;
							break;
							case "delete":
								if ($CGDb->where('id', $Pony['id'])->delete('ponies'))
									respond(ERR_DB_FAIL);
								respond('Appearance deleted successfuly');
							break;
							default: respond('Bad request');
						}

						$CGDb->where('id', $Pony['id'])->update('ponies', $update);
					}
					else if (preg_match('~^([gs]et|make|del)tag(?:/(\d+))?$~', $data, $_match)){
						$setting = $_match[1] === 'set';
						$getting = $_match[1] === 'get';
						$deleting = $_match[1] === 'del';
						$new = $_match[1] === 'make';

						if (!$new){
							if (empty($_match[2]))
								respond('Missing tag ID');
							$TagID = intval($_match[2], 10);
							$Tag = $CGDb->where('tid', $TagID)->getOne('tags');
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

						$name = isset($_POST['name']) ? strtolower(trim($_POST['name'])) : null;
						$nl = !empty($name) ? strlen($name) : null;
						if (empty($name) || $nl < 4)
							respond("Tag name cannot be shorter than 4 characters");
						if ($nl > 30)
							respond("Tag name cannot be longer than 30 characters");
						$fails = array();
						if (preg_match('/'.INVERSE_TAG_NAME_PATTERN.'/', $name, $fails)){
							$invalid = array();
							foreach ($fails as $f)
								if (!in_array($f, $invalid))
									$invalid[] = $f;
							respond('Tag name contains the following invalid character'.(count($invalid)!==1?'s':'').': "'.implode('", "', $invalid).'".');
						}
						$data['name'] = $name;

						if (empty($_POST['type'])) $data['type'] = null;
						else {
							$type = trim($_POST['type']);
							if (!in_array($type, $TAG_TYPES))
								respond("Invalid tag type: $type");
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
							$TagID = $CGDb->insert('tags', $data);
							if (!$TagID) respond(ERR_DB_FAIL);
							$data['tid'] = $TagID;

							if (!empty($_POST['addto']) && is_numeric($_POST['addto'])){
								$PonyID = intval($_POST['addto'], 10);
								$Pony = $CGDb->where('id', $PonyID)->getOne('ponies');
								if (empty($Pony))
									respond("Tag created, but target appearance (#$PonyID) does not exist. Please try adding the tag manually.");

								if (!$CGDb->insert('tagged',array(
									'tid' => $data['tid'],
									'ponyid' => $Pony['id']
								))) respond(ERR_DB_FAIL);
								respond(array('tags' => get_tags_html($Pony['id'], NOWRAP)));
							}
						}
						else {
							$CGDb->where('tid', $Tag['tid'])->update('tags', $data);
							$data = array_merge($Tag, $data);
						}

						respond($data);
					}
					else do404();
				}

				fix_path("/{$color}guide");

				$Ponies = $CGDb->orderBy('label', 'ASC')->get('ponies');

				$settings = array(
					'title' => "$Color Guide",
					'do-css',
					'js' => array('jquery.qtip', 'jquery.ctxmenu', $do),
				);
				if (PERM('inspector')) $settings['js'][] = "$do-manage";
				loadPage($settings);
			break;
			case "404": do404();
			default:
				if (!empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'GitHub-Hookshot/') === 0){
					if (!empty(GH_WEBHOOK_DO) && $do === GH_WEBHOOK_DO){
						if (empty($_SERVER['HTTP_X_GITHUB_EVENT']) || empty($_SERVER['HTTP_X_HUB_SIGNATURE']))
							do404();

						list($algo, $hash) = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2) + array('', '');
						if (!in_array($algo, hash_algos(), TRUE))
							do404();
						$rawPost = file_get_contents('php://input');
						if ($hash !== hash_hmac($algo, $rawPost, GH_WEBHOOK_SECRET))
							do404();

						switch (strtolower($_SERVER['HTTP_X_GITHUB_EVENT'])) {
							case 'post': shell_exec("$git pull") && exit;
							case 'ping': die("pong");
							default: do404();
						}
					}
					else do404();
				}
				do404('default-fallthru');
			break;
		}
	}
	else statusCodeHeader(400);
