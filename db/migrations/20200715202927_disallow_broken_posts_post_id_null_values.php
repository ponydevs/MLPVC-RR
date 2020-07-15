<?php

use Phinx\Migration\AbstractMigration;

class DisallowBrokenPostsPostIdNullValues extends AbstractMigration {
  public function up() {
    $this->query('ALTER TABLE broken_posts ALTER COLUMN post_id SET NOT NULL');
  }
  public function down() {
    $this->query('ALTER TABLE broken_posts ALTER COLUMN post_id DROP NOT NULL');
  }
}
