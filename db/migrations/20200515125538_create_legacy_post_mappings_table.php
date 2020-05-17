<?php /** @noinspection SqlResolve */

use Phinx\Migration\AbstractMigration;

class CreateLegacyPostMappingsTable extends AbstractMigration {
  public function up() {
    $this->table('legacy_post_mappings')
      ->addColumn('post_id', 'integer')
      ->addColumn('old_id', 'integer')
      ->addColumn('type', 'string', ['length' => 11])
      ->addForeignKey('post_id', 'posts', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
      ->addIndex('post_id', ['unique' => true])
      ->addIndex(['old_id', 'type'])
      ->create();

    $this->query("INSERT INTO legacy_post_mappings (post_id, old_id, type) SELECT id as post_id, old_id, (CASE WHEN requested_by IS NULL THEN 'reservation' ELSE 'request' END) as type FROM posts WHERE old_id IS NOT NULL");

    $this->query('UPDATE locked_posts lp SET post_id = lpm.post_id FROM (SELECT * FROM legacy_post_mappings) lpm WHERE lpm.type = lp.type AND lpm.old_id = lp.old_post_id');

    $this->query('UPDATE broken_posts bp SET post_id = lpm.post_id FROM (SELECT * FROM legacy_post_mappings) lpm WHERE lpm.type = bp.type AND lpm.old_id = bp.old_post_id');

    $this->query('DELETE FROM locked_posts lp WHERE NOT EXISTS(SELECT * FROM posts p WHERE p.id = lp.post_id)');

    $this->query('DELETE FROM broken_posts lp WHERE NOT EXISTS(SELECT * FROM posts p WHERE p.id = lp.post_id)');

    $this->table('posts')
      ->removeColumn('old_id')
      ->update();

    $this->table('locked_posts')
      ->removeColumn('old_post_id')
      ->removeColumn('type')
      ->addIndex('post_id')
      ->addForeignKey('post_id', 'posts', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
      ->update();

    $this->table('broken_posts')
      ->removeColumn('old_post_id')
      ->removeColumn('type')
      ->addIndex('post_id')
      ->addForeignKey('post_id', 'posts', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
      ->update();
  }

  public function down() {
    $this->table('posts')
      ->addColumn('old_id', 'integer', ['null' => true])
      ->update();

    $this->table('locked_posts')
      ->removeIndex('post_id')
      ->dropForeignKey('post_id')
      ->addColumn('old_post_id', 'integer', ['null' => true])
      ->addColumn('type', 'string', ['length' => 11, 'null' => true])
      ->update();

    $this->table('broken_posts')
      ->removeIndex('post_id')
      ->dropForeignKey('post_id')
      ->addColumn('old_post_id', 'integer', ['null' => true])
      ->addColumn('type', 'string', ['length' => 11, 'null' => true])
      ->update();

    $this->query("UPDATE posts p SET old_id = lpm.old_id FROM (SELECT post_id, old_id from legacy_post_mappings) lpm WHERE lpm.post_id = p.id");

    $this->query("UPDATE locked_posts lp SET old_post_id = lpm.old_id, type = lpm.type FROM (SELECT post_id, old_id, type from legacy_post_mappings) lpm WHERE lpm.post_id = lp.post_id");

    $this->query("UPDATE broken_posts bp SET old_post_id = lpm.old_id, type = lpm.type FROM (SELECT post_id, old_id, type from legacy_post_mappings) lpm WHERE lpm.post_id = bp.post_id");

    $this->table('legacy_post_mappings')->drop()->update();
  }
}
