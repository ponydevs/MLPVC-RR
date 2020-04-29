<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

class AllowNullJoniedAtForDiscordMembers extends AbstractMigration {
  public function up() {
    $this->query('ALTER TABLE discord_members ALTER joined_at DROP NOT NULL');
  }

  public function down():void {
    throw new IrreversibleMigrationException("There is no going back");
  }
}
