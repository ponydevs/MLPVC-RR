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

			// PAGES
			case "index":
				if ($_SERVER['REQUEST_URI'] === '/index'){
					statusCodeHeader(301);
					redirect('/',false);
				}

				$CurrentEpisode = $Database->rawQuery(
					"SELECT *
					FROM episodes e
					ORDER BY e.posted
					LIMIT 1");
				if (empty($CurrentEpisode)) unset($CurrentEpisode);
				else $CurrentEpisode = $CurrentEpisode[0];

				//$Reservations

				$Requests = $Database->rawQuery(
					"SELECT *
					FROM requests
					WHERE season = ? &&  episode = ?
					ORDER BY finished, posted",array($CurrentEpisode['season'], $CurrentEpisode['episode']));

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
				));
			break;

			case "404":
			default:
				do404();
			break;
		}
	}
	else statusCodeHeader(400);