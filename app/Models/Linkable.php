<?php

namespace App\Models;

interface Linkable {
  /**
   * Returns the public-facing URL of this model
   *
   * @return string
   */
  public function toURL():string;

  /**
   * Returns an anchor with the public-facing URL and the model's name/label/ID as the text
   *
   * @return string
   */
  public function toAnchor():string;
}
