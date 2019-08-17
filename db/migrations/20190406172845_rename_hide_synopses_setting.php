<?php

use Phinx\Migration\AbstractMigration;

class RenameHideSynopsesSetting extends AbstractMigration {
  public function change() {
    $this->query("UPDATE user_prefs SET key = 'ep_hidesynopses' WHERE key = 'p_hidesynopses'");
  }
}
