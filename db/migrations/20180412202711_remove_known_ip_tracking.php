<?php

use Phinx\Migration\AbstractMigration;

class RemoveKnownIpTracking extends AbstractMigration {
  public function change() {
    $this->table('known_ips')->drop()->save();
  }
}
