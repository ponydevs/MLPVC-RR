<?php

	class CloudFlare {
		/**
		 * Checks if user's IP address is a genuine CloudFlare server IP
		 *
		 * @return bool True if IP is genuine
		 */
		static function CheckUserIP(){
			return self::CheckIP($_SERVER['REMOTE_ADDR']);
		}

		/**
		 * Checks if user's IP address is a genuine CloudFlare server IP
		 *
		 * @param string $ip Address to check
		 *
		 * @return bool True if IP is genuine
		 */
		static function CheckIP($ip){
			foreach (self::_getCFIpRanges() as $range){
				if (self::_cidr_match($_SERVER['REMOTE_ADDR'], $range))
					return true;
			}
			return false;
		}

		/**
		 * Cache & retrieve CloudFlare IPv4 list
		 *
		 * @return array List of IP ranges
		 */
		private function _getCFIpRanges(){
			$cachefile = APPATH.'../cf-ips.txt';
			$fileExists = file_exists($cachefile);
			if (!$fileExists || filemtime($cachefile)+Time::$IN_SECONDS['hour']*5 < time()){
				$data = @file_get_contents('https://www.cloudflare.com/ips-v4');
				if (empty($data)){
					error_log('CloudFlare IPv4 data is empty!');
					if (!$fileExists)
						file_put_contents($cachefile, '');
					$data = file_get_contents($cachefile);
				}
				else file_put_contents($cachefile, $data);
			}
			else $data = file_get_contents($cachefile);
			$data = trim($data);
			return strlen($data) > 0 ? explode("\n", $data) : array();
		}

		/**
		 * Match an IP against a range in CIDR notation
		 *  from http://php.net/manual/en/ref.network.php#74656
		 *
		 * @param string $IP Address
		 * @param string $CIDR Range to match $IP against
		 *
		 * @return bool True if IP is part of the network
		 */
		private function _cidr_match($IP, $CIDR){
		    list ($net, $mask) = explode("/", $CIDR);

		    $ip_net = ip2long ($net);
		    $ip_mask = ~((1 << (32 - $mask)) - 1);

		    $ip_ip = ip2long ($IP);
		    $ip_ip_net = $ip_ip & $ip_mask;

		    return ($ip_ip_net == $ip_net);
		}
	}
