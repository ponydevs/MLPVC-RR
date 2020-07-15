<?php /** @noinspection SqlResolve */

use Phinx\Migration\AbstractMigration;

class ChangeBrokenPostsReservedByColumnType extends AbstractMigration {
  public function up() {
    $table = $this->table('broken_posts');
    $table
      ->renameColumn('reserved_by', 'old_reserved_by')
      ->update();
    $this->query(<<<'SQL'
        ALTER TABLE broken_posts
        ADD COLUMN reserved_by integer NULL
    SQL);
    $this->query('UPDATE broken_posts tbl SET reserved_by = du.user_id FROM (SELECT id, user_id FROM deviantart_users) du WHERE tbl.old_reserved_by IS NOT NULL and du.id = tbl.old_reserved_by');
    $table
      ->removeColumn('old_reserved_by')
      ->update();
  }

  public function down() {
    $table = $this->table('broken_posts');
    $table
      ->renameColumn('reserved_by', 'new_reserved_by')
      ->update();
    $this->query(<<<'SQL'
        ALTER TABLE broken_posts
        ADD COLUMN reserved_by uuid NULL
    SQL);
    $this->query('UPDATE broken_posts tbl SET reserved_by = du.id FROM (SELECT id, user_id FROM deviantart_users) du WHERE tbl.new_reserved_by IS NOT NULL and du.user_id = tbl.new_reserved_by');
    $table
      ->removeColumn('new_reserved_by')
      ->update();
  }
}
