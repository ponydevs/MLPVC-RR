<?php

use Phinx\Migration\AbstractMigration;

class AddIdToEpisodesTable extends AbstractMigration {
	public function up() {
		# <Preparations>
		// Remove old primary key relations
		$this->query('ALTER TABLE posts DROP CONSTRAINT posts_season_episode_fkey');
		$this->query('ALTER TABLE episode_votes DROP CONSTRAINT episode_votes_season_episode');
		$this->query('ALTER TABLE episode_videos DROP CONSTRAINT episode_videos_season_episode');

		// Drop tables that are just not cool
		$this->query('DROP TABLE log__episode_modify');
		$this->query('DROP TABLE log__episodes');
		$this->query("DELETE FROM log WHERE reftype IN ('episode_modify', 'episodes')");

		// Transform old primary key into unique constraint, add an ID column, and rename
		$this->query('ALTER TABLE episodes DROP constraint episodes_pkey');
		$this->query('ALTER TABLE episodes RENAME TO show');
		$show_type_column_length = 10;
		$this->query("ALTER TABLE show ADD COLUMN id SERIAL PRIMARY KEY, ADD COLUMN type character varying($show_type_column_length) null");
		$this->query('ALTER TABLE show ADD CONSTRAINT show_season_episode UNIQUE (season, episode)');
		$this->query("UPDATE show SET type = (CASE WHEN season = 0 THEN 'movie' ELSE 'episode' END)");

		// Change episode_videos table structure and rename
		$this->query('ALTER TABLE episode_videos DROP constraint episode_videos_pkey');
		$this->query('ALTER TABLE episode_videos RENAME TO show_videos');
		$this->query('ALTER TABLE show_videos ADD COLUMN show_id integer null');

		// Change episode_votes table structure and rename
		$this->query('ALTER TABLE episode_votes DROP constraint episode_votes_pkey');
		$this->query('ALTER TABLE episode_votes RENAME TO show_votes');
		$this->query('ALTER TABLE show_votes ADD COLUMN show_id integer null');

		// Change log table structures
		$this->query('ALTER TABLE log__video_broken ADD COLUMN show_id integer null');
		$this->query('ALTER TABLE log__req_delete ADD COLUMN show_id integer null');

		// Change posts table structure
		$this->query('ALTER TABLE posts ADD COLUMN show_id integer null');
		# </Preparations>


		# <Migrate-Relations>
		$this->query('UPDATE show_videos sv SET show_id = (SELECT id FROM show s WHERE s.season = sv.season AND s.episode = sv.episode)');
		$this->query('UPDATE show_votes sv SET show_id = (SELECT id FROM show s WHERE s.season = sv.season AND s.episode = sv.episode)');
		$this->query('UPDATE log__video_broken vb SET show_id = (SELECT id FROM show s WHERE s.season = vb.season AND s.episode = vb.episode)');
		$this->query('UPDATE log__req_delete vb SET show_id = (SELECT id FROM show s WHERE s.season = vb.season AND s.episode = vb.episode)');
		$this->query('UPDATE posts p SET show_id = (SELECT id FROM show s WHERE s.season = p.season AND s.episode = p.episode)');
		$this->query('DELETE FROM log__video_broken WHERE show_id IS NULL');
		# </Migrate-Relations>


		# <Post-Import>
		$this->query('ALTER TABLE show ALTER COLUMN season DROP NOT NULL, ALTER COLUMN episode DROP NOT NULL, ALTER COLUMN twoparter DROP NOT NULL');
		$this->query("UPDATE show SET no = episode, season = null, episode = null, twoparter = null WHERE type = 'movie'");
		$this->table('show')
			->changeColumn('id','integer', ['null' => false])
			->changeColumn('type','string', ['length' => $show_type_column_length, 'null' => false])
			->update();
		$this->table('show_videos')
			->removeColumn('season')
			->removeColumn('episode')
			->changeColumn('show_id','integer', ['null' => false])
			->changePrimaryKey(['show_id', 'provider', 'part'])
			->addForeignKey('show_id', 'show', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ])
			->update();
		$this->table('show_votes')
			->removeColumn('season')
			->removeColumn('episode')
			->changeColumn('show_id','integer', ['null' => false])
			->changePrimaryKey(['show_id', 'user_id'])
			->addForeignKey('show_id', 'show', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ])
			->update();
		$this->table('log__video_broken')
			->removeColumn('season')
			->removeColumn('episode')
			->changeColumn('show_id','integer', ['null' => false])
			->update();
		$this->table('log__req_delete')
			->removeColumn('season')
			->removeColumn('episode')
			->changeColumn('show_id','integer', ['null' => false])
			->update();
		$this->table('posts')
			->removeColumn('season')
			->removeColumn('episode')
			->changeColumn('show_id','integer', ['null' => false])
			->addIndex('show_id')
			->addForeignKey('show_id', 'show', 'id', [ 'delete' => 'CASCADE', 'update' => 'CASCADE' ])
			->update();
		# </Post-Import>
	}
}
