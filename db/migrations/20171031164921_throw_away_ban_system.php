<?php

use Phinx\Migration\AbstractMigration;

class ThrowAwayBanSystem extends AbstractMigration {
  public function change() {
    $this->table('log__banish')->drop()->save();
    $this->table('log__unbanish')->drop()->save();
  }
}
