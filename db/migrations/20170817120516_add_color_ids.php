<?php

use Phinx\Migration\AbstractMigration;

class AddColorIds extends AbstractMigration {
  public function up() {
    $this->execute('ALTER TABLE colors ADD id serial');
    $this->execute('ALTER TABLE colors ADD CONSTRAINT colors_pkey PRIMARY KEY (id)');
  }

  public function down() {
    $this->execute('ALTER TABLE colors DROP id');
    $this->execute('ALTER TABLE colors DROP CONSTRAINT colors_pkey');
  }
}
