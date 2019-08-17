<?php

use Phinx\Migration\AbstractMigration;

class RenameDevRoleLabelGlobalSetting extends AbstractMigration {
  public function up() {
    $this->query("UPDATE global_settings SET key = 'dev_role_label' WHERE key = 'dev_rolelabel'");
  }

  public function down() {
    $this->query("UPDATE global_settings SET key = 'dev_rolelabel' WHERE key = 'dev_role_label'");
  }
}
