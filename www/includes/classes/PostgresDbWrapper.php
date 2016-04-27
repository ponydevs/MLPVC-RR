<?php

	require_once "PostgresDb.php";

	class PostgresDbWrapper extends PostgresDb {
		/**
		 * Execute where method with the specified episode and season numbers
		 *
		 * @param string|int|array $s Season, or array with keys season & episode
		 * @param string|int|null $e Episode, optional if $s is an array
		 *
		 * @return PostgresDb
		 */
		public function whereEp($s, $e = null){
			if (!isset($e)){
				parent::where('season', intval($s['season']));
				parent::where('episode',intval($s['episode']));
			}
			else {
				parent::where('season', $s);
				parent::where('episode',$e);
			}
			return $this;
		}

		public $query_count = 0;

		protected function _execStatement($stmt){
			$this->query_count++;
			return parent::_execStatement($stmt);
		}
	}
