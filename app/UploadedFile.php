<?php

namespace App;

class UploadedFile {
  /** @var string */
  public
    $name,
    $type,
    $tmp_name;
  /** @var int */
  public
    $error,
    $size;

  public const SIZES = [
    'byte' => 1,
    'kilobyte' => 1024,
    'megabyte' => 1_048_576,
  ];

  public function __construct($data) {
    foreach ($data as $k => $v){
      if (!property_exists(__CLASS__, $k))
        continue;

      $this->{$k} = $v;
    }
  }
}
