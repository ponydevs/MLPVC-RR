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
				if (!$signedIn) die(header('Location: /'));
				detectCSRF();

				if (isset($_REQUEST['unlink']))
					da_request('https://www.deviantart.com/oauth2/revoke',array('token' => $currentUser['access_token']));

				if (!$Database->where('id',$currentUser['id'])->update('users', array(
					'access_token' => '',
					'refresh_token' => '',
					'token_expires' => NULL,
				))) respond('Could not remove information from database');

				Cookie::delete('access_token');
				respond((isset($_REQUEST['unlink'])?'Your account has been unlinked from our site':'You have been signed out successfully').'. Goodbye!',1);
			break;
			case "da-auth":
				if ($signedIn) header('Location: /');

				if (empty($_GET['code']) || (empty($_GET['state']) || ($_GET['state'] !== '/' && !preg_match(REWRITE_REGEX,$_GET['state']))))
					$_GET['error'] = 'unauthorized_client';
				if (isset($_GET['error']))
					loadPage($IndexSettings);

				da_get_token($_GET['code']);

				redirect($_GET['state']);
			break;
			case "post":
				if (RQMTHD !== 'POST') do404();
				if (!PERM('user')) respond();
				detectCSRF();

				if (!empty($_POST['what'])){
					if(!in_array($_POST['what'],array('request','reservation'))) respond('Invalid post type');
					$what = $_POST['what'];
					if ($what === 'reservation' && !PERM('reservations.create'))
						respond();
				}

				if (!empty($_POST['image_url'])){
					require 'includes/Image.php';
					$Image = new Image($_POST['image_url']);
					$ImageAvailable = $Image->preview !== false && $Image->fullsize !== false;

					if (empty($_POST['what'])){
						if (!$ImageAvailable) respond('The image could not be retrieved');
						respond(array('preview' => $Image->preview, 'title' => $Image->title));
					}
				}
				else if (empty($_POST['what'])) respond('Invalid request');

				if (!$ImageAvailable) respond('The image could not be retrieved');

				$insert = array(
					'preview' => $Image->preview,
					'fullsize' => $Image->fullsize,
				);

				switch ($what){
					case "request": $insert['requested_by'] = $currentUser['id']; break;
					case "reservation": $insert['reserved_by'] = $currentUser['id']; break;
				}

				if ($what !== 'reservation' && empty($_POST['label']))
					respond('Missing label');
				if (!empty($_POST['label'])){
					$insert['label'] = trim($_POST['label']);
					if (strlen($insert['label']) <= 2 || strlen($insert['label']) > 255) respond("The label must be between 2 and 255 characters in length");
				}

				if (empty($_POST['image_url'])) respond('Missing image URL');

				if (empty($_POST['season']) || empty($_POST['episode'])) respond('Missing episode identifiers');
				$insert['season'] = intval($_POST['season']);
				$insert['episode'] = intval($_POST['episode']);

				if ($what === 'request'){
					if (!isset($_POST['type']) || !in_array($_POST['type'],array('chr','obj','bg'))) respond("Invalid request type");
					$insert['type'] = $_POST['type'];
				}

				if ($Database->insert("{$what}s",$insert)) respond('Submission complete',1);
				else respond('Submission failed');
			break;
			case "reserving":
				if (RQMTHD !== 'POST') do404();
				$match = array();
				if (empty($data) || !preg_match('/^(requests?|reservations?)\/(\d+)$/',$data,$match)) respond('Invalid request');
				if (!PERM('reservations.create')) respond();
				$cancelling = isset($_REQUEST['cancel']);
				$type = rtrim($match[1],'s');

				$ID = intval($match[2]);
				$Thing = $Database->where('id', $ID)->getOne("{$type}s");
				if (empty($Thing)) respond("There's no request with that ID");

				$reservedBy = null;
				if (!empty($Thing['reserved_by'])){
					if ($Thing['reserved_by'] === $currentUser['id'] && !$cancelling) respond("You already reserved this $type");
					if ($Thing['reserved_by'] !== $currentUser['id']) respond("This $type has already been reserved by somepony else");
				}
				else if (!$cancelling) $reservedBy = $currentUser['id'];

				if (!$Database->where('id', $Thing['id'])->update("{$type}s",array('reserved_by' => $reservedBy)))
					respond('Nothing has been changed');

				if ($type === 'request')
					respond(array('btnhtml' => get_reserver_button(!$cancelling)));
				else if ($type === 'reservation' && $cancelling)
					respond(array('remove' => true));
				else respond('Invalid request');
			break;

			// PAGES
			case "index":
				if ($_SERVER['REQUEST_URI'] === '/index'){
					statusCodeHeader(301);
					redirect('/',false);
				}

				$CurrentEpisode = $Database->orderBy('season')->orderBy('episode')->getOne('episodes');
				if (empty($CurrentEpisode)) unset($CurrentEpisode);
				else list($Requests, $Reservations) = get_posts($CurrentEpisode['season'], $CurrentEpisode['episode']);

				loadPage($IndexSettings);
			break;
			case "episode":
				# TODO Locking posts
				if (RQMTHD === 'POST'){
					if (!PERM('episodes.manage')) respond();
					detectCSRF();

					if (empty($data)) do404();

					$EpData = episode_id_parse($data);
					if (!empty($EpData)){
						$Ep = get_real_episode($EpData['season'],$EpData['episode'],'season, episode, twoparter, title');
						respond(array(
							'ep' => $Ep,
							'epid' => format_episode_title($Ep, AS_ARRAY, 'id'),
						));
					}
					unset($EpData);

					$_match = array();
					if (preg_match('/^delete\/'.EPISODE_ID_PATTERN.'$/',$data,$_match)){
						list($season,$episode) = array_map('intval',array_splice($_match,1,2));

						$Episode = get_real_episode($season,$episode);
						if (empty($Episode))
							respond("There's no episode with this season & episode number");

						if (!$Database->where('season',$Episode['season'])->where('episode',$Episode['episode'])->delete('episodes')) respond(ERR_DB_FAIL);
						respond('Episode deleted successfuly',1,array('tbody' => get_eptable_tbody()));
					}
					else {
						$editing = preg_match('/^edit\/'.EPISODE_ID_PATTERN.'$/',$data,$_match);
						if ($editing){
							list($season,$episode) = array_map('intval',array_splice($_match,1,2));
							$insert = array();
						}
						else if ($data === 'add') $insert = array(
							'posted' => date('c'),
							'posted_by' => $currentUser['id'],
						);
						else statusCodeHeader(404, AND_DIE);

						if (!isset($_POST['season']) || !is_numeric($_POST['season']) )
							respond('Season number is missing or invalid');
						$insert['season'] = intval($_POST['season']);
						if ($insert['season'] < 1 || $insert['season'] > 8) respond('Season number must be between 1 and 8');

						if (!isset($_POST['episode']) || !is_numeric($_POST['episode']))
							respond('Episode number is missing or invalid');
						$insert['episode'] = intval($_POST['episode']);
						if ($insert['episode'] < 1 || $insert['episode'] > 26) respond('Season number must be between 1 and 26');

						if ($editing){
							$Current = get_real_episode($season,$episode);
							if (empty($Current)) respond("This episode doesn't exist");
						}
						$Target = get_real_episode($insert['season'],$insert['episode']);
						if (!empty($Target) && (!$editing || ($editing && ($Target['season'] !== $Current['season'] || $Target['episode'] !== $Current['episode']))))
							respond("There's already an episode with the same season & episode number");

						if (isset($_POST['twoparter']))
							$insert['twoparter'] = 1;

						if (empty($_POST['title']))
							respond('Episode title is missing or invalid');
						$insert['title'] = $_POST['title'];
						if (strlen($insert['title']) < 5 || strlen($insert['title']) > 35)
							respond('Episode title must be between 5 and 35 characters');
						if (!preg_match(EP_TITLE_REGEX, $insert['title']))
							respond('Episode title contains invalid charcaters');

						if ($editing){
							if (!$Database->where('season',$season)->where('episode',$episode)->update('episodes', $insert))
								respond('No changes were made', 1);
						}
						else if (!$Database->insert('episodes', $insert))
							respond(ERR_DB_FAIL);
						respond('Episode saved successfuly',1,array('tbody' => get_eptable_tbody()));
					}
				}

				$EpData = episode_id_parse($data);
				if (empty($EpData)) redirect('/episodes');
				$CurrentEpisode = get_real_episode($EpData['season'],$EpData['episode'],'season, episode, twoparter, title');
				if (empty($CurrentEpisode)) redirect('/episodes');

				list($Requests, $Reservations) = get_posts($CurrentEpisode['season'], $CurrentEpisode['episode']);

				loadPage(array_merge($IndexSettings,array('title',format_episode_title($CurrentEpisode))));
			break;
			case "episodes":
				$Episodes = get_episodes();
				$settings = array(
					'title' => 'Episodes',
					'do-css',
				);
				if (PERM('episodes.manage')) $settings['js'] = 'episodes-manage';
				loadPage($settings);
			break;
			case "about":
				loadPage(array(
					'title' => 'Home',
					'do-css',
				));
			break;
			case "404":
			default:
				do404();
			break;
		}
	}
	else statusCodeHeader(400);