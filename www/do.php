<?php
	
	require "init.php";

	# Chck activity
	if (isset($_POST['do'])) $do = $_POST['do'];
	else if (!isset($_POST['do']) && isset($_GET['do'])) $do = $_GET['do'];
	
	# Get additional details
	if (isset($_POST['data'])) $data = $_POST['data'];
	else if (!isset($_POST['data']) && isset($_GET['data'])) $data = $_GET['data'];
	else $data = '';
	
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
					loadPage(array(
						'title' => 'Home',
						'view' => 'index',
						'css' => 'index',
					));

				da_get_token($_GET['code']);

				redirect($_GET['state']);
			break;

			// PAGES
			// PAGES
			// PAGES
			// PAGES
			case "index":
				if ($_SERVER['REQUEST_URI'] === '/index'){
					statusCodeHeader(301);
					redirect('/',false);
				}

				loadPage(array(
					'title' => 'Home',
					'view' => 'index',
					//'css' => 'index',
				));
			break;
			case "about":
				loadPage(array(
					'title' => 'Home',
					'view' => 'about',
				));
			break;

			case "404":
			default:
				do404();
			break;
		}
	}
	else statusCodeHeader(400);