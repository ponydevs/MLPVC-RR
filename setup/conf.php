<?php

// Database Access Info \\
define('DB_HOST', 'localhost');
define('DB_USER', 'mlpvc-rr');
define('DB_PASS', 'example-password');
define('DB_NAME', 'mlpvc-rr');

// Log path - relative to the "logs" directory, e.g. "error.log" == "/var/www/MLPVC-RR/logs/error.log"
define('LOG_PATH', 'error.log');

// dA API Codes \\
define('DA_CLIENT', '');
define('DA_SECRET', '');

// Google Analytics Tracking Code \\
define('GA_TRACKING_CODE', '');

// Websocket \\
define('WS_SERVER_DOMAIN', '');
define('WS_SERVER_KEY', '');

// Discord API \\
define('DISCORD_BOT_TOKEN', '');
define('DISCORD_SERVER_ID', 0);
define('DISCORD_CLIENT', '');
define('DISCORD_SECRET', '');

// CSP \\
define('CSP_ENABLED', false);
define('CSP_HEADER', '');

// Development
//define('MAINTENANCE_START', strtotime(''));
define('SOCKET_SSL_CTX', ['verify_peer' => false]);
