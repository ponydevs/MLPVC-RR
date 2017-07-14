<?php

namespace App;

/**
 * @method static PostgresDbWrapper where(...$args)
 * @method static PostgresDbWrapper orderBy(...$args)
 * @method static PostgresDbWrapper orderByLiteral(...$args)
 * @method static PostgresDbWrapper disableAutoClass()
 * @method static array rawQuery(string $query, ?array $bindParams = null)
 * @method static PostgresDbWrapper setModel(string  $model)
 * @method static int|string|bool insert(string  $tableName, array $data, string $return_col = null)
 * @method static string getLastError()
 * @method static PostgresDbWrapper join(string $tableName, string $on, string $type = '', bool $disableAutoClass = true)
 * @method static array get(string $tableName, int|int[] $numRows = null, string|string[] $columns = null)
 * @method static array getOne(string $tableName, string|string[] $columns = null)
 * @method static array rawQuerySingle(string $query, ?array $bindParams = null)
 * @method static int count(string $tableName)
 */
class DB {
	/** @var PostgresDbWrapper */
	public static $instance;

	public static function __callStatic($name, $arguments){
		return self::$instance->{$name}(...$arguments);
	}
}
