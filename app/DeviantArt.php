<?php

namespace App;

use App\Exceptions\UnsupportedProviderException;
use App\Models\CachedDeviation;
use App\Models\Session;
use App\Models\User;
use App\Exceptions\CURLRequestException;
use RuntimeException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use App\Exceptions\JSONParseException;
use SeinopSys\OAuth2\Client\Provider\DeviantArtProvider;

class DeviantArt {
	// oAuth Error Response Messages \\
	public const OAUTH_RESPONSE = [
		'invalid_request' => 'The authorization request was not properly formatted.',
		'unsupported_response_type' => 'The authorization server does not support obtaining an authorization code using this method.',
		'unauthorized_client' => 'The authorization process did not complete. Please try again.',
		'invalid_scope' => 'The requested scope is invalid, unknown, or malformed.',
		'server_error' => "There seems to be an issue on DeviantArt's end. Try again later.",
		'temporarily_unavailable' => "There's an issue on DeviantArt's end. Try again later.",
		'user_banned' => 'You were banned on our website by a staff member.',
		'access_denied' => 'You decided not to allow the site to verify your identity',
	];

	/** @var DeviantArtProvider */
	private static $_OAuthProviderInstance;
	/** @return DeviantArtProvider */
	public static function OAuthProviderInstance(){
		if (self::$_OAuthProviderInstance !== null)
			return self::$_OAuthProviderInstance;

		return self::$_OAuthProviderInstance = new DeviantArtProvider([
			'clientId' => $_ENV['DA_CLIENT'],
			'clientSecret' => $_ENV['DA_SECRET'],
			'redirectUri' => OAUTH_REDIRECT_URI,
		]);
	}

	/**
	 * Makes authenticated requests to the DeviantArt API
	 *
	 * @param string            $endpoint
	 * @param null|array        $postdata
	 * @param null|string|false $token    Set to false if no token is required
	 *
	 * @return array
	 */
	public static function request($endpoint, $token = null, $postdata = null){
		global $http_response_header;

		$requestHeaders = ['Accept-Encoding: gzip', 'User-Agent: MLPVC-RR @ '.GITHUB_URL];
		if ($token === null && Auth::$signed_in)
			$token = Auth::$session->access;
		if (!empty($token))
			$requestHeaders[] = "Authorization: Bearer $token";
		else if ($token !== false)
			return null;

		$requestURI  = preg_match(new RegExp('^https?://'), $endpoint) ? $endpoint : "https://www.deviantart.com/api/v1/oauth2/$endpoint";

		$r = curl_init($requestURI);
		$curl_opt = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $requestHeaders,
			CURLOPT_HEADER => true,
			CURLOPT_BINARYTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
		];
		if (!empty($postdata)){
			$query = [];
			foreach($postdata as $k => $v) $query[] = urlencode($k).'='.urlencode($v);
			$curl_opt[CURLOPT_POST] = \count($postdata);
			$curl_opt[CURLOPT_POSTFIELDS] = implode('&', $query);
		}
		curl_setopt_array($r, $curl_opt);

		$response = curl_exec($r);
		$responseCode = curl_getinfo($r, CURLINFO_HTTP_CODE);
		$headerSize = curl_getinfo($r, CURLINFO_HEADER_SIZE);

		$responseHeaders = rtrim(mb_substr($response, 0, $headerSize));
		$response = mb_substr($response, $headerSize);
		$http_response_header = array_map('rtrim',explode("\n",$responseHeaders));
		$curlError = curl_error($r);
		curl_close($r);

		if ($responseCode < 200 || $responseCode >= 300)
			throw new CURLRequestException(rtrim("cURL fail for URL \"$requestURI\"",' ;'), $responseCode, $curlError);

		if (empty($response)){
			CoreUtils::error_log(__METHOD__.": Empty response (HTTP $responseCode)\nURI: $requestURI\nResponse headers:\n$http_response_header\ncURL error: $curlError");
			return null;
		}
		if (preg_match(new RegExp('Content-Encoding:\s?gzip'), $responseHeaders))
			$response = gzdecode($response);
		return JSON::decode($response);
	}

	/**
	 * Caches information about a deviation in the 'cached_deviations' table
	 * Returns null on failure
	 *
	 * @param string      $ID
	 * @param null|string $type
	 *
	 * @return CachedDeviation|null
	 */
	public static function getCachedDeviation($ID, $type = 'fav.me'){
		if ($type === 'sta.sh')
			$ID = self::nomralizeStashID($ID);

		/** @var $Deviation CachedDeviation */
		$Deviation = CachedDeviation::find_by_id_and_provider($ID, $type);
		$localDataMissing = $Deviation == null;

		$cacheExpired = true;
		if (!$localDataMissing && $Deviation->updated_on !== null)
			$cacheExpired = $Deviation->updated_on->getTimestamp()+(Time::IN_SECONDS['hour']*12) < time();

		if ($cacheExpired){
			try {
				$json = self::oEmbed($ID, $type);
				if (empty($json))
					throw new \RuntimeException('oEmbed JSON data is empty');
			}
			catch (\Exception $e){
				if ($Deviation !== null)
					$Deviation->update_attributes(['updated_on' => date('c', time()+Time::IN_SECONDS['minute'] )]);

				CoreUtils::error_log("Saving local data for $ID@$type failed: ".$e->getMessage()."\n".$e->getTraceAsString());

				if ($e->getCode() === 404){
					if ($Deviation !== null)
						$Deviation->delete();
					$Deviation = null;
				}

				return $Deviation;
			}

			$insert = [
				'id' => $ID,
				'provider' => $type,
				'title' => preg_replace(new RegExp('\\\\\''),"'",$json['title']),
				'preview' => isset($json['thumbnail_url']) ? URL::makeHttps($json['thumbnail_url']) : null,
				'fullsize' => isset($json['fullsize_url'])
					? URL::makeHttps($json['fullsize_url'])
					: (
						isset($json['url']) && !preg_match(new RegExp('-\d+$'), $json['url'])
						? URL::makeHttps($json['url'])
						: null
					),
				'author' => $json['author_name'],
				'updated_on' => date('c'),
			];

			switch ($json['type']){
				case 'photo':
					$insert['type'] = $json['imagetype'];
				break;
				case 'rich':
					if (isset($json['html'])){
						$DATA_EXTENSION_REGEX = new RegExp('^[\s\S]*\sdata-extension="([a-z\d]+?)"[\s\S]*$');
						if ($DATA_EXTENSION_REGEX->match($json['html']))
							$insert['type'] = $DATA_EXTENSION_REGEX->replace('$1',$json['html']);

						$H2_EXTENSION_REGEX = new RegExp('^[\s\S]*<h2>([A-Z\d]+?)</h2>[\s\S]*$');
						if ($H2_EXTENSION_REGEX->match($json['html']))
							$insert['type'] = strtolower($H2_EXTENSION_REGEX->replace('$1',$json['html']));
					}
				break;
				case 'link':
					$stashpage = HTTP::legitimateRequest("http://$type/$ID");
					if (!empty($stashpage['response'])){
						preg_match(new RegExp('<span class="text">([A-Za-z\d]+) download,'), $stashpage['response'], $matches);
						if (!empty($matches[1]))
							$insert['type'] = strtolower($matches[1]);
					}
					if (empty($insert['type']))
						$insert['type'] = $json['imagetype'];
				break;
			}

			if ($insert['fullsize'] === null || !preg_match(Regexes::$fullsize_match, $insert['fullsize'])){
				$fullsize_attempt = self::getDownloadURL($ID, $type);
				if (\is_string($fullsize_attempt))
					$insert['fullsize'] = $fullsize_attempt;
			}

			if (empty($Deviation))
				$Deviation = CachedDeviation::find_by_id_and_provider($ID, $type);

			if (empty($Deviation))
				$Deviation = CachedDeviation::create($insert);
			else $Deviation->update_attributes($insert);
		}
		else if (!empty($Deviation->updated_on)){
			$Deviation->updated_on = date('c', strtotime($Deviation->updated_on));
			$Deviation->save();
		}

		return $Deviation;
	}

	/**
	 * Makes a call to the dA oEmbed API to get public info about an artwork
	 * $type defaults to 'fav.me'
	 *
	 * @param string      $ID
	 * @param null|string $type
	 *
	 * @return array
	 */
	public static function oEmbed($ID, $type = null){
		if (empty($type) || !\in_array($type, ['fav.me', 'sta.sh'], true))
			$type = 'fav.me';

		if ($type === 'sta.sh')
			$ID = self::nomralizeStashID($ID);
		try {
			$data = self::request('https://backend.deviantart.com/oembed?url='.urlencode("http://$type/$ID"),false);
		}
		catch (CURLRequestException $e){
			if ($e->getCode() === 404)
				throw new \RuntimeException('Image not found. The URL may be incorrect or the image has been deleted.', 404);
			else throw new \RuntimeException("Image could not be retrieved; ".$e->getMessage(), $e->getCode());
		}

		return $data;
	}

	private static function _authRequest(bool $refresh, string $code):?User {
		global $http_response_header;

		$provider = self::OAuthProviderInstance();
		try {
			if ($refresh)
				$accessToken = $provider->getAccessToken('refresh_token', ['refresh_token' => $code]);
			else $accessToken = $provider->getAccessToken('authorization_code', ['code' => $code, 'scope' => ['user','browse']]);
		}
		catch (\TypeError $e){
			$trace = $e->getTrace();
			if (!empty($trace[0]['function']) && $trace[0]['function'] === 'prepareAccessTokenResponse' && !empty($trace[0]['args']) && CoreUtils::contains($trace[0]['args'][0], 'DeviantArt: 403 Forbidden')){
				$_GET['error'] = 'server_error';
				$_GET['error_description'] = 'DeviantArt returned a 403 Forbidden response. Please try again or contact us if this persists.';
				return null;
			}

			CoreUtils::error_log('Caught '.\get_class($e).': '.$e->getMessage()."\nTrace:\n".var_export($trace, true));
		}
		catch (IdentityProviderException $e){
			if (Cookie::exists('access')){
				DB::$instance->where('token', CoreUtils::sha256(Cookie::get('access')))->delete('sessions');
				Cookie::delete('access', Cookie::HTTP_ONLY);
			}
			$response_body = $e->getResponseBody();
			try {
				if (\is_array($response_body))
					$data = $response_body;
				else $data = JSON::decode($response_body);

				if ($data['error_description'] === 'User has revoked access.')
					return null;

				$_GET['error'] = rawurlencode($data['error']);
				$_GET['error_description'] = !empty($data['error_description']) ? $data['error_description'] : (self::OAUTH_RESPONSE[$data['error']] ?? '');
			}
			catch(JSONParseException $_){
				$_GET['error'] = 'server_error';
				$_GET['error_description'] = $e->getMessage();
			}
			CoreUtils::error_log(__METHOD__.' threw IdentityProviderException: '.$e->getMessage()."\nResponse body:\n$response_body\nTrace:\n".$e->getTraceAsString());
			return null;
		}

		$userdata = $provider->getResourceOwner($accessToken)->toArray();

		/** @var $User Models\User */
		$User = User::find($userdata['userid']);
		// TODO When re-implementing banning, this can be re-used
		/* if (isset($User->role) && $User->role === 'ban'){
			$_GET['error'] = 'user_banned';
			$BanReason = DB::$instance
				->where('target', $User->id)
				->orderBy('entryid')
				->getOne('log__banish');
			if (!empty($BanReason))
				$_GET['error_description'] = $BanReason['reason'];

			return null;
		} */

		$UserID = strtolower($userdata['userid']);
		$UserData = [
			'name' => $userdata['username'],
			'avatar_url' => URL::makeHttps($userdata['usericon']),
		];
		$AuthData = [
			'access' => $accessToken->getToken(),
			'refresh' => $accessToken->getRefreshToken(),
			'expires' => date('c',$accessToken->getExpires()),
			'scope' => $accessToken->getValues()['scope'],
		];

		if (!$refresh){
			$cookie = Session::generateCookie();
			$AuthData['token'] = CoreUtils::sha256($cookie);

			$browser = CoreUtils::detectBrowser();
			foreach ($browser as $k => $v)
				if (!empty($v))
					$AuthData[$k] = $v;
		}

		if (empty($User)){
			$MoreInfo = [
				'id' => $UserID,
				'role' => 'user',
			];
			$makeDev = !DB::$instance->has('users');
			if ($makeDev)
				$MoreInfo['id'] = strtoupper($MoreInfo['id']);
			$Insert = array_merge($UserData, $MoreInfo);
			$User = User::create($Insert);
			if ($makeDev)
				$User->updateRole('developer');
		}
		else $User->update_attributes($UserData);

		if (empty($makeDev) && !empty($User)){
			$clubmember = $User->isClubMember();
			$permmember = Permission::sufficient('member', $User->role);
			if ($clubmember && !$permmember)
				$User->updateRole(self::getClubRole($User));
			else if (!$clubmember && $permmember)
				$User->updateRole('user');
		}

		if ($refresh)
			Auth::$session->update_attributes($AuthData);
		else {
			Session::delete_all(['conditions' => ['user_id = ? AND scope != ?', $User->id, $AuthData['scope']]]);
			$update = array_merge($AuthData, ['user_id' => $User->id]);
			if (Auth::$session !== null)
				Auth::$session->update_attributes($update);
			else Auth::$session = Session::create($update);
		}

		Session::delete_all(['conditions' => ["user_id = ? AND last_visit <= NOW() - INTERVAL '1 MONTH'", $User->id]]);
		if (!$refresh)
			Session::setCookie($cookie);
		return $User ?? null;
	}

	/**
	 * Updates the (current) session for seamless browsing even if the session expires beetween requests
	 *
	 * @return User|void
	 * @throws RuntimeException
	 * @throws \InvalidArgumentException
	 */
	public static function refreshAccessToken():?User {
		if (empty(Auth::$session))
			throw new RuntimeException('Auth::$session must be set');
		return self::_authRequest(true, Auth::$session->refresh);
	}

	/**
	 * Requests an Access Token
	 *
	 * @param string $code
	 *
	 * @return User|void
	 * @throws \InvalidArgumentException
	 */
	public static function getAccessToken(string $code):?User {
		return self::_authRequest(false, $code);
	}

	public static function isImageAvailable(string $url, array $onlyFails = []):bool {
		if (CoreUtils::isURLAvailable($url, $onlyFails))
			return true;
		CoreUtils::msleep(300);
		if (CoreUtils::isURLAvailable($url, $onlyFails))
			return true;
		CoreUtils::msleep(300);
		if (CoreUtils::isURLAvailable("$url?", $onlyFails))
			return true;
		CoreUtils::msleep(300);
		if (CoreUtils::isURLAvailable("$url?", $onlyFails))
			return true;
		return false;
	}

	/**
	 * Parses various DeviantArt pages and returns the usernames of members along with their role
	 * Results are cached for 10 minutes
	 *
	 * @return array [ 'username' => 'role', ... ]
	 */
	public static function getMemberList():array {
		$cache = CachedFile::init(FSPATH.'members.json.gz', Time::IN_SECONDS['minute']*10);
		if (!$cache->expired())
			return $cache->read();

		$usernames = [];
		$off = 0;
		// Get regular members
		while (true){
			$memberlist = HTTP::legitimateRequest("https://www.deviantart.com/mlp-vectorclub/modals/memberlist/?offset=$off");
			if (empty($memberlist['response']))
				break;
			$dom = new \DOMDocument('1.0', 'UTF-8');
			$internalErrors = libxml_use_internal_errors(true);
			$dom->loadHTML($memberlist['response']);
			libxml_use_internal_errors($internalErrors);
			$members = $dom->getElementById('userlist')->childNodes->item(0)->childNodes;
			foreach ($members as $node){
				$username = $node->lastChild->firstChild->textContent;
				$usernames[$username] = 'member';
			}
			$xp = new \DOMXPath($dom);
			$more =  $xp->query('//ul[@class="pages"]/li[@class="next"]');
			if ($more->length === 0 || $more->item(0)->firstChild->getAttribute('class') === 'disabled')
				break;
			$off += 100;
		}
		unset($dom, $xp);

		// Get staff
		$requri = 'http://www.deviantart.com/global/difi/?c%5B%5D=%22GrusersModules%22%2C%22displayModule%22%2C%5B%2217450764%22%2C%22374037863%22%2C%22generic%22%2C%7B%7D%5D&iid=576m8f040364c99a7d9373611b4a9414d434-j2asw8mn-1.1&mp=2&t=json';
		$stafflist = JSON::decode(HTTP::legitimateRequest($requri)['response'], false);
		$stafflist = $stafflist->DiFi->response->calls[0]->response->content->html;
		$stafflist = str_replace('id="gmi-GAboutUsModule_Item"','',$stafflist);
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->loadHTML($stafflist);
		$xp = new \DOMXPath($dom);
		$admins =  $xp->query('//div[@id="aboutus"]//div[@class="user-name"]');
		/** @var $revroles array */
		$revroles = array_flip(Permission::ROLES_ASSOC);
		foreach ($admins as $admin){
			$username = $admin->childNodes->item(1)->firstChild->textContent;
			$role = CoreUtils::makeSingular($admin->childNodes->item(3)->textContent);
			if (!isset($revroles[$role]))
				throw new \RuntimeException("Role $role not reversible");
			$usernames[$username] = $revroles[$role];
		}

		$cache->update($usernames);

		return $usernames;
	}

	/**
	 * @param User $user
	 *
	 * @return null|string
	 */
	public static function getClubRole(User $user):?string {
		return self::getClubRoleByName($user->name);
	}

	/**
	 * @param string $username
	 *
	 * @return null|string
	 */
	public static function getClubRoleByName(string $username):?string {
		$usernames = self::getMemberList();
		return $usernames[$username] ?? null;
	}

	public static function favmeHttpsUrl(string $favme_id):string {
		return 'https://www.deviantart.com/art/REDIRECT-'.\intval(mb_substr($favme_id, 1), 36);
	}

	public static function trimOutgoingGateFromUrl(string $url):string {
		return preg_replace(new RegExp('^https?://(www\.)?deviantart\.com/users/outgoing\?'),'',$url);
	}

	/**
	 * Retrieve the full size URL for a submission
	 *
	 * @param string $id
	 * @param string $prov
	 * @param string $formats
	 *
	 * @return null|string
	 */
	public static function getDownloadURL($id, $prov, $formats = 'png|jpe?g|bmp'){
		$stash_url = $prov === 'sta.sh' ? "https://sta.sh/$id" : self::favmeHttpsUrl($id);
		try {
			$stashpage = HTTP::legitimateRequest($stash_url);
		}
		catch (CURLRequestException $e){
			if ($e->getCode() === 404)
				return 404;

			return 1;
		}
		catch (\Exception $e){
			return 2;
		}
		if (empty($stashpage))
			return 3;

		$DL_LINK_REGEX = "(https?://(sta\.sh|www\.deviantart\.com)/download/\d+/[a-z\d_]+-d[a-z\d]{6,}\.(?:$formats)\?[^\"]+)";
		$urlmatch = preg_match(new RegExp('<a\s+class="[^"]*?dev-page-download[^"]*?"\s+href="'.$DL_LINK_REGEX.'"'), $stashpage['response'], $_match);

		if (!$urlmatch)
			return 4;

		$fullsize_url = HTTP::findRedirectTarget(htmlspecialchars_decode($_match[1]), $stash_url);

		if (empty($fullsize_url))
			return 5;

		$CachedDeviation = CachedDeviation::find_by_id_and_provider($id, $prov);
		if (!empty($CachedDeviation)){
			$CachedDeviation->fullsize = $fullsize_url;
			$CachedDeviation->save();
		}

		return URL::makeHttps($fullsize_url);
	}

	/**
	 * Normalize a misaligned Stash submission ID
	 *
	 * @param string $id Stash submission ID
	 *
	 * @return string
	 */
	public static function nomralizeStashID($id){
		$normalized = ltrim($id, '0');

		return mb_strlen($normalized) < 12 ? '0'.$normalized : $normalized;
	}
}
