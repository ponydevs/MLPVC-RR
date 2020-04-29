<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__.'/../config/constants.php';

if (PHP_SAPI !== 'cli') {
    throw new Exception('This file must be run from the command line.');
}

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
  $names = array_values(array_filter($row, fn($el) => !empty($el)));
  if (empty($names))
    continue;

  $output[(int) $appearance_id] = $names;
}

$outpath = CONFPATH.'nutshell_names.php';
$result = file_put_contents($outpath, "<?php\ndefine('NUTSHELL_NAMES',".preg_replace('~"(\d+)"=>~','$1=>',str_replace(['{', '}', ':'], ['[',']','=>'], json_encode($output))).');');

if (!$result) {
  fwrite(STDERR, "Failed to write output file $outpath\n");
  exit(1);
}

echo "Wrote output to $outpath\n";
