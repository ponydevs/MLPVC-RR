<?php

use App\CGUtils;

require __DIR__.'/../config/init/minimal.php';
require __DIR__.'/../config/init/db_class.php';

CGUtils::saveExportData();
