<?php

namespace App;

use ActiveRecord\Model;
use App\Models\Episode;

class PostgresDbWrapper extends \SeinopSys\PostgresDb {
	public static function withConnection(\PDO $PDO):PostgresDbWrapper {
		$instance = new self();
		$instance->setConnection($PDO);
		return $instance;
	}

	/**
	 * Execute where method with the specified episode and season numbers
	 *
	 * @param int|Episode $s Season, or array with keys season & episode
	 * @param int|null    $e Episode, optional if $s is and instance of Episode
	 *
	 * @return self
	 */
	public function whereEp($s, $e = null):self {
		if ($e === null){
			if (!$s instanceof Episode)
				throw new \InvalidArgumentException(__METHOD__.' expects parameter 1 to be an instance of '.Episode::class.' (because parameter 2 is null), '.\gettype($s).' given');
			parent::where('season', $s->season);
			parent::where('episode', $s->episode);
		}
		else {
			parent::where('season', $s);
			parent::where('episode', $e);
		}
		return $this;
	}

	/**
	 * Sets the output format to use the specified class with late property fetching for php-activerecord
	 * Expects ModelName::class as the name argument, or the equivalent fully qualified model name.
	 *
	 * @param string $class_name Fully qualified class name
	 *
	 * @return self
	 */
	public function setModel(string $class_name):self {
		if (!CoreUtils::startsWith($class_name, 'App\\'))
			$class_name = "App\\Models\\$class_name";
		if (!class_exists($class_name))
			throw new \RuntimeException("The model $class_name does not exist");

		$this->setClass($class_name, \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE);

		return $this;
	}

	private $non_existing_class_cache = [];

	/**
	 * @inheritdoc
	 */
	protected function execStatement($stmt, $reset = true){
		$class_name = $this->tableNameToClassName();
		if ($class_name !== null && empty($this->non_existing_class_cache[$class_name])){
			try {
				$this->setModel($class_name);
			}
			catch (\RuntimeException $e){$this->non_existing_class_cache[$class_name] = true; }
		}

		$exec_result = parent::execStatement($stmt, $reset);
		$is_array = \is_array($exec_result);
		if ($is_array && \count($exec_result) > 0)
			$check = $exec_result[0];
		else $check = $exec_result;

		if ($check instanceof Model){
			/** @var $exec_result Model|Model[] */
			if ($is_array){
				foreach ($exec_result as $el)
					$el->forceExisting(true);
			}
			else $exec_result->forceExisting(true);
		}

		return $exec_result;
	}
}
