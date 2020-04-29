<?php

use Activerecord\Connection;
use App\DB;
use App\PostgresDbWrapper;

$ar_conn = Connection::instance();
DB::$instance = PostgresDbWrapper::withConnection($ar_conn->connection);
