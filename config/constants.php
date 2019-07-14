<?php

use App\CoreUtils;
use App\RegExp;
use App\Regexes;

// Configuration \\
define('HTTPS', !empty($_SERVER['HTTPS']));
define('ORIGIN',(HTTPS?'https':'http').'://'.($_SERVER['SERVER_NAME']??'localhost'));
define('WS_LOCAL_ORIGIN', 'http://localhost');
define('ABSPATH',ORIGIN.'/');
require __DIR__.'/init/path-constants.php';
define('POST_REQUEST', ($_SERVER['REQUEST_METHOD']??'GET') === 'POST');
define('GITHUB_PROJECT_NAME','ponydevs/MLPVC-RR');
define('GITHUB_URL','https://github.com/'.GITHUB_PROJECT_NAME);
define('SITE_TITLE', 'MLP Vector Club');
define('SVGO_BINARY',PROJPATH.'node_modules/svgo/bin/svgo');
define('DISCORD_INVITE_LINK', 'https://discord.gg/hrffb8k');
define('CSP_NONCE', base64_encode(random_bytes(16)));

require __DIR__.'/init/env.php';

// Some constants \\
# integer
define('ONLY_REQUESTS', 1); // Posts::Get
define('ONLY_RESERVATIONS', 2); // Posts::Get
define('POSTGRES_INTEGER_MIN', -2147483648);
define('POSTGRES_INTEGER_MAX', 2147483647);
# string
define('FULL_LOG_PATH', PROJPATH.'logs/'.CoreUtils::env('LOG_PATH'));
define('OAUTH_REDIRECT_URI', ABSPATH.'da-auth');
define('GDPR_IP_PLACEHOLDER', '127.168.80.82');
# boolean
define('AND_DIE', true); // CoreUtils::StatusCode
define('AS_ARRAY',true); // Episode::FormatTitle
define('RETURN_AS_BOOL', true); // CSRFProtection::Protect & User::ReservationLimitCheck
define('STAY_ALIVE', false); // HTTP::Redirect
define('HTML_ONLY', true); // CoreUtils::_processHeaderLink
define('PREPEND_NUMBER', true); // CoreUtils::MakePlural
define('NOWRAP', false);
define('WRAP', !NOWRAP);
define('IS_REQUEST', true); // Posts::GetRequestsSection
define('WITH_GIT_INFO', true); // CoreUtils::GetFooter
define('RETURN_MAP', true); // CGUtils::RenderSpritePNG
define('LAZYLOAD', true); // Posts::getLi

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
Regexes::$username = new RegExp('^'.USERNAME_PATTERN.'$');
define('GUEST_AVATAR','/img/guest.svg');
# Episode
define('EPISODE_ID_PATTERN','[sS]0*([0-9])[eE]0*(1\d|2[0-6]|[1-9])(?:-0*(1\d|2[0-6]|[1-9]))?');
Regexes::$episode_id = new RegExp('^'.EPISODE_ID_PATTERN);
define('MOVIE_ID_PATTERN','(?:[mM]ovie)#?0*(\d+)');
Regexes::$movie_id = new RegExp('^'.MOVIE_ID_PATTERN,'i');
Regexes::$ep_title = new RegExp('^([A-Za-z\s]+: )?[ -~]{5,35}$','u');
define('INVERSE_EP_TITLE_PATTERN','[^ -~]');
Regexes::$ep_title_prefix = new RegExp('^\s*(^|.*?[^\\\\]):\s*');
# Colors
Regexes::$hex_color = new RegExp('^#?([\dA-Fa-f]{6})$','u');
# DeviantArt
Regexes::$fullsize_match = new RegExp('^https?:\/\/orig\d+\.');
# General
define('PRINTABLE_ASCII_PATTERN','^[ -~\n]+$');
define('INVERSE_PRINTABLE_ASCII_PATTERN','[^ -~\n\t]');
define('NEWEST_FIRST', 'desc');
define('OLDEST_FIRST', 'asc');
Regexes::$rewrite = new RegExp('^/([^/].*)?$');

// Color Guide regular expression \\
# Tags
define('TAG_NAME_PATTERN', '^[a-z\d ().\-\']{2,64}$');
Regexes::$tag_name = new RegExp(TAG_NAME_PATTERN,'u');
define('INVERSE_TAG_NAME_PATTERN', '[^a-z\d ().\-\']');
