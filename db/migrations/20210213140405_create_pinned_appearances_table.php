<?php

use Phinx\Migration\AbstractMigration;

class CreatePinnedAppearancesTable extends AbstractMigration {
  public function change() {
    $table = $this->table('pinned_appearances');
    $table
      ->addColumn('appearance_id', 'integer')
      ->addColumn('guide', 'string', ['length' => 4])
      ->addColumn('order', 'integer', ['default' => 0])
      ->addIndex('appearance_id', ['unique' => true])
      ->addIndex('guide')
      ->addTimestampsWithTimezone()
      ->addForeignKey('appearance_id', 'appearances', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
      ->create();

    if ($this->isMigratingUp){
      $universal_colors = $this->query("SELECT id, guide FROM appearances WHERE label = 'Universal Colors'")->fetchAll();
      $table
        ->insert(array_map(fn(array $result) => [
          'appearance_id' => $result['id'],
          'guide' => $result['guide'],
        ], $universal_colors))
        ->save();
    }
  }
}
