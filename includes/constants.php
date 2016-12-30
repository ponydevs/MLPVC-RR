<?php

use App\RegExp;

// Configuration \\
define('HTTPS', !empty($_SERVER['HTTPS']));
define('ABSPATH',(HTTPS?'https':'http').'://'.$_SERVER['SERVER_NAME'].'/');
define('PROJPATH',realpath(dirname(__FILE__).'/../').DIRECTORY_SEPARATOR);
define('APPATH',  PROJPATH.'www'.DIRECTORY_SEPARATOR);
define('FSPATH',  PROJPATH.'fs'.DIRECTORY_SEPARATOR);
define('INCPATH', PROJPATH.'includes'.DIRECTORY_SEPARATOR);
define('POST_REQUEST', $_SERVER['REQUEST_METHOD'] === 'POST');
define('GITHUB_PROJECT_NAME','ponydevs/MLPVC-RR');
define('GITHUB_URL','https://github.com/'.GITHUB_PROJECT_NAME);
define('SITE_TITLE', 'MLP Vector Club');
define('EXEC_START_MICRO', microtime(true));

// Load private configuration \\
if (!file_exists(INCPATH.'conf.php'))
	die('conf.php is missing from '.INCPATH);
require INCPATH.'conf.php';

// Some constants \\
# integer - none
# string
define('OAUTH_REDIRECT_URI', '&redirect_uri='.urlencode(ABSPATH.'da-auth'));
define('OAUTH_AUTHORIZATION_URL', "https://www.deviantart.com/oauth2/authorize?response_type=code&scope=user+browse&client_id=".DA_CLIENT.OAUTH_REDIRECT_URI);
define('SPRITE_PATH', FSPATH.'sprites/');
# boolean
define('AND_DIE', true); // CoreUtils::StatusCode
define('AS_ARRAY',true); // Episode::FormatTitle
define('RETURN_AS_BOOL', true); // CSRFProtection::Protect & User::ReservationLimitCheck
define('STAY_ALIVE', false); // HTTP::Redirect
define('HTML_ONLY', true); // CoreUtils::_processHeaderLink
define('PREPEND_NUMBER', true); // CoreUtils::MakePlural
define('NOWRAP', false);
define('WRAP', !NOWRAP);
define('ONLY_REQUESTS', 1); // Posts::Get
define('ONLY_RESERVATIONS', 2); // Posts::Get
define('CURRENT',true); // User::RenderSessionLi
define('RETURN_ARRANGED', true); // Posts::GetRequestsSection & Posts::GetReservationsSection
define('IS_REQUEST', true); // Posts::GetRequestsSection
define('WITH_GIT_INFO', true); // CoreUtils::GetFooter
define('RETURN_MAP', true); // CGUtils::RenderSpritePNG

// Color Guide constants \\
define('DEFAULT_SPRITE', '/img/blank-pixel.png'); // \CG\Appearances::GetSpriteURL
# CM direction
define('CM_FACING_RIGHT', 'right');
define('CM_FACING_LEFT', 'left');
# Color Groups
define('NO_COLON', false);
define('OUTPUT_COLOR_NAMES', true);
define('FORCE_EXTRA_INFO', true);
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
define('EPISODE_ID_PATTERN','[sS]0*([0-8])[eE]0*([1-9]|1\d|2[0-6])(?:-0*([1-9]|1\d|2[0-6]))?(?:\b|$)');
$EPISODE_ID_REGEX = new RegExp('^'.EPISODE_ID_PATTERN);
define('MOVIE_ID_PATTERN','(?:[mM]ovie#)0*(\d+)(?:\b|$)');
$MOVIE_ID_REGEX = new RegExp('^'.MOVIE_ID_PATTERN,'i');
$EP_TITLE_REGEX = new RegExp('^([A-Za-z\s]+: )?[A-Za-z \'"\-!\d,&:?.()]{5,35}$','u');
define('INVERSE_EP_TITLE_PATTERN','[^A-Za-z \'"\-!\d,&:?.()]');
$PREFIX_REGEX = new RegExp('^\s*(^|.*?[^\\\\]):\s*');
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
$EQG_URL_PATTERN = new RegExp('^eqg/?');
# Tags
define('TAG_NAME_PATTERN', '^[a-z\d ().\-\'#]{3,30}$');
$TAG_NAME_REGEX = new RegExp(TAG_NAME_PATTERN,'u');
define('INVERSE_TAG_NAME_PATTERN', '[^a-z\d ().\-\'#]');
