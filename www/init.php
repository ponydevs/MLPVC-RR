<?php

	// Configuration \\
	require 'conf.php';
	define('HTTPS', !empty($_SERVER['HTTPS']));
	define('ABSPATH',(HTTPS?'https':'http').'://'.$_SERVER['SERVER_NAME'].'/');
	define('APPATH',dirname(__FILE__).DIRECTORY_SEPARATOR);
	define('POST_REQUEST', $_SERVER['REQUEST_METHOD'] === 'POST');
	define('GITHUB_PROJECT_NAME','ponydevs/MLPVC-RR');
	define('GITHUB_URL','https://github.com/'.GITHUB_PROJECT_NAME);
	define('SITE_TITLE', 'MLP Vector Club');
	define('EXEC_START_MICRO', microtime(true));

	// Autoload classes \\
	require 'includes/classes/CoreUtils.php';
	spl_autoload_register(function($class){
		CoreUtils::CanIHas($class);
	});

	// Some constants \\
	# integer
	define('EIGHTY_YEARS',2524556160);
	define('ONE_YEAR',31536000);
	define('THIRTY_DAYS',2592000);
	define('ONE_DAY',86400);
	define('ONE_HOUR',3600);
	define('FULL', 0);      // \
	define('TEXT_ONLY', 1); //  }-> User::GetDALink
	define('LINK_ONLY', 2); // /
	# string
	define('ERR_DB_FAIL','There was an error while saving to the database');
	define('FORMAT_FULL','jS M Y, g:i:s a T'); // Time::Format
	# boolean
	define('ALLOW_SEASON_ZERO', true); // Epsiode::Get
	define('AND_DIE', true); // CoreUtils::StatusCode
	define('AS_ARRAY',true); // Episode::FormatTitle
	define('RETURN_AS_BOOL', true); // CSRFProtection::Protect
	define('STAY_ALIVE', false); // CoreUtils::Redirect
	define('HTML_ONLY', true); // CoreUtils::_processHeaderLink
	define('PREPEND_NUMBER', true); // CoreUtils::MakePlural
	define('NOWRAP', false);
	define('WRAP', !NOWRAP);
	define('FORMAT_READABLE',true); // Time::Format
	define('EXTENDED', true); // Time::Tag
	define('NO_DYNTIME', false); // Time::Tag
	define('ONLY_REQUESTS', 1); // Posts::Get
	define('ONLY_RESERVATIONS', 2); // Posts::Get
	define('CURRENT',true); // render_session_li()
	define('RETURN_ARRANGED', true); // Posts::GetRequestsSection & Posts::GetReservationsSection
	define('IS_REQUEST', true); // Posts::GetRequestsSection

	// Color Guide constants \\
	define('DEFAULT_SPRITE', '/img/blank-pixel.png'); // \CG\Appearances::GetSpriteURL
	# CM direction
	define('CM_DIR_TAIL_TO_HEAD', false);
	define('CM_DIR_HEAD_TO_TAIL', true);
	define('CM_DIR_ONLY',true);
	# Color Groups
	define('NO_COLON', false);
	define('OUTPUT_COLOR_NAMES', true);
	# Notes
	define('NOTE_TEXT_ONLY', false);
	# Updates
	define('MOST_RECENT', 1);
	# Appearance sorting
	define('SIMPLE_ARRAY', true);
	# Appearances
	define('SHOW_APPEARANCE_NAMES', true);
	# getimagesize() return array keys
	define('WIDTH', 0);
	define('HEIGHT', 1);

	// Site-wide regular expressions \\
	# User
	define('USERNAME_PATTERN', '([A-Za-z\-\d]{1,20})');
	$USERNAME_REGEX = new RegExp('^'.USERNAME_PATTERN.'$');
	define('GUEST_AVATAR','/img/guest.svg');
	# Episode
	define('EPISODE_ID_PATTERN','S0*([0-8])E0*([1-9]|1\d|2[0-6])(?:-0*([1-9]|1\d|2[0-6]))?(?:\D|$)');;
	$EPISODE_ID_REGEX = new RegExp('^'.EPISODE_ID_PATTERN,'i');
	$EP_TITLE_REGEX = new RegExp('^[A-Za-z \'\-!\d,&:?]{5,35}$','u');
	# Colors
	$HEX_COLOR_PATTERN = new RegExp('^#?([\dA-Fa-f]{6})$','u');
	# DeviantArt
	$FULLSIZE_MATCH_REGEX = new RegExp('^https?:\/\/orig\d+\.');
	# General
	define('PRINTABLE_ASCII_REGEX','^[ -~]+$');
	define('INVERSE_PRINTABLE_ASCII_REGEX','[^ -~]');
	define('NEWEST_FIRST', 'DESC');
	define('OLDEST_FIRST', 'ASC');
	$REWRITE_REGEX = new RegExp('^/(?:([\w\.\-]+|-?\d+)(?:/((?:[\w\-]+|-?\d+)(?:/(?:[\w\-]+|-?\d+))?))?/?)?$','i');

	// Color Guide regular expression \\
	$EQG_URL_PATTERN = new RegExp('^eqg/');
	# Tags
	define('TAG_NAME_PATTERN', '^[a-z\d ().\-\']{3,30}$');
	$TAG_NAME_REGEX = new RegExp(TAG_NAME_PATTERN,'u');
	define('INVERSE_TAG_NAME_PATTERN', '[^a-z\d ().\-\']');

	// Git commit information \\
	define('LATEST_COMMIT_ID',rtrim(shell_exec('git rev-parse --short=4 HEAD')));
	define('LATEST_COMMIT_TIME',date('c',strtotime(shell_exec('git log -1 --date=short --pretty=format:%ci'))));

	// Database connection & Auth \\
	$Database = new PostgresDbWrapper('mlpvc-rr');
	try {
		$Database->pdo();
	}
	catch (Exception $e){
		unset($Database);
		die(require APPATH."views/dberr.php");
	}
	$CGDb = new PostgresDbWrapper('mlpvc-colorguide');
	User::CheckAuth();

	header('Access-Control-Allow-Origin: '.(HTTPS?'http':'https').'://'.$_SERVER['SERVER_NAME']);

	if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])){
		CoreUtils::CanIHas('CloudFlare');
		if (CloudFlare::CheckUserIP())
			$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
	}
