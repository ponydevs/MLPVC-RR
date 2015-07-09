<?php

	require_once "MysqliDb.php";

	class MysqliDbWrapper extends MysqliDb {
		public function rawQuerySingle(...$args){
			return $this->singleRow(parent::rawQuery(...$args));
		}
		public function singleRow($query){
			return empty($query[0]) ? null : $query[0];
		}
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
	}