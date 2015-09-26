<?php

	class CloudFlare {
		/**
		 * Checks if user's IP address is a genuine CloudFlare server IP
		 *
		 * @return bool
		 */
		static function CheckUserIP(){
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
			if (!file_exists($cachefile) || filemtime($cachefile) > ONE_HOUR*5){
				$data = file_get_contents('https://www.cloudflare.com/ips-v4');
				file_put_contents($cachefile, $data);
			}
			else $data = file_get_contents($cachefile);
			return explode('\n', $data);
		}

		/**
		 * Match an IP against a range in CIDR notation
		 *  from http://stackoverflow.com/a/14841828/1344955
		 *
		 * @param $ip IP address
		 * @param $range IP range to match $ip against
		 *
		 * @return bool True if IP is part of the network
		 */
		private function _cidr_match($ip, $range){
		    list($subnet, $mask) = explode('/', $range);

			return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet) ? true : false;
		}
	}
