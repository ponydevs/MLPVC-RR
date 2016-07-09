<?php

	class About {
		static function GetServerOS(){
			return PHP_OS === 'WINNT'
				? str_replace('Caption=','',CoreUtils::Trim(shell_exec('wmic os get Caption /value')))
				: regex_replace(new RegExp('^[\s\S]*Description:\s+(\w+).*(\d+\.\d+(?:\.\d)?)\s+(\(\w+\))[\s\S]*$'),'$1 $2 $3',shell_exec('lsb_release -da'));
		}
		static function GetServerSoftware(){
			return implode(' ',array_slice(preg_split('~[/ ]~',$_SERVER['SERVER_SOFTWARE']),0,2));
		}
		static function GetPHPVersion(){
			return preg_replace('/^(\d+(?:\.\d+)*).*$/','$1',PHP_VERSION);
		}
		static function GetPostgresVersion(){
			global $Database;
			return $Database->rawQuerySingle('SHOW server_version')['server_version'];
		}
	}
