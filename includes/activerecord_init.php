<?php

ActiveRecord\Config::initialize(function ($cfg){
	$cfg->set_model_directory(INCPATH."classes/Models");
	$cfg->set_connections([
		'pgsql' => 'pgsql://'.DB_USER.':'.DB_PASS.'@'.DB_HOST.'/mlpvc-rr?charset=utf8',
	]);
	$cfg->set_default_connection('pgsql');
});
ActiveRecord\Serialization::$DATETIME_FORMAT = 'c';
ActiveRecord\DateTime::$FORMATS['compat'] = 'c';
ActiveRecord\DateTime::$DEFAULT_FORMAT = 'compat';
ActiveRecord\Connection::$datetime_format = 'c';
