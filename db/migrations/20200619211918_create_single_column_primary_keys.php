<?php /** @noinspection SqlResolve */

use Phinx\Migration\AbstractMigration;

class CreateSingleColumnPrimaryKeys extends AbstractMigration {
  public function up() {
    $this->query('ALTER TABLE tagged ADD COLUMN id serial NOT NULL, DROP CONSTRAINT tagged_pkey');
    $this->table('tagged')
      ->changePrimaryKey('id')
      ->addIndex(['tag_id', 'appearance_id'], ['unique' => true])
      ->update();

    $this->table('show_videos')
      ->renameColumn('provider', 'provider_abbr')
      ->renameColumn('id', 'provider_id')
      ->update();
    $this->query('ALTER TABLE show_videos ADD COLUMN id serial NOT NULL, DROP CONSTRAINT show_videos_pkey');
    $this->table('show_videos')
      ->changePrimaryKey('id')
      ->addIndex(['provider_abbr', 'part', 'show_id'], ['unique' => true])
      ->update();

    $this->query('ALTER TABLE show_appearances ADD COLUMN id serial NOT NULL, DROP CONSTRAINT show_appearances_pkey');
    $this->table('show_appearances')
      ->changePrimaryKey('id')
      ->addIndex(['show_id', 'appearance_id'], ['unique' => true])
      ->update();

    $this->table('cutiemarks')
      ->addForeignKey('contributor_id', 'deviantart_users', 'id', ['delete' => 'restrict', 'update' => 'cascade'])
      ->save();
  }

  public function down() {
    $this->table('tagged')
      ->removeColumn('id')
      ->removeIndexByName('tagged_tag_id_appearance_id')
      ->changePrimaryKey(['tag_id', 'appearance_id'])
      ->update();

    $this->table('show_videos')
      ->removeColumn('id')
      ->update();
    $this->table('show_videos')
      ->removeIndexByName('show_videos_provider_abbr_part_show_id')
      ->renameColumn('provider_abbr', 'provider')
      ->renameColumn('provider_id', 'id')
      ->changePrimaryKey(['provider', 'part', 'show_id'])
      ->update();

    $this->table('show_appearances')
      ->removeColumn('id')
      ->removeIndexByName('show_appearances_show_id_appearance_id')
      ->changePrimaryKey(['show_id', 'appearance_id'])
      ->update();

    $this->query('ALTER TABLE cutiemarks DROP CONSTRAINT cutiemarks_contributor_id_fkey');
  }
}
