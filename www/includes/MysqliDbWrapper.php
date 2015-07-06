<?php

	require_once "MysqliDb.php";

	class MysqliDbWrapper extends MysqliDb {
		public function rawQuerySingle(...$args){
			return $this->singleRow(parent::rawQuery(...$args));
		}
		public function singleRow($query){
			return empty($query[0]) ? null : $query[0];
		}
		public function whereEp($s, $e){
			parent::where('season', $s);
			parent::where('episode',$e);
			return $this;
		}
	}