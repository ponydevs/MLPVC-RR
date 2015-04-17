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
				if (!$signedIn) respond();
				detectCSRF();

				if (!empty($_POST['image_url'])){
					require 'includes/Image.php';
					$Image = new Image($_POST['image_url']);
					$ImageAvailable = $Image->preview !== false && $Image->fullsize !== false;

					if (empty($_POST['what'])){
						respond(!$ImageAvailable?'The image could not be retrieved':'',$ImageAvailable,array('preview' => $Image->preview, 'title' => $Image->title));
					}
				}

				if (empty($_POST['what']) || !in_array($_POST['what'],array('request','reservation'))) respond('Invalid post type');
				$what = $_POST['what'];

				if (!$ImageAvailable) respond('The image could not be retrieved');

				$data = array(
					'preview' => $Image->preview,
					'fullsize' => $Image->fullsize,
				);

				switch ($what){
					case "request": $data['requested_by'] = $currentUser['id']; break;
					case "reservation": $data['reserved_by'] = $currentUser['id']; break;
				}

				if (empty($_POST['label'])) respond('Missing label');
				$data['label'] = trim($_POST['label']);
				if (strlen($data['label']) <= 2 || strlen($data['label']) > 255) respond("The label must be between 2 and 255 characters in length");
				if (empty($_POST['image_url'])) respond('Missing image URL');

				if (empty($_POST['season']) || empty($_POST['episode'])) respond('Missing episode identifiers');
				$data['season'] = intval($_POST['season']);
				$data['episode'] = intval($_POST['episode']);

				if ($what === 'request'){
					if (!isset($_POST['type']) || !in_array($_POST['type'],array('chr','obj','bg'))) respond("Invalid request type");
					$data['type'] = $_POST['type'];
				}

				if ($Database->insert("{$what}s",$data)) respond('Submission complete',1);
				else respond('Submission failed');
			break;

			// PAGES
			case "index":
				if ($_SERVER['REQUEST_URI'] === '/index'){
					statusCodeHeader(301);
					redirect('/',false);
				}

				$CurrentEpisode = rawquery_get_single_result($Database->rawQuery(
					"SELECT *
					FROM episodes e
					ORDER BY e.posted DESC
					LIMIT 1"));
				if (empty($CurrentEpisode)) unset($CurrentEpisode);
				else {
					$Reservations = $Database->rawQuery(
						"SELECT
							*,
							IF(!ISNULL(r.deviation_id), 1, 0) as finished
						FROM reservations r
						WHERE season = ? &&  episode = ?
						ORDER BY finished, posted",array($CurrentEpisode['season'], $CurrentEpisode['episode']));

					$Requests = $Database->rawQuery(
						"SELECT
							*,
							IF(!ISNULL(r.deviation_id), 1, 0) as finished
						FROM requests r
						WHERE season = ? &&  episode = ?
						ORDER BY finished, posted",array($CurrentEpisode['season'], $CurrentEpisode['episode']));
				}

				loadPage($IndexSettings);
			break;
			case "episodes":
				loadPage(array(
					'title' => 'Episodes',
					'do-css',
				));
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