<?php

use App\CoreUtils;

require __DIR__.'/../config/init/minimal.php';

$result = CoreUtils::generateApiSchema();
if (!$result){
	fwrite(STDERR, "Could not write API schema\n");
	exit(1);
}
fwrite(STDOUT, "Written API schema ($result bytes)\n");
exit(0);

