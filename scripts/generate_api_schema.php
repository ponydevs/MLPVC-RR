<?php

use App\CoreUtils;

require __DIR__.'/../config/init/minimal.php';

try {
  $result = CoreUtils::generateApiSchema();
}
catch (Exception $e){
  fwrite(STDERR, implode("\n", ['Could not write API schema to file: '.get_class($e), $e->getMessage(), 'Stack trace:', $e->getTraceAsString(), '']));
  exit(1);
}
fwrite(STDOUT, "Written API schema to file\n");
exit(0);

