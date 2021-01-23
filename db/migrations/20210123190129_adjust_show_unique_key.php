<?php

use Phinx\Migration\AbstractMigration;

class AdjustShowUniqueKey extends AbstractMigration {
  public function up() {
    $this->query('ALTER TABLE show DROP CONSTRAINT IF EXISTS "show_season_episode"');
    $this->table('show')
      ->addIndex(['season', 'episode', 'generation'], ['unique' => true])
      ->update();
  }

  public function down() {
    $this->query('ALTER TABLE show DROP CONSTRAINT IF EXISTS "show_season_episode_generation"');
    $this->table('show')
      ->removeIndex(['season', 'episode', 'generation'])
      ->addIndex(['season', 'episode'], ['unique' => true])
      ->update();
  }
}
