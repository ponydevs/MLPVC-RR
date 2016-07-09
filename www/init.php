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
	define('FULL', 0);      // \
	define('TEXT_ONLY', 1); //  }-> User::GetDALink
	define('LINK_ONLY', 2); // /
	define('ALWAYS_PLURAL', 0);
	define('UNREAD_ONLY', 0); // Notifications::Get
	define('READ_ONLY', 1); // Notifications::Get
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
	define('NO_DYNTIME', 'no'); // Time::Tag
	define('STATIC_DYNTIME', 'static'); // Time::Tag
	define('ONLY_REQUESTS', 1); // Posts::Get
	define('ONLY_RESERVATIONS', 2); // Posts::Get
	define('CURRENT',true); // render_session_li()
	define('RETURN_ARRANGED', true); // Posts::GetRequestsSection & Posts::GetReservationsSection
	define('IS_REQUEST', true); // Posts::GetRequestsSection
	define('WITH_GIT_INFO', true); // CoreUtils::GetFooter

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
	$EP_TITLE_REGEX = new RegExp('^[A-Za-z \'"\-!\d,&:?]{5,35}$','u');
	define('INVERSE_EP_TITLE_PATTERN','[^A-Za-z \'"\-!\d,&:?]');
	# Colors
	$HEX_COLOR_REGEX = new RegExp('^#?([\dA-Fa-f]{6})$','u');
	# DeviantArt
	$FULLSIZE_MATCH_REGEX = new RegExp('^https?:\/\/orig\d+\.');
	# General
	define('PRINTABLE_ASCII_PATTERN','^[ -~\n]+$');
	define('INVERSE_PRINTABLE_ASCII_PATTERN','[^ -~\n]');
	define('NEWEST_FIRST', 'DESC');
	define('OLDEST_FIRST', 'ASC');
	$REWRITE_REGEX = new RegExp('^/([\w\-]+)(?:/([\w\.\-]+(?:/[\w\.\-]+)*)?)?/?[^\w\.\-]*(?:\.php)?$');

	// Color Guide regular expression \\
	$EQG_URL_PATTERN = new RegExp('^eqg/');
	# Tags
	define('TAG_NAME_PATTERN', '^[a-z\d ().\-\']{3,30}$');
	$TAG_NAME_REGEX = new RegExp(TAG_NAME_PATTERN,'u');
	define('INVERSE_TAG_NAME_PATTERN', '[^a-z\d ().\-\']');

	// Database connection & Required Functionality Checking \\
	try {
		$inipath = 'in/to '.php_ini_loaded_file().' then restart '.About::GetServerSoftware();
		if (About::IniGet('short_open_tag') !== true)
			throw new Exception("Short open tags (&lt;?) are disabled\nUncomment/add the line <strong>short_open_tag=On</strong> $inipath to fix");
		if (!function_exists('curl_init'))
			throw new Exception("cURL extension is disabled or not installed\n".(PHP_OS !== 'WINNT' ? "Run <strong>sudo apt-get install php7.0-curl</strong>" : "Uncomment/add the line <strong>extension=php_curl.dll</strong> $inipath").' to fix');
		if (!class_exists('DOMDocument'))
			throw new Exception("XML extension is disabled or not installed\n".(PHP_OS !== 'WINNT' ? "Run <strong>sudo apt-get install php7.0-xml</strong> to fix" : ''));
		if (!function_exists('pdo_drivers'))
			throw new Exception("PDO extension is disabled or not installed\nThe site requires PHP 7.0+ to function, please upgrade your server.");
		if (!in_array('pgsql', pdo_drivers()))
			throw new Exception("PostgreSQL PDO extension is disabled or not installed\n".(PHP_OS !== 'WINNT' ? "Run <strong>sudo apt-get install php7.0-pgsql</strong>" : "Uncomment/add the line <strong>extension=php_pdo_pgsql.dll</strong> $inipath").' to fix');
	}
	catch (Exception $e){
		$errcause = 'libmiss';
		die(require APPATH."views/fatalerr.php");
	}
	$Database = new PostgresDbWrapper('mlpvc-rr');
	try {
		$Database->pdo();
	}
	catch (Exception $e){
		unset($Database);
		$errcause = 'db';
		die(require APPATH."views/fatalerr.php");
	}
	$CGDb = new PostgresDbWrapper('mlpvc-colorguide');

	header('Access-Control-Allow-Origin: '.(HTTPS?'http':'https').'://'.$_SERVER['SERVER_NAME']);

	if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])){
		CoreUtils::CanIHas('CloudFlare');
		if (CloudFlare::CheckUserIP())
			$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
	}
