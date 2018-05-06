<?php

use Phinx\Migration\AbstractMigration;

class IncreaseEpisodeVideoIdLength extends AbstractMigration {
	public function change() {
		$this->table('episode_videos')
			->changeColumn('id','string',['length' => 64])
			->update();
	}
}
