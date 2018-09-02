<?php

use App\CGUtils;

require __DIR__.'/../config/init/minimal.php';
require __DIR__.'/../config/init/db_class.php';

\App\File::put(APPATH.'dist/mlpvc-colorguide.json', CGUtils::getExportData());
