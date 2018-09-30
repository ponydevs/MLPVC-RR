<?php

$ar_conn = \Activerecord\Connection::instance();
\App\DB::$instance = \App\PostgresDbWrapper::withConnection($ar_conn->connection);
