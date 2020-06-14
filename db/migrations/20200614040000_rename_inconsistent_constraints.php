<?php

use Phinx\Migration\AbstractMigration;

class RenameInconsistentConstraints extends AbstractMigration {
  public function up():void {
    $this->runAll();
  }

  public function down():void {
    $this->runAll();
  }

  // Helper methods

  private function runAll():void {
    $this->renameConstraint('show_votes', 'episode_votes_user_id_fkey', 'show_votes_user_id_fkey');
    $this->renameIndex('episode_votes_user_id', 'show_votes_user_id');

    $this->renameConstraint('show', 'episodes_posted_by_fkey', 'show_posted_by_fkey');
    $this->renameIndex('episodes_posted_by', 'show_posted_by');

    $this->renameIndex('global_settings_pkey', 'settings_pkey');

    $this->renameConstraint('previous_usernames', 'log__da_namechange_user_id_fkey', 'previous_usernames_user_id_fkey');
    $this->renameIndex('log__da_namechange_pkey', 'previous_usernames_pkey');
    $this->renameIndex('log__da_namechange_user_id', 'previous_usernames_user_id');

    $this->renameIndex('log__major_changes_pkey', 'major_changes_pkey');
    $this->renameIndex('log__major_changes_appearance_id', 'major_changes_appearance_id');

    $this->renameConstraint('logs', 'log_initiator_fkey', 'logs_initiator_fkey');
    $this->renameIndex('log_pkey', 'logs_pkey');
    $this->renameIndex('log_initiator', 'logs_initiator');

    $this->renameConstraint('locked_posts', 'log__post_lock_user_id_fkey', 'locked_posts_user_id_fkey');
    $this->renameIndex('log__post_lock_pkey', 'locked_posts_pkey');

    $this->renameIndex('log__failed_auth_attempts_pkey', 'failed_auth_attempts_pkey');

    $this->renameIndex('log__post_break_pkey', 'broken_posts_pkey');
  }

  private function renameConstraint(string $table, string $old_name, string $new_name):void {
    if (!$this->isMigratingUp)
      [$old_name, $new_name] = [$new_name, $old_name];
    $this->query("ALTER TABLE $table RENAME CONSTRAINT $old_name TO $new_name");
  }

  private function renameIndex(string $old_name, string $new_name):void {
    if (!$this->isMigratingUp)
      [$old_name, $new_name] = [$new_name, $old_name];
    $this->query("ALTER INDEX $old_name RENAME TO $new_name");
  }
}
