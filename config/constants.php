<?php

use App\Regexes;
use App\RegExp;

// Configuration \\
define('HTTPS', !empty($_SERVER['HTTPS']));
define('ORIGIN', (HTTPS ? 'https' : 'http').'://'.($_SERVER['SERVER_NAME'] ?? 'localhost'));
const WS_LOCAL_ORIGIN = 'http://localhost';
const ABSPATH = ORIGIN.'/';
require __DIR__.'/init/path-constants.php';
define('POST_REQUEST', ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');
const GITHUB_PROJECT_NAME = 'MLP-VectorClub/Winterchilla';
const GITHUB_URL = 'https://github.com/'.GITHUB_PROJECT_NAME;
const SITE_TITLE = 'MLP Vector Club';
const SVGO_BINARY = PROJPATH.'node_modules/svgo/bin/svgo';
const DISCORD_INVITE_LINK = 'https://discord.gg/hrffb8k';
define('CSP_NONCE', base64_encode(random_bytes(16)));
const API_SCHEMA_PATH = 'dist/api.json';
const PRIVATE_API_PATH = '/api/private';

require __DIR__.'/init/env.php';

// Some constants \\
# integer
/** @see Posts::Get */
const ONLY_REQUESTS = 1;
/** @see Posts::Get */
const ONLY_RESERVATIONS = 2;
const POSTGRES_INTEGER_MIN = -2_147_483_648;
const POSTGRES_INTEGER_MAX = 2_147_483_647;
# string
define('FULL_LOG_PATH', PROJPATH.'logs/'.$_ENV['LOG_PATH']);
const OAUTH_REDIRECT_URI = ABSPATH.'da-auth';
const GDPR_IP_PLACEHOLDER = '127.168.80.82';
const DA_AUTHORIZED_APPS_URL = 'https://www.deviantart.com/settings/apps';
const SETTINGS_PAGE = '/settings';
# boolean
/** @see \App\HTTP::statusCode() */
const AND_DIE = true;
/** @see \App\Models\Show::formatTitle() */
const AS_ARRAY = true;
/** @see \App\CoreUtils::makePlural() */
const PREPEND_NUMBER = true;
/**
 * @see \App\Tags::getActual()
 * @see \App\Users::checkReservationLimitReached()
 */
const RETURN_AS_BOOL = true;
/** @see \App\Models\User::toAnchor */
const WITH_AVATAR = true;
const NOWRAP = false;
const WRAP = !NOWRAP;

// Color Guide constants \\
/** @see Appearances::GetSpriteURL */
const DEFAULT_SPRITE = '/img/blank-pixel.png';
# CM direction
const CM_FACING_RIGHT = 'right';
const CM_FACING_LEFT = 'left';
# Color Groups
const NO_COLON = false;
# Notes
const NOTE_TEXT_ONLY = false;
# Updates
const MOST_RECENT = 1;
# Appearance sorting
const SIMPLE_ARRAY = true;
# Appearances
const SHOW_APPEARANCE_NAMES = true;
# getimagesize() return array keys
const WIDTH = 0;
const HEIGHT = 1;

// Site-wide regular expressions \\
# User
const USERNAME_CHARACTERS_PATTERN = '[A-Za-z\-\d]';
const USERNAME_PATTERN = '('.USERNAME_CHARACTERS_PATTERN.'{1,20})';
Regexes::$username = new RegExp('^'.USERNAME_PATTERN.'$');
const GUEST_AVATAR = '/img/guest.svg';
# Episode
const EPISODE_ID_PATTERN = '[sS]0*([0-9])[eE]0*(1\d|2[0-6]|[1-9])(?:-0*(1\d|2[0-6]|[1-9]))?';
Regexes::$episode_id = new RegExp('^'.EPISODE_ID_PATTERN);
const MOVIE_ID_PATTERN = '(?:[mM]ovie)#?0*(\d+)';
Regexes::$movie_id = new RegExp('^'.MOVIE_ID_PATTERN, 'i');
Regexes::$ep_title = new RegExp('^([A-Za-z\s]+: )?[ -~]{5,100}$', 'u');
const INVERSE_EP_TITLE_PATTERN = '[^ -~]';
Regexes::$ep_title_prefix = new RegExp('^\s*(^|.*?[^\\\\]):\s*');
# Colors
Regexes::$hex_color = new RegExp('^#?([\dA-Fa-f]{6})$', 'u');
# DeviantArt
Regexes::$fullsize_match = new RegExp('^https?:\/\/orig\d+\.');
# General
const PRINTABLE_ASCII_PATTERN = '^[ -~\n]+$';
const INVERSE_PRINTABLE_ASCII_PATTERN = '[^ -~\n\t]';
const NEWEST_FIRST = 'desc';
const OLDEST_FIRST = 'asc';
Regexes::$rewrite = new RegExp('^/([^/].*)?$');

// Color Guide regular expression \\
# Tags
const TAG_NAME_PATTERN = '^[a-z\d ().\-\']{2,64}$';
Regexes::$tag_name = new RegExp(TAG_NAME_PATTERN, 'u');
const INVERSE_TAG_NAME_PATTERN = '[^a-z\d ().\-\']';
