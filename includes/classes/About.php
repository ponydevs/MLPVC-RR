<?php

namespace App;

use \Elasticsearch\Common\Exceptions\NoNodesAvailableException as ElasticNoNodesAvailableException;

class About {
	public static function getServerOS(){
		return PHP_OS === 'WINNT'
			? str_replace(new RegExp('^[\s\S]*?ProductName\s+REG_SZ\s+([^\r\n]+)[\s\S]*$'),'$1',CoreUtils::trim(shell_exec('reg query "HKLM\Software\Microsoft\Windows NT\CurrentVersion" /v "ProductName"')))
			: CoreUtils::trim(preg_replace(new RegExp('^Distributor ID:\s+([^\n]+)\nRelease:\s+([^\n]+)\nCodename:\s+([^\n]+)\n?$'),'$1 $2 ($3)',shell_exec('lsb_release -irc')));
	}
	public static function getServerSoftware(){
		return implode(' ', \array_slice(preg_split('~[/ ]~', $_SERVER['SERVER_SOFTWARE']),0,2));
	}
	public static function getPHPVersion(){
		return preg_replace('/^(\d+(?:\.\d+)*).*$/','$1',PHP_VERSION);
	}
	public static function getPostgresVersion(){

		return DB::$instance->querySingle('SHOW server_version')['server_version'];
	}
	public static function getElasticSearchVersion(){
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
	public static function iniGet($key){
		$val = ini_get($key);
	    return self::INI_BOOL_MAP[strtolower($val)] ?? $val;
	}
}
