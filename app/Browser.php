<?php

namespace App;

use App\CoreUtils;

/**
 * File: Browser.php
 * Author: Chris Schuld (http://chrisschuld.com/)
 * Last Modified: July 4th, 2014
 *
 * @version 1.9
 * @package PegasusPHP
 * @url https://github.com/cbschuld/Browser.php
 * Copyright (C) 2008-2010 Chris Schuld  (chris@chrisschuld.com)
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details at:
 * http://www.gnu.org/copyleft/gpl.html
 * Typical Usage:
 *   $browser = new Browser();
 *   if( $browser->getBrowser() == Browser::BROWSER_FIREFOX && $browser->getVersion() >= 2 ) {
 *    echo 'You have FireFox version 2 or greater';
 *   }
 * User Agents Sampled from: http://www.useragentstring.com/
 * This implementation is based on the original work from Gary White
 * http://apptools.com/phptools/browser/
 * IE Mobile browser & Windows Phone platform check implemented by SeinopSys
 * http://github.com/SeinopSys
 */
class Browser {
	private $_agent, $_platform, $_browserName, $_version;

	public const BROWSER_UNKNOWN = 'Unknown Browser';
	public const VERSION_UNKNOWN = 'v?';

	public const BROWSER_OPERA = 'Opera'; // http://www.opera.com/
	public const BROWSER_OPERA_MINI = 'Opera Mini'; // http://www.opera.com/mini/
	public const BROWSER_IE = 'Internet Explorer'; // http://www.microsoft.com/ie/
	public const BROWSER_EDGE = 'Edge'; // https://www.microsoft.com/en-us/windows/microsoft-edge
	public const BROWSER_IEMOBILE = 'IE Mobile'; // http://en.wikipedia.org/wiki/Internet_Explorer_Mobile
	public const BROWSER_VIVALDI = 'Vivaldi'; // http://vivaldi.com/
	public const BROWSER_OMNIWEB = 'OmniWeb'; // http://www.omnigroup.com/applications/omniweb/
	public const BROWSER_FIREFOX = 'Firefox'; // http://www.mozilla.com/en-US/firefox/firefox.html
	public const BROWSER_ICEWEASEL = 'Iceweasel'; // http://www.geticeweasel.org/
	public const BROWSER_AMAYA = 'Amaya'; // http://www.w3.org/Amaya/
	public const BROWSER_LYNX = 'Lynx'; // http://en.wikipedia.org/wiki/Lynx
	public const BROWSER_SAFARI = 'Safari'; // http://apple.com
	public const BROWSER_CHROME = 'Chrome'; // http://www.google.com/chrome
	public const BROWSER_ANDROID = 'Android'; // http://www.android.com/
	public const BROWSER_W3C_VALIDATOR = 'W3C Validator'; // http://validator.w3.org/
	public const BROWSER_BLACKBERRY = 'BlackBerry'; // http://www.blackberry.com/
	public const BROWSER_ICECAT = 'IceCat'; // http://en.wikipedia.org/wiki/GNU_IceCat
	public const BROWSER_PALEMOON = 'Pale Moon'; // https://www.palemoon.org/
	public const BROWSER_MAXTHON = 'Maxthon'; // http://maxthon.com/
	public const BROWSER_FFFOCUS = 'Firefox Focus'; // https://www.mozilla.org/en-US/firefox/focus/
	public const BROWSER_YANDEX = 'Yandex Browser'; // https://browser.yandex.com/
	public const BROWSER_SILK = 'Silk';
	public const BROWSER_SAMSUNG_INET = 'Samsung Internet'; // http://www.samsung.com/global/galaxy/apps/samsung-internet/

	public const PLATFORM_UNKNOWN = 'Unknown Platform';
	public const PLATFORM_WINDOWS = 'Windows';
	public const PLATFORM_WINPHONE = 'Windows Phone';
	public const PLATFORM_WINDOWS_CE = 'Windows CE';
	public const PLATFORM_OSX = 'Mac OSX';
	public const PLATFORM_LINUX = 'Linux';
	public const PLATFORM_IOS = 'iOS';
	public const PLATFORM_BLACKBERRY = 'BlackBerry';
	public const PLATFORM_FREEBSD = 'FreeBSD';
	public const PLATFORM_OPENBSD = 'OpenBSD';
	public const PLATFORM_NETBSD = 'NetBSD';
	public const PLATFORM_ANDROID = 'Android';
	public const PLATFORM_CHROMEOS = 'Chrome OS';
	public const PLATFORM_KINDLE = 'Amazon Kindle';

	public function __construct($userAgent = null) {
		$this->reset();
		if (!empty($userAgent)){
			$this->setUserAgent($userAgent);
		}
		else $this->determine();
	}

	/**
	 * Reset all properties
	 */
	public function reset():void {
		$this->_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$this->_browserName = self::BROWSER_UNKNOWN;
		$this->_version = self::VERSION_UNKNOWN;
		$this->_platform = self::PLATFORM_UNKNOWN;
	}

	/**
	 * Check to see if the specific browser is valid
	 *
	 * @param string $browserName
	 *
	 * @return bool True if the browser is the specified browser
	 */
	public function isBrowser($browserName):bool {
		return strcasecmp($this->_browserName, CoreUtils::trim($browserName)) === 0;
	}

	/**
	 * The name of the browser.  All return types are from the class contents
	 *
	 * @return string Name of the browser
	 */
	public function getBrowser():string {
		return $this->_browserName;
	}

	/**
	 * Set the name of the browser
	 *
	 * @param $browser string The name of the Browser
	 */
	public function setBrowser($browser):void {
		$this->_browserName = $browser;
	}

	/**
	 * The name of the platform.  All return types are from the class contents
	 *
	 * @return string Name of the browser
	 */
	public function getPlatform():string {
		return $this->_platform;
	}

	/**
	 * Set the name of the platform
	 *
	 * @param string $platform The name of the Platform
	 */
	public function setPlatform($platform):void {
		$this->_platform = $platform;
	}

	/**
	 * The version of the browser.
	 *
	 * @return string Version of the browser (will only contain alpha-numeric characters and a period)
	 */
	public function getVersion():string {
		return $this->_version;
	}

	/**
	 * Set the version of the browser
	 *
	 * @param string $version The version of the Browser
	 */
	public function setVersion($version):void {
		$this->_version = preg_replace('/[^0-9,.,a-z,A-Z-]/', '', $version);
	}

	/**
	 * Get the user agent value in use to determine the browser
	 *
	 * @return string The user agent from the HTTP header
	 */
	public function getUserAgent():string {
		return $this->_agent;
	}

	/**
	 * Set the user agent value (the construction will use the HTTP header value - this will overwrite it)
	 *
	 * @param string $agent_string The value for the User Agent
	 */
	public function setUserAgent($agent_string):void {
		$this->reset();
		$this->_agent = $agent_string;
		$this->determine();
	}

	/**
	 * Protected routine to calculate and determine what the browser is in use (including platform)
	 */
	protected function determine():void {
		$this->checkPlatform();
		$this->checkBrowsers();
	}

	/**
	 * Protected routine to determine the browser type
	 *
	 * @return boolean True if the browser was detected otherwise false
	 */
	protected function checkBrowsers():bool {
		return (
			$this->checkBrowserEdge() ||
			$this->checkBrowserInternetExplorer() ||
			$this->checkBrowserVivaldi() ||
			$this->checkBrowserSamsungInternet() ||
			$this->checkBrowserOpera() ||
			$this->checkBrowserPaleMoon() ||
			$this->checkBrowserFirefoxFocus() ||
			$this->checkBrowserFirefox() ||
			$this->checkBrowserMaxthon() ||
			$this->checkBrowserYandex() ||
			$this->checkBrowserSilk() ||
			$this->checkBrowserChrome() ||
			$this->checkBrowserOmniWeb() ||
			$this->checkBrowserSafari() ||

			// common mobile
			$this->checkBrowserAndroid() ||
			$this->checkBrowserAppleMobile() ||
			$this->checkBrowserBlackBerry() ||

			// everyone else
			$this->checkBrowserAmaya() ||
			$this->checkBrowserLynx() ||
			$this->checkBrowserIceCat() ||
			$this->checkBrowserIceweasel() ||
			$this->checkBrowserW3CValidator()
		);
	}

	/**
	 * Determine if the user is using a BlackBerry (last updated 1.7)
	 *
	 * @return boolean True if the browser is the BlackBerry browser otherwise false
	 */
	protected function checkBrowserBlackBerry():bool {
		if (CoreUtils::contains($this->_agent, 'blackberry', false)){
			$res = explode('/', stristr($this->_agent, 'BlackBerry'));
			if (isset($res[1])){
				$aversion = explode(' ', $res[1]);
				$this->setVersion($aversion[0]);
				$this->_browserName = self::BROWSER_BLACKBERRY;

				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if the browser is the W3C Validator or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is the W3C Validator otherwise false
	 */
	protected function checkBrowserW3CValidator():bool {
		if (CoreUtils::contains($this->_agent, 'W3C-checklink', false)){
			$res = explode('/', stristr($this->_agent, 'W3C-checklink'));
			if (isset($res[1])){
				$aversion = explode(' ', $res[1]);
				$this->setVersion($aversion[0]);
				$this->_browserName = self::BROWSER_W3C_VALIDATOR;

				return true;
			}
		}
		else if (CoreUtils::contains($this->_agent, 'W3C_Validator', false)){
			// Some of the Validator versions do not delineate w/ a slash - add it back in
			$ua = str_replace('W3C_Validator ', 'W3C_Validator/', $this->_agent);
			$res = explode('/', stristr($ua, 'W3C_Validator'));
			if (isset($res[1])){
				$aversion = explode(' ', $res[1]);
				$this->setVersion($aversion[0]);
				$this->_browserName = self::BROWSER_W3C_VALIDATOR;

				return true;
			}
		}
		else if (CoreUtils::contains($this->_agent, 'W3C-mobileOK', false)){
			$this->_browserName = self::BROWSER_W3C_VALIDATOR;

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is Internet Explorer or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Internet Explorer otherwise false
	 */
	protected function checkBrowserInternetExplorer():bool {
		// Test for IE11
		if (CoreUtils::contains($this->_agent, 'Trident/7.0;', false) && CoreUtils::contains($this->_agent, 'rv:11.0;', true)){
			if (CoreUtils::contains($this->_agent, 'IEMobile', false)){
				$this->setPlatform(self::PLATFORM_WINPHONE);
				$this->setBrowser(self::BROWSER_IEMOBILE);
			}
			else $this->setBrowser(self::BROWSER_IE);
			$this->setVersion('11.0');

			return true;
		}
		// Test for v1 - v1.5 IE
		if (CoreUtils::contains($this->_agent, 'microsoft internet explorer', false)){
			$this->setBrowser(self::BROWSER_IE);
			$this->setVersion('1.0');
			$res = strstr($this->_agent, '/');
			if (preg_match('/308|425|426|474|0b1/i', $res)){
				$this->setVersion('1.5');
			}

			return true;
		}
		// Test for versions > 1.5
		if (CoreUtils::contains($this->_agent, 'msie', false) && !CoreUtils::contains($this->_agent, 'opera', false)){
			/** @noinspection SuspiciousAssignmentsInspection */
			$res = explode(' ', stristr(str_replace(';', '; ', $this->_agent), 'msie'));
			if (isset($res[1])){
				$this->setBrowser(self::BROWSER_IE);
				$this->setVersion(str_replace(['(', ')', ';'], '', $res[1]));
				if (CoreUtils::contains($this->_agent, 'IEMobile', false)){
					$this->setBrowser(self::BROWSER_IEMOBILE);
				}

				return true;
			}
		} // Test for versions > IE 10
		else if (CoreUtils::contains($this->_agent, 'trident', false)){
			$this->setBrowser(self::BROWSER_IE);
			$result = explode('rv:', $this->_agent);
			if (isset($result[1])){
				$this->setVersion(preg_replace('/[^0-9.]+/', '', $result[1]));
				$this->_agent = str_replace(['Mozilla', 'Gecko'], 'MSIE', $this->_agent);
			}
		} // Test for Pocket IE
		else if (($mspie = CoreUtils::contains($this->_agent, 'mspie', false)) || CoreUtils::contains($this->_agent, 'pocket', false)){
			$res = explode(' ', stristr($this->_agent, 'mspie'));
			if (isset($res[1])){
				$this->setPlatform(self::PLATFORM_WINDOWS_CE);
				$this->setBrowser(self::BROWSER_IEMOBILE);

				if ($mspie){
					$this->setVersion($res[1]);
				}
				else {
					$aversion = explode('/', $this->_agent);
					if (isset($aversion[1])){
						$this->setVersion($aversion[1]);
					}
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if the browser is Opera or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Opera otherwise false
	 */
	protected function checkBrowserOpera():bool {
		if (CoreUtils::contains($this->_agent, 'opera mini', false)){
			$resultant = stristr($this->_agent, 'opera mini');
			if (preg_match('/\//', $resultant)){
				$res = explode('/', $resultant);
				if (isset($res[1])){
					$aversion = explode(' ', $res[1]);
					$this->setVersion($aversion[0]);
				}
			}
			else {
				$aversion = explode(' ', stristr($resultant, 'opera mini'));
				if (isset($aversion[1])){
					$this->setVersion($aversion[1]);
				}
			}
			$this->_browserName = self::BROWSER_OPERA_MINI;

			return true;
		}
		if (CoreUtils::contains($this->_agent, 'opera', false)){
			$resultant = stristr($this->_agent, 'opera');
			if (preg_match('/Version\/(1*.*)$/', $resultant, $matches)){
				$this->setVersion($matches[1]);
			}
			else if (preg_match('/\//', $resultant)){
				$res = explode('/', str_replace('(', ' ', $resultant));
				if (isset($res[1])){
					$aversion = explode(' ', $res[1]);
					$this->setVersion($aversion[0]);
				}
			}
			else {
				$aversion = explode(' ', stristr($resultant, 'opera'));
				$this->setVersion($aversion[1] ?? '');
			}
			$this->_browserName = self::BROWSER_OPERA;

			return true;
		}
		if (CoreUtils::contains($this->_agent, 'OPR', false) && !CoreUtils::contains($this->_agent, 'Chrome', false)){
			$resultant = stristr($this->_agent, 'OPR');
			if (preg_match('/\//', $resultant)){
				$res = explode('/', str_replace('(', ' ', $resultant));
				if (isset($res[1])){
					$aversion = explode(' ', $res[1]);
					$this->setVersion($aversion[0]);
				}
			}
			$this->_browserName = self::BROWSER_OPERA;

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is Chrome or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Chrome otherwise false
	 */
	protected function checkBrowserChrome():bool {
		if (CoreUtils::contains($this->_agent, 'Chrome', false)){
			$res = explode('/', stristr($this->_agent, 'Chrome'));
			if (isset($res[1])){
				$aversion = explode(' ', $res[1]);
				$this->setVersion($aversion[0]);
				$this->setBrowser(self::BROWSER_CHROME);

				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if the browser is Maxthon or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Maxthon otherwise false
	 */
	protected function checkBrowserMaxthon():bool {
		if (preg_match('~\bMaxthon\/([\d.]+)~', $this->_agent, $match)){
			$this->setVersion($match[1]);
			$this->setBrowser(self::BROWSER_MAXTHON);

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is Yandex Browser or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Yandex Browser otherwise false
	 */
	protected function checkBrowserYandex():bool {
		if (preg_match('~\bYaBrowser\/([\d.]+)~', $this->_agent, $match)){
			$this->setVersion($match[1]);
			$this->setBrowser(self::BROWSER_YANDEX);

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is Silk or not
	 *
	 * @return boolean True if the browser is Silk otherwise false
	 */
	protected function checkBrowserSilk():bool {
		if (preg_match('~\bSilk\/([\d.]+)~', $this->_agent, $match)){
			$this->setVersion($match[1]);
			$this->setBrowser(self::BROWSER_SILK);

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is OmniWeb or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is OmniWeb otherwise false
	 */
	protected function checkBrowserOmniWeb():bool {
		if (CoreUtils::contains($this->_agent, 'omniweb', false)){
			$res = explode('/', stristr($this->_agent, 'omniweb'));
			$aversion = explode(' ', $res[1] ?? '');
			$this->setVersion($aversion[0]);
			$this->setBrowser(self::BROWSER_OMNIWEB);

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is Ice Cat or not (http://en.wikipedia.org/wiki/GNU_IceCat) (last updated 1.7)
	 *
	 * @return boolean True if the browser is Ice Cat otherwise false
	 */
	protected function checkBrowserIceCat():bool {
		if (CoreUtils::contains($this->_agent, 'Mozilla', false) && preg_match('/IceCat\/([^ ]*)/i', $this->_agent, $matches)){
			$this->setVersion($matches[1]);
			$this->setBrowser(self::BROWSER_ICECAT);

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is Firefox or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Firefox otherwise false
	 */
	protected function checkBrowserFirefox():bool {
		if (!CoreUtils::contains($this->_agent, 'safari', false)){
			if (preg_match("/Firefox[\/ \(]([^ ;\)]+)/i", $this->_agent, $matches)){
				$this->setVersion($matches[1]);
				$this->setBrowser(self::BROWSER_FIREFOX);

				return true;
			}
			if (preg_match('/Firefox$/i', $this->_agent, $matches)){
				$this->setVersion('');
				$this->setBrowser(self::BROWSER_FIREFOX);

				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if the browser is Firefox Focus or not
	 *
	 * @return boolean True if the browser is Firefox Focus otherwise false
	 */
	protected function checkBrowserFirefoxFocus():bool {
		if (preg_match("~\bFocus\/([\d.]+)~", $this->_agent, $matches)){
			$this->setVersion($matches[1]);
			$this->setBrowser(self::BROWSER_FFFOCUS);

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is Pale Moon
	 *
	 * @return boolean True if the browser is Pale Moon otherwise false
	 */
	protected function checkBrowserPaleMoon():bool {
		if (preg_match("~PaleMoon/([\d.]+)~", $this->_agent, $matches)){
			$this->setVersion($matches[1]);
			$this->setBrowser(self::BROWSER_PALEMOON);

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is Firefox or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Firefox otherwise false
	 */
	protected function checkBrowserIceweasel():bool {
		if (CoreUtils::contains($this->_agent, 'Iceweasel', false)){
			$res = explode('/', stristr($this->_agent, 'Iceweasel'));
			if (isset($res[1])){
				$aversion = explode(' ', $res[1]);
				$this->setVersion($aversion[0]);
				$this->setBrowser(self::BROWSER_ICEWEASEL);

				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if the browser is Lynx or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Lynx otherwise false
	 */
	protected function checkBrowserLynx():bool {
		if (CoreUtils::contains($this->_agent, 'lynx', false)){
			$res = explode('/', stristr($this->_agent, 'Lynx'));
			$aversion = explode(' ', $res[1] ?? '');
			$this->setVersion($aversion[0]);
			$this->setBrowser(self::BROWSER_LYNX);

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is Amaya or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Amaya otherwise false
	 */
	protected function checkBrowserAmaya():bool {
		if (CoreUtils::contains($this->_agent, 'amaya', false)){
			$res = explode('/', stristr($this->_agent, 'Amaya'));
			if (isset($res[1])){
				$aversion = explode(' ', $res[1]);
				$this->setVersion($aversion[0]);
				$this->setBrowser(self::BROWSER_AMAYA);

				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if the browser is Safari or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Safari otherwise false
	 */
	protected function checkBrowserSafari():bool {
		if (CoreUtils::contains($this->_agent, 'Safari', false)
			&& !CoreUtils::contains($this->_agent, 'iPhone', false)
			&& !CoreUtils::contains($this->_agent, 'iPod', false)
		){
			if (preg_match('~\bVersion\/([\d.]+)~', $this->_agent, $match)){
				$this->setVersion($match[1]);
			}
			else {
				$this->setVersion(self::VERSION_UNKNOWN);
			}
			$this->setBrowser(self::BROWSER_SAFARI);

			return true;
		}

		return false;
	}

	/**
	 * Detect Version for the Safari browser on iOS devices
	 *
	 * @return boolean True if it detects the version correctly otherwise false
	 */
	protected function getSafariVersionOnIos():bool {
		$res = explode('/', stristr($this->_agent, 'Version'));
		if (isset($res[1])){
			$aversion = explode(' ', $res[1]);
			$this->setVersion($aversion[0]);

			return true;
		}

		return false;
	}

	/**
	 * Detect Version for the Chrome browser on iOS devices
	 *
	 * @return boolean True if it detects the version correctly otherwise false
	 */
	protected function getChromeVersionOnIos():bool {
		$res = explode('/', stristr($this->_agent, 'CriOS'));
		if (isset($res[1])){
			$aversion = explode(' ', $res[1]);
			$this->setVersion($aversion[0]);
			$this->setBrowser(self::BROWSER_CHROME);

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is iPhone or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is iPhone otherwise false
	 */
	protected function checkBrowserAppleMobile():bool {
		$version = [];
		if (preg_match('~CriOS/([\d\.]+)~', $this->_agent, $version)){
			$this->setVersion($version[1]);
			$this->setBrowser(self::BROWSER_CHROME);

			return true;
		}
		if (preg_match('~Safari/([\d\.]+)~', $this->_agent, $version)){
			$this->setVersion($version[1]);
			$this->setBrowser(self::BROWSER_SAFARI);

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is Android or not (last updated 1.7)
	 *
	 * @return boolean True if the browser is Android otherwise false
	 */
	protected function checkBrowserAndroid():bool {
		if (CoreUtils::contains($this->_agent, 'Android', false)){
			$res = explode(' ', stristr($this->_agent, 'Android'));
			if (isset($res[1])){
				$aversion = explode(' ', $res[1]);
				$this->setVersion($aversion[0]);
			}
			else {
				$this->setVersion(self::VERSION_UNKNOWN);
			}
			$this->setBrowser(self::BROWSER_ANDROID);

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is Edge or not (last updated 1.7)
	 * https://github.com/cbschuld/Browser.php/pull/43
	 *
	 * @return boolean True if the browser is Edge otherwise false
	 */
	protected function checkBrowserEdge():bool {
		if (CoreUtils::contains($this->_agent, 'Edge', false)){
			$res = explode('/', stristr($this->_agent, 'Edge'));
			if (isset($res[1])){
				$aversion = explode(' ', $res[1]);
				$this->setVersion($aversion[0]);
				$this->setBrowser(self::BROWSER_EDGE);

				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if the browser is Vivaldi or not
	 *
	 * @return boolean True if the browser is Vivaldi otherwise false
	 */
	protected function checkBrowserVivaldi():bool {
		if (CoreUtils::contains($this->_agent, 'Vivaldi', false)){
			$_match = [];
			if (preg_match('/Vivaldi\/([\d.]+)/', $this->_agent, $_match)){
				$this->setVersion($_match[1]);
			}
			else $this->setVersion(self::VERSION_UNKNOWN);
			$this->setBrowser(self::BROWSER_VIVALDI);

			return true;
		}

		return false;
	}

	/**
	 * Determine if the browser is Samsung Internet or not
	 *
	 * @return boolean True if the browser is Samsung Internet otherwise false
	 */
	protected function checkBrowserSamsungInternet():bool {
		if (preg_match('/SamsungBrowser\/([\d.]+)/', $this->_agent, $_match)){
			$this->setVersion($_match[1]);
			$this->setBrowser(self::BROWSER_SAMSUNG_INET);

			return true;
		}

		return false;
	}

	/**
	 * Determine the user's platform (last updated 1.7)
	 */
	protected function checkPlatform():void {
		if (preg_match('/Windows Phone/', $this->_agent)){
			$this->_platform = self::PLATFORM_WINPHONE;
		}
		else if (CoreUtils::contains($this->_agent, 'windows', false)){
			$this->_platform = self::PLATFORM_WINDOWS;
		}
		else if (preg_match('/(iPod|iPad|iPhone)/', $this->_agent)){
			$this->_platform = self::PLATFORM_IOS;
		}
		else if (CoreUtils::contains($this->_agent, 'mac', false)){
			$this->_platform = self::PLATFORM_OSX;
		}
		else if (CoreUtils::contains($this->_agent, 'android', false)){
			if (CoreUtils::contains($this->_agent, 'KFFOWI', false)){
				$this->_platform = self::PLATFORM_KINDLE;
			}
			else $this->_platform = self::PLATFORM_ANDROID;
		}
		else if (CoreUtils::contains($this->_agent, 'linux', false)){
			$this->_platform = self::PLATFORM_LINUX;
		}
		else if (CoreUtils::contains($this->_agent, 'BlackBerry', false)){
			$this->_platform = self::PLATFORM_BLACKBERRY;
		}
		else if (CoreUtils::contains($this->_agent, 'FreeBSD', false)){
			$this->_platform = self::PLATFORM_FREEBSD;
		}
		else if (CoreUtils::contains($this->_agent, 'OpenBSD', false)){
			$this->_platform = self::PLATFORM_OPENBSD;
		}
		else if (CoreUtils::contains($this->_agent, 'NetBSD', false)){
			$this->_platform = self::PLATFORM_NETBSD;
		}
		else if (CoreUtils::contains($this->_agent, 'CrOS', false)){
			$this->_platform = self::PLATFORM_CHROMEOS;
		}
		else if (CoreUtils::contains($this->_agent, 'win', false)){
			$this->_platform = self::PLATFORM_WINDOWS;
		}
	}
}
