<?php

namespace ActiveRecord;

Config::initialize(function (Config $cfg) {
  $cfg->set_connections([
    'pgsql' => "pgsql://{$_ENV['DB_USER']}:{$_ENV['DB_PASS']}@{$_ENV['DB_HOST']}/{$_ENV['DB_NAME']}?charset=utf8",
    'failsafe' => 'sqlite://:memory:',
  ], 'pgsql');
});
Serialization::$DATETIME_FORMAT = 'c';
DateTime::$FORMATS['compat'] = 'c';
DateTime::$DEFAULT_FORMAT = 'compat';
Connection::$datetime_format = 'c';
