<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__.'/../config/constants.php';

if (PHP_SAPI !== 'cli') {
    throw new Exception('This application must be run on the command line.');
}

use App\CoreUtils;

$csv_path = FSPATH.'names.csv';

if (!file_exists($csv_path)) {
  fwrite(STDERR, "The specified file does not exist\n");
  exit(1);
}

$parsed_csv = array_map('str_getcsv', file($csv_path));
array_shift($parsed_csv);

$output = [];
foreach ($parsed_csv as $row) {
  $appearance_id = (int) array_shift($row);
  array_shift($row);
  if (empty($row[0]))
    continue;

  $output[$appearance_id] = array_filter($row, fn($el) => !empty($el));
}

$outpath = FSPATH.'babel.php';
$result = file_put_contents($outpath, "<?php\ndefine('BABEL_ARRAY',".var_export($output, true).');');

if (!$result) {
  fwrite(STDERR, "Failed to write output file $outpath\n");
  exit(1);
}

echo "Wrote output to $outpath\n";
