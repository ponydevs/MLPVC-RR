<?php

use Phinx\Migration\AbstractMigration;

class ChangeSessionDataColumnTypeToJsonb extends AbstractMigration {
  public function up() {
    $this->query('ALTER TABLE sessions ALTER COLUMN data TYPE jsonb USING data::jsonb;');
  }

  public function down() {
    $this->query('ALTER TABLE sessions ALTER COLUMN data TYPE text USING data::text;');
  }
}
