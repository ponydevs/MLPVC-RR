<?php

use Phinx\Migration\AbstractMigration;

class RenameAppearancesIshumanColumn extends AbstractMigration {
  public function up() {
    $this->query('ALTER TABLE appearances RENAME ishuman TO guide');
    $this->query("ALTER TABLE appearances ALTER COLUMN guide TYPE character varying(4) USING CASE WHEN guide IS NULL THEN null WHEN guide THEN 'eqg' ELSE 'pony' END");
  }

  public function down() {
    $this->query("ALTER TABLE appearances ALTER COLUMN guide TYPE bool USING CASE WHEN guide IS NULL THEN null ELSE guide = 'eqg' END");
    $this->query('ALTER TABLE appearances RENAME guide TO ishuman');
  }
}
