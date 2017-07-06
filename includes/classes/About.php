<?php

namespace App;

use \Elasticsearch\Common\Exceptions\NoNodesAvailableException as ElasticNoNodesAvailableException;

class About {
	static function getServerOS(){
		return PHP_OS === 'WINNT'
			? str_replace('Caption=','',CoreUtils::trim(shell_exec('wmic os get Caption /value')))
			: preg_replace(new RegExp('^[\s\S]*Description:\s+(\w+).*(\d+\.\d+(?:\.\d)?)\s+(\(\w+\))[\s\S]*$'),'$1 $2 $3',shell_exec('lsb_release -da'));
	}
	static function getServerSoftware(){
		return implode(' ',array_slice(preg_split('~[/ ]~',$_SERVER['SERVER_SOFTWARE']),0,2));
	}
	static function getPHPVersion(){
		return preg_replace('/^(\d+(?:\.\d+)*).*$/','$1',PHP_VERSION);
	}
	static function getPostgresVersion(){
		global $Database;
		return $Database->rawQuerySingle('SHOW server_version')['server_version'];
	}
	static function getElasticSearchVersion(){
		try {
			$info = CoreUtils::elasticClient()->info();
		}
		catch (ElasticNoNodesAvailableException $e){
			return;
		}
		return $info['version']['number'];
	}

    const INI_BOOL_MAP = [
        1 => true,
		'on' => true,
		'true' => true,
		0 => false,
		'off' => false,
		'false' => false,
    ];
	static function iniGet($key){
		$val = ini_get($key);
	    return self::INI_BOOL_MAP[strtolower($val)] ?? $val;
	}
}
