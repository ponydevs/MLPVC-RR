<?php

// TODO Replace this with a .env file before merging

define('PRODUCTION', false);

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

// Google API Key \\
define('GOOGLE_API_KEY', '');

// WebSocket Connection \\
define('WS_SERVER_HOST', '');
define('WS_SERVER_KEY', '');

// Discord API \\
define('DISCORD_BOT_TOKEN', '');
define('DISCORD_SERVER_ID', 0);
define('DISCORD_CLIENT', '');
define('DISCORD_SECRET', '');

// CSP \\
define('CSP_ENABLED', false);
define('CSP_NONCE', base64_encode(random_bytes(16)));
define('CSP_HEADER', '');

// Redis \\
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);

// The Movie Database API \\
define('TMDB_API_KEY', '');

// Development \\
//define('MAINTENANCE_START', strtotime(''));
define('SOCKET_SSL_CTX', ['verify_peer' => false]);
