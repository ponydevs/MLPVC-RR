<?php

	require_once "MysqliDb.php";

	class MysqliDbWrapper extends MysqliDb {
		/**
		 * Alternate constructor which uses values from config.php as defaults
		 *
		 * @param string $db   Database name
		 * @param string $host Database host
		 * @param string $user Database acesss username
		 * @param string $pass Database acesss password
		 */
		public function __construct($db, $host = DB_HOST, $user = DB_USER, $pass = DB_PASS){
			parent::__construct($host,$user,$pass,$db);
		}
		/**
		 * Execute where method with the specified episode and season numbers
		 *
		 * @param array $args Arguments to be forwarded to rawQuery
		 *
		 * @return dbObject
		 */
		public function rawQuerySingle(...$args){
			return $this->_singleRow(parent::rawQuery(...$args));
		}
		/**
		 * Get the first entry in a query if it exists, otherwise, return null
		 *
		 * @param array $query Array containing the query results
		 *
		 * @return array|null
		 */
		protected function _singleRow($query){
			return empty($query[0]) ? null : $query[0];
		}
		/**
		 * Execute where method with the specified episode and season numbers
		 *
		 * @param string|int|array $s Season, or array with keys season & episode
		 * @param string|int|null $e Episode, optional if $s is an array
		 *
		 * @return dbObject
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
		/**
		 * Get number of rows in database table
		 *
		 * @param string $table Name of table
		 *
		 * @return int
		 */
		public function count($table){
			return parent::getOne($table, 'COUNT(*) as c')['c'];
		}
		/**
		 * Replacement for the orderBy method, which would screw up complex order statements
		 *
		 * @param string $orderstr Raw ordering sting
		 * @param string $direction Order direction
		 *
		 * @return dbObject
		 */
		public function orderByLiteral($orderstr, $direction = 'ASC'){
            $this->_orderBy[$orderstr] = $direction;
            return $this;
		}
	}
