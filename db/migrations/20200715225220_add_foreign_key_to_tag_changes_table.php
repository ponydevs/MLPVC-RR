<?php

use Phinx\Migration\AbstractMigration;

class AddForeignKeyToTagChangesTable extends AbstractMigration {
  public function up() {
    $this->query('ALTER TABLE tag_changes ALTER COLUMN tag_id DROP NOT NULL');
    $screwed_ids = $this->fetchAll('SELECT DISTINCT tag_id FROM tag_changes tc WHERE (SELECT count(id) FROM tags t WHERE t.id = tc.tag_id) = 0');
    if (!empty($screwed_ids)) {
      $in_query = implode(',', array_map(fn($x) => $x['tag_id'], $screwed_ids));
      $this->query(sprintf('UPDATE tag_changes SET tag_id = null WHERE tag_id IN (%s)', $in_query));
    }
    $this->table('tag_changes')
      ->addForeignKey('tag_id', 'tags', 'id', ['delete' => 'set null', 'update' => 'cascade'])
      ->update();
  }
  public function down() {
    $this->table('tag_changes')
      ->dropForeignKey('tag_id')
      ->update();
    $this->query('DELETE FROM tag_changes WHERE tag_id IS NULL');
    $this->query('ALTER TABLE tag_changes ALTER COLUMN tag_id SET NOT NULL');
  }
}
