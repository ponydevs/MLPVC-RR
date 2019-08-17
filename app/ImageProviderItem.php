<?php

namespace App;

class ImageProviderItem {
  /** @var string */
  public $name;
  /** @var int|string */
  public $itemid;

  public function __construct($name, $itemid) {
    $this->name = $name;
    $this->itemid = $itemid;
  }
}
