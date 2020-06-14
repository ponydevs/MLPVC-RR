<?php /** @noinspection SqlResolve */

use Phinx\Migration\AbstractMigration;

class ReplaceUserIdReferences extends AbstractMigration {
  public function up():void {
    $this->migrateAllTables();
  }

  public function down():void {
    $this->migrateAllTables();
  }

  // Table-specific methods

  private function migrateAllTables():void {
    $calls = [
      fn() => $this->migrateUserPrefs(),
      fn() => $this->migrateTagChanges(),
      fn() => $this->migrateShowVotes(),
      fn() => $this->migrateShow(),
      fn() => $this->migrateSessions(),
      fn() => $this->migratePosts(),
      fn() => $this->migratePcgSlotHistory(),
      fn() => $this->migratePcgPointGrants(),
      fn() => $this->migrateNotifications(),
      fn() => $this->migrateNotices(),
      fn() => $this->migrateMajorChanges(),
      fn() => $this->migrateLogs(),
      fn() => $this->migrateLockedPosts(),
      fn() => $this->migrateEvents(),
      fn() => $this->migrateEventEntryVotes(),
      fn() => $this->migrateEventEntries(),
      fn() => $this->migrateDiscordMembers(),
      fn() => $this->migrateAppearances(),
    ];

    if (!$this->isMigratingUp)
      $calls = array_reverse($calls);

    foreach ($calls as $execute)
      $execute();
  }

  private function migrateUserPrefs():void {
    $table = $this->table('user_prefs');
    if ($this->isMigratingUp){
      $table->renameColumn('user_id', 'old_user_id')->update();
      $this->query(<<<'SQL'
        ALTER TABLE user_prefs
        DROP CONSTRAINT user_prefs_pkey,
        DROP CONSTRAINT user_prefs_user_id_fkey,
        ADD COLUMN id serial PRIMARY KEY,
        ADD COLUMN user_id integer NULL
      SQL);
      $this->query('UPDATE user_prefs tbl SET user_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_user_id');
      $table
        ->changeColumn('user_id', 'integer', ['null' => false])
        ->removeColumn('old_user_id')
        ->addIndex(['user_id', 'key'], ['unique' => true])
        ->addForeignKey('user_id', 'users', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table->renameColumn('user_id', 'new_user_id')->update();
      $this->query(<<<'SQL'
        ALTER TABLE user_prefs
        DROP CONSTRAINT user_prefs_pkey,
        DROP CONSTRAINT user_prefs_user_id_fkey,
        DROP COLUMN id,
        ADD COLUMN user_id uuid NULL
      SQL);
      $this->query('UPDATE user_prefs tbl SET user_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_user_id');
      $table
        ->changeColumn('user_id', 'uuid', ['null' => false])
        ->removeColumn('new_user_id')
        ->changePrimaryKey(['user_id', 'key'])
        ->addForeignKey('user_id', 'deviantart_users', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
        ->update();
    }
  }

  private function migrateTagChanges():void {
    $table = $this->table('tag_changes');
    if ($this->isMigratingUp){
      $table
        ->removeIndexByName('tag_changes_user_id')
        ->renameColumn('user_id', 'old_user_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE tag_changes
        DROP CONSTRAINT tag_changes_user_id_fkey,
        ADD COLUMN user_id integer NULL
      SQL);
      $this->query('UPDATE tag_changes tbl SET user_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_user_id');
      $table
        ->changeColumn('user_id', 'integer', ['null' => false])
        ->removeColumn('old_user_id')
        ->addIndex('user_id')
        ->addForeignKey('user_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->removeIndexByName('tag_changes_user_id')
        ->renameColumn('user_id', 'new_user_id')
        ->update();
      $this->query(<<<'SQL'
      ALTER TABLE tag_changes
      DROP CONSTRAINT tag_changes_user_id_fkey,
      ADD COLUMN user_id uuid NULL
    SQL
      );
      $this->query('UPDATE tag_changes tbl SET user_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_user_id');
      $table
        ->changeColumn('user_id', 'uuid', ['null' => false])
        ->removeColumn('new_user_id')
        ->addIndex('user_id')
        ->addForeignKey('user_id', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
  }

  private function migrateShowVotes():void {
    $table = $this->table('show_votes');
    if ($this->isMigratingUp){
      $table
        ->renameColumn('user_id', 'old_user_id')
        ->removeIndexByName('show_votes_user_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE show_votes
        DROP CONSTRAINT show_votes_pkey,
        DROP CONSTRAINT show_votes_user_id_fkey,
        ADD COLUMN id serial PRIMARY KEY,
        ADD COLUMN user_id integer NULL
      SQL);
      $this->query('UPDATE show_votes tbl SET user_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_user_id');
      $table
        ->changeColumn('user_id', 'integer', ['null' => false])
        ->removeColumn('old_user_id')
        ->addIndex(['user_id', 'show_id'], ['unique' => true])
        ->addForeignKey('user_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table->renameColumn('user_id', 'new_user_id')->update();
      $this->query(<<<'SQL'
        ALTER TABLE show_votes
        DROP CONSTRAINT show_votes_pkey,
        DROP CONSTRAINT show_votes_user_id_fkey,
        DROP COLUMN id,
        ADD COLUMN user_id uuid NULL
      SQL);
      $this->query('UPDATE show_votes tbl SET user_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_user_id');
      $table
        ->changeColumn('user_id', 'uuid', ['null' => false])
        ->removeColumn('new_user_id')
        ->changePrimaryKey(['user_id', 'show_id'])
        ->addForeignKey('user_id', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
  }

  private function migrateShow():void {
    $table = $this->table('show');
    if ($this->isMigratingUp){
      $table
        ->removeIndexByName('show_posted_by')
        ->renameColumn('posted_by', 'old_posted_by')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE show
        DROP CONSTRAINT show_posted_by_fkey,
        ADD COLUMN posted_by integer NULL
      SQL);
      $this->query('UPDATE show tbl SET posted_by = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_posted_by');
      $table
        ->changeColumn('posted_by', 'integer', ['null' => false])
        ->removeColumn('old_posted_by')
        ->addIndex('posted_by')
        ->addForeignKey('posted_by', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->removeIndexByName('show_posted_by')
        ->renameColumn('posted_by', 'new_posted_by')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE show
        DROP CONSTRAINT show_posted_by_fkey,
        ADD COLUMN posted_by uuid NULL
      SQL);
      $this->query('UPDATE show tbl SET posted_by = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_posted_by');
      $table
        ->changeColumn('posted_by', 'uuid', ['null' => false])
        ->removeColumn('new_posted_by')
        ->addIndex('posted_by')
        ->addForeignKey('posted_by', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
  }

  private function migrateSessions():void {
    $table = $this->table('sessions');
    if ($this->isMigratingUp){
      $table
        ->renameColumn('id', 'old_id')
        ->renameColumn('user_id', 'old_user_id')
        ->removeIndexByName('sessions_user_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE sessions
        DROP CONSTRAINT sessions_pkey,
        DROP CONSTRAINT sessions_user_id_fkey,
        ADD COLUMN id serial PRIMARY KEY,
        ADD COLUMN user_id integer NULL
      SQL);
      $this->query('UPDATE sessions tbl SET user_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_user_id');
      $table
        ->removeColumn('old_user_id')
        ->removeColumn('old_id')
        ->addIndex('user_id')
        ->addForeignKey('user_id', 'users', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->renameColumn('user_id', 'new_user_id')
        ->renameColumn('id', 'new_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE sessions
        DROP CONSTRAINT sessions_pkey,
        DROP CONSTRAINT sessions_user_id_fkey,
        ADD COLUMN id uuid DEFAULT uuid_generate_v4(),
        ADD COLUMN user_id uuid NULL
      SQL);
      $this->query('UPDATE sessions tbl SET user_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_user_id');
      $table
        ->removeColumn('new_user_id')
        ->removeColumn('new_id')
        ->changePrimaryKey('id')
        ->addForeignKey('user_id', 'deviantart_users', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
        ->update();
    }
  }

  private function migratePosts():void {
    $table = $this->table('posts');
    if ($this->isMigratingUp){
      $table
        ->renameColumn('requested_by', 'old_requested_by')
        ->renameColumn('reserved_by', 'old_reserved_by')
        ->removeIndexByName('posts_requested_by')
        ->removeIndexByName('posts_reserved_by')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE posts
        DROP CONSTRAINT posts_requested_by_fkey,
        DROP CONSTRAINT posts_reserved_by_fkey,
        ADD COLUMN requested_by integer NULL,
        ADD COLUMN reserved_by integer NULL
      SQL);
      $this->query('UPDATE posts tbl SET requested_by = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_requested_by');
      $this->query('UPDATE posts tbl SET reserved_by = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_reserved_by');
      $table
        ->removeColumn('old_requested_by')
        ->removeColumn('old_reserved_by')
        ->addIndex('requested_by')
        ->addIndex('reserved_by')
        ->addForeignKey('requested_by', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->addForeignKey('reserved_by', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->renameColumn('requested_by', 'new_requested_by')
        ->renameColumn('reserved_by', 'new_reserved_by')
        ->removeIndexByName('posts_requested_by')
        ->removeIndexByName('posts_reserved_by')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE posts
        DROP CONSTRAINT posts_requested_by_fkey,
        DROP CONSTRAINT posts_reserved_by_fkey,
        ADD COLUMN requested_by uuid NULL,
        ADD COLUMN reserved_by uuid NULL
      SQL);
      $this->query('UPDATE posts tbl SET requested_by = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_requested_by');
      $this->query('UPDATE posts tbl SET reserved_by = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_reserved_by');
      $table
        ->removeColumn('new_requested_by')
        ->removeColumn('new_reserved_by')
        ->addIndex('requested_by')
        ->addIndex('reserved_by')
        ->addForeignKey('requested_by', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->addForeignKey('reserved_by', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
  }

  private function migratePcgSlotHistory():void {
    $table = $this->table('pcg_slot_history');
    if ($this->isMigratingUp){
      $table->renameColumn('user_id', 'old_user_id')->update();
      $table->addColumn('user_id', 'integer', ['null' => true])->update();
      $this->query('UPDATE pcg_slot_history tbl SET user_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_user_id');
      $table
        ->changeColumn('user_id', 'integer', ['null' => false])
        ->removeColumn('old_user_id')
        ->addIndex('user_id')
        ->addForeignKey('user_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->removeIndexByName('pcg_slot_history_user_id')
        ->renameColumn('user_id', 'new_user_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE pcg_slot_history
        DROP CONSTRAINT pcg_slot_history_user_id_fkey,
        ADD COLUMN user_id uuid NULL
      SQL);
      $this->query('UPDATE pcg_slot_history tbl SET user_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_user_id');
      $table
        ->changeColumn('user_id', 'uuid', ['null' => false])
        ->removeColumn('new_user_id')
        ->update();
    }
  }

  private function migratePcgPointGrants():void {
    $table = $this->table('pcg_point_grants');
    if ($this->isMigratingUp){
      $table
        ->renameColumn('receiver_id', 'old_receiver_id')
        ->renameColumn('sender_id', 'old_sender_id')
        ->update();
      $table
        ->addColumn('receiver_id', 'integer', ['null' => true])
        ->addColumn('sender_id', 'integer', ['null' => true])
        ->update();
      $this->query('UPDATE pcg_point_grants tbl SET receiver_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_receiver_id');
      $this->query('UPDATE pcg_point_grants tbl SET sender_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_sender_id');
      $table
        ->changeColumn('receiver_id', 'integer', ['null' => false])
        ->changeColumn('sender_id', 'integer', ['null' => false])
        ->removeColumn('old_receiver_id')
        ->removeColumn('old_sender_id')
        ->addIndex('receiver_id')
        ->addIndex('sender_id')
        ->addForeignKey('receiver_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->addForeignKey('sender_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->renameColumn('receiver_id', 'new_receiver_id')
        ->renameColumn('sender_id', 'new_sender_id')
        ->removeIndexByName('pcg_point_grants_receiver_id')
        ->removeIndexByName('pcg_point_grants_sender_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE pcg_point_grants
        DROP CONSTRAINT pcg_point_grants_receiver_id_fkey,
        DROP CONSTRAINT pcg_point_grants_sender_id_fkey,
        ADD COLUMN receiver_id uuid NULL,
        ADD COLUMN sender_id uuid NULL
      SQL);
      $this->query('UPDATE pcg_point_grants tbl SET receiver_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_receiver_id');
      $this->query('UPDATE pcg_point_grants tbl SET sender_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_sender_id');
      $table
        ->changeColumn('receiver_id', 'uuid', ['null' => false])
        ->changeColumn('sender_id', 'uuid', ['null' => false])
        ->removeColumn('new_receiver_id')
        ->removeColumn('new_sender_id')
        ->update();
    }
  }
  
  private function migrateNotifications():void {
    $table = $this->table('notifications');
    $this->query('DROP VIEW unread_notifications');

    if ($this->isMigratingUp){
      $table
        ->removeIndexByName('notifications_recipient_id')
        ->renameColumn('recipient_id', 'old_recipient_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE notifications
        DROP CONSTRAINT notifications_recipient_id_fkey,
        ADD COLUMN recipient_id integer NULL
      SQL);
      $this->query('UPDATE notifications tbl SET recipient_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_recipient_id');
      $table
        ->changeColumn('recipient_id', 'integer', ['null' => false])
        ->removeColumn('old_recipient_id')
        ->addIndex('recipient_id')
        ->addForeignKey('recipient_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();

      $notif_ref_table = 'users';
    }
    else {
      $table
        ->removeIndexByName('notifications_recipient_id')
        ->renameColumn('recipient_id', 'new_recipient_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE notifications
        DROP CONSTRAINT notifications_recipient_id_fkey,
        ADD COLUMN recipient_id uuid NULL
      SQL);
      $this->query('UPDATE notifications tbl SET recipient_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_recipient_id');
      $table
        ->changeColumn('recipient_id', 'uuid', ['null' => false])
        ->removeColumn('new_recipient_id')
        ->addIndex('recipient_id')
        ->addForeignKey('recipient_id', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();

      $notif_ref_table = 'deviantart_users';
    }

    $this->execute(<<<SQL
      CREATE VIEW unread_notifications AS
      SELECT
        u.name AS "user",
        count(n.id) AS count
      FROM notifications n
      LEFT JOIN $notif_ref_table u ON n.recipient_id = u.id
      WHERE n.read_at IS NULL
      GROUP BY u.name
      ORDER BY count(n.id) DESC;
    SQL);
  }

  private function migrateNotices():void {
    $table = $this->table('notices');
    if ($this->isMigratingUp){
      $table
        ->removeIndexByName('notices_posted_by')
        ->renameColumn('posted_by', 'old_posted_by')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE notices
        DROP CONSTRAINT notices_posted_by_fkey,
        ADD COLUMN posted_by integer NULL
      SQL);
      $this->query('UPDATE notices tbl SET posted_by = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_posted_by');
      $table
        ->changeColumn('posted_by', 'integer', ['null' => false])
        ->removeColumn('old_posted_by')
        ->addIndex('posted_by')
        ->addForeignKey('posted_by', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->removeIndexByName('notices_posted_by')
        ->renameColumn('posted_by', 'new_posted_by')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE notices
        DROP CONSTRAINT notices_posted_by_fkey,
        ADD COLUMN posted_by uuid NULL
      SQL);
      $this->query('UPDATE notices tbl SET posted_by = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_posted_by');
      $table
        ->changeColumn('posted_by', 'uuid', ['null' => false])
        ->removeColumn('new_posted_by')
        ->addIndex('posted_by')
        ->addForeignKey('posted_by', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
  }
  
  private function migrateMajorChanges():void {
    $table = $this->table('major_changes');
    if ($this->isMigratingUp){
      $table->renameColumn('user_id', 'old_user_id')->update();
      $table->addColumn('user_id', 'integer', ['null' => true])->update();
      $this->query('UPDATE major_changes tbl SET user_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_user_id');
      $table
        ->changeColumn('user_id', 'integer', ['null' => false])
        ->removeColumn('old_user_id')
        ->addIndex('user_id')
        ->addForeignKey('user_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->removeIndexByName('major_changes_user_id')
        ->renameColumn('user_id', 'new_user_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE major_changes
        DROP CONSTRAINT major_changes_user_id_fkey,
        ADD COLUMN user_id uuid NULL
      SQL);
      $this->query('UPDATE major_changes tbl SET user_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_user_id');
      $table
        ->changeColumn('user_id', 'uuid', ['null' => false])
        ->removeColumn('new_user_id')
        ->update();
    }
  }

  private function migrateLogs():void {
    $table = $this->table('logs');
    if ($this->isMigratingUp){
      $table
        ->removeIndexByName('logs_initiator')
        ->renameColumn('initiator', 'old_initiator')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE logs
        DROP CONSTRAINT logs_initiator_fkey,
        ADD COLUMN initiator integer NULL
      SQL);
      $this->query('UPDATE logs tbl SET initiator = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_initiator');
      $table
        ->removeColumn('old_initiator')
        ->addIndex('initiator')
        ->addForeignKey('initiator', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->removeIndexByName('logs_initiator')
        ->renameColumn('initiator', 'new_initiator')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE logs
        DROP CONSTRAINT logs_initiator_fkey,
        ADD COLUMN initiator uuid NULL
      SQL);
      $this->query('UPDATE logs tbl SET initiator = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_initiator');
      $table
        ->removeColumn('new_initiator')
        ->addIndex('initiator')
        ->addForeignKey('initiator', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
  }

  private function migrateLockedPosts():void {
    $table = $this->table('locked_posts');
    if ($this->isMigratingUp){
      $table
        ->removeIndexByName('locked_posts_user_id')
        ->renameColumn('user_id', 'old_user_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE locked_posts
        DROP CONSTRAINT locked_posts_user_id_fkey,
        ADD COLUMN user_id integer NULL
      SQL);
      $this->query('UPDATE locked_posts tbl SET user_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_user_id');
      $table
        ->removeColumn('old_user_id')
        ->addIndex('user_id')
        ->addForeignKey('user_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->removeIndexByName('locked_posts_user_id')
        ->renameColumn('user_id', 'new_user_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE locked_posts
        DROP CONSTRAINT locked_posts_user_id_fkey,
        ADD COLUMN user_id uuid NULL
      SQL);
      $this->query('UPDATE locked_posts tbl SET user_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_user_id');
      $table
        ->removeColumn('new_user_id')
        ->addIndex('user_id')
        ->addForeignKey('user_id', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
  }

  private function migrateEvents():void {
    $table = $this->table('events');
    if ($this->isMigratingUp){
      $table
        ->renameColumn('added_by', 'old_added_by')
        ->renameColumn('finalized_by', 'old_finalized_by')
        ->removeIndexByName('events_added_by')
        ->removeIndexByName('events_finalized_by')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE events
        DROP CONSTRAINT events_added_by_fkey,
        DROP CONSTRAINT events_finalized_by_fkey,
        ADD COLUMN added_by integer NULL,
        ADD COLUMN finalized_by integer NULL
      SQL);
      $this->query('UPDATE events tbl SET added_by = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_added_by');
      $this->query('UPDATE events tbl SET finalized_by = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_finalized_by');
      $table
        ->changeColumn('added_by', 'integer', ['null' => false])
        ->removeColumn('old_added_by')
        ->removeColumn('old_finalized_by')
        ->addIndex('added_by')
        ->addIndex('finalized_by')
        ->addForeignKey('added_by', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->addForeignKey('finalized_by', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->renameColumn('added_by', 'new_added_by')
        ->renameColumn('finalized_by', 'new_finalized_by')
        ->removeIndexByName('events_added_by')
        ->removeIndexByName('events_finalized_by')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE events
        DROP CONSTRAINT events_added_by_fkey,
        DROP CONSTRAINT events_finalized_by_fkey,
        ADD COLUMN added_by uuid NULL,
        ADD COLUMN finalized_by uuid NULL
      SQL);
      $this->query('UPDATE events tbl SET added_by = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_added_by');
      $this->query('UPDATE events tbl SET finalized_by = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_finalized_by');
      $table
        ->changeColumn('added_by', 'uuid', ['null' => false])
        ->removeColumn('new_added_by')
        ->removeColumn('new_finalized_by')
        ->addIndex('added_by')
        ->addIndex('finalized_by')
        ->addForeignKey('added_by', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->addForeignKey('finalized_by', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
  }

  private function migrateEventEntryVotes():void {
    $table = $this->table('event_entry_votes');
    if ($this->isMigratingUp){
      $table
        ->renameColumn('user_id', 'old_user_id')
        ->removeIndexByName('event_entry_votes_user_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE event_entry_votes
        DROP CONSTRAINT event_entry_votes_pkey,
        DROP CONSTRAINT event_entry_votes_user_id_fkey,
        ADD COLUMN id serial PRIMARY KEY,
        ADD COLUMN user_id integer NULL
      SQL);
      $this->query('UPDATE event_entry_votes tbl SET user_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_user_id');
      $table
        ->changeColumn('user_id', 'integer', ['null' => false])
        ->removeColumn('old_user_id')
        ->addIndex(['user_id', 'entry_id'], ['unique' => true])
        ->addForeignKey('user_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table->renameColumn('user_id', 'new_user_id')->update();
      $this->query(<<<'SQL'
        ALTER TABLE event_entry_votes
        DROP CONSTRAINT event_entry_votes_pkey,
        DROP CONSTRAINT event_entry_votes_user_id_fkey,
        DROP COLUMN id,
        ADD COLUMN user_id uuid NULL
      SQL);
      $this->query('UPDATE event_entry_votes tbl SET user_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_user_id');
      $table
        ->changeColumn('user_id', 'uuid', ['null' => false])
        ->removeColumn('new_user_id')
        ->changePrimaryKey(['user_id', 'entry_id'])
        ->addForeignKey('user_id', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
  }

  private function migrateEventEntries():void {
    $table = $this->table('event_entries');
    if ($this->isMigratingUp){
      $table
        ->removeIndexByName('event_entries_submitted_by')
        ->renameColumn('submitted_by', 'old_submitted_by')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE event_entries
        DROP CONSTRAINT event_entries_submitted_by_fkey,
        ADD COLUMN submitted_by integer NULL
      SQL);
      $this->query('UPDATE event_entries tbl SET submitted_by = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_submitted_by');
      $table
        ->changeColumn('submitted_by', 'integer', ['null' => false])
        ->removeColumn('old_submitted_by')
        ->addIndex('submitted_by')
        ->addForeignKey('submitted_by', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->removeIndexByName('event_entries_submitted_by')
        ->renameColumn('submitted_by', 'new_submitted_by')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE event_entries
        DROP CONSTRAINT event_entries_submitted_by_fkey,
        ADD COLUMN submitted_by uuid NULL
      SQL);
      $this->query('UPDATE event_entries tbl SET submitted_by = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_submitted_by');
      $table
        ->changeColumn('submitted_by', 'uuid', ['null' => false])
        ->removeColumn('new_submitted_by')
        ->addIndex('submitted_by')
        ->addForeignKey('submitted_by', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
  }

  private function migrateDiscordMembers():void {
    $table = $this->table('discord_members');
    if ($this->isMigratingUp){
      $table
        ->removeIndexByName('discord_members_user_id')
        ->renameColumn('user_id', 'old_user_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE discord_members
        DROP CONSTRAINT discord_members_user_id_fkey,
        ADD COLUMN user_id integer NULL
      SQL);
      $this->query('UPDATE discord_members tbl SET user_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_user_id');
      $table
        ->removeColumn('old_user_id')
        ->addIndex('user_id')
        ->addForeignKey('user_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->removeIndexByName('discord_members_user_id')
        ->renameColumn('user_id', 'new_user_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE discord_members
        DROP CONSTRAINT discord_members_user_id_fkey,
        ADD COLUMN user_id uuid NULL
      SQL);
      $this->query('UPDATE discord_members tbl SET user_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_user_id');
      $table
        ->removeColumn('new_user_id')
        ->addIndex('user_id')
        ->addForeignKey('user_id', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
  }

  private function migrateAppearances():void {
    $table = $this->table('appearances');
    if ($this->isMigratingUp){
      $table
        ->removeIndexByName('appearances_owner_id')
        ->renameColumn('owner_id', 'old_owner_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE appearances
        DROP CONSTRAINT appearances_owner_id_fkey,
        ADD COLUMN owner_id integer NULL
      SQL);
      $this->query('UPDATE appearances tbl SET owner_id = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.id = tbl.old_owner_id');
      $table
        ->removeColumn('old_owner_id')
        ->addIndex('owner_id')
        ->addForeignKey('owner_id', 'users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
        ->update();
    }
    else {
      $table
        ->removeIndexByName('appearances_owner_id')
        ->renameColumn('owner_id', 'new_owner_id')
        ->update();
      $this->query(<<<'SQL'
        ALTER TABLE appearances
        DROP CONSTRAINT appearances_owner_id_fkey,
        ADD COLUMN owner_id uuid NULL
      SQL);
      $this->query('UPDATE appearances tbl SET owner_id = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE du.user_id = tbl.new_owner_id');
      $table
        ->removeColumn('new_owner_id')
        ->addIndex('owner_id')
        ->addForeignKey('owner_id', 'deviantart_users', 'id', ['delete' => 'no action', 'update' => 'cascade'])
        ->update();
    }
  }
}
