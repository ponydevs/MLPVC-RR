<?php

namespace ActiveRecord;

Config::initialize(function (Config $cfg){
	$cfg->set_connections([
		'pgsql' => 'pgsql://'.DB_USER.':'.DB_PASS.'@'.DB_HOST.'/mlpvc-rr?charset=utf8',
		'failsafe' => 'sqlite://:memory:',
	], 'pgsql');
});
Serialization::$DATETIME_FORMAT = 'c';
DateTime::$FORMATS['compat'] = 'c';
DateTime::$DEFAULT_FORMAT = 'compat';
Connection::$datetime_format = 'c';
