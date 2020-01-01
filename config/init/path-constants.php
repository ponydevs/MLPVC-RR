<?php
/** @noinspection RealpathInSteamContextInspection */
define('PROJPATH', dirname(__FILE__, 3).DIRECTORY_SEPARATOR);
define('APPATH', PROJPATH.'public/');
define('FSPATH', PROJPATH.'fs/');
define('INCPATH', PROJPATH.'includes/');
define('CONFPATH', PROJPATH.'config/');
define('PUBLIC_SPRITE_PATH', FSPATH.'sprites/');
define('PRIVATE_SPRITE_PATH', FSPATH.'sprites_pcg/');

// Set new file & folder permissions \\
define('FILE_PERM', 0660);
define('FOLDER_PERM', 0770);
umask(0007);
