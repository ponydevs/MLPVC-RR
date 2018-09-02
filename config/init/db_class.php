<?php

$conn = \Activerecord\Connection::instance();
\App\DB::$instance = \App\PostgresDbWrapper::withConnection(DB_NAME, $conn->connection);
