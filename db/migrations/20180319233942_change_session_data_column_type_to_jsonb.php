<?php

use Phinx\Migration\AbstractMigration;

class ChangeSessionDataColumnTypeToJsonb extends AbstractMigration {
  public function up() {
    $this->query('ALTER COLUMN data TYPE jsonb;');
  }

  public function down() {
    $this->query('ALTER COLUMN data TYPE text;');
  }
}
