<?php

use Phinx\Migration\AbstractMigration;

class AllowNullJoniedAtForDiscordMembers extends AbstractMigration {
  public function up() {
    $this->query('ALTER TABLE discord_members ALTER joined_at DROP NOT NULL');
  }
  // There is no going back
}
