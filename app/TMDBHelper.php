<?php

namespace App;

use App\Models\Episode;
use Doctrine\Common\Cache\RedisCache;

class TMDBHelper {
	public const SHOW_NAME = 'My Little Pony: Friendship Is Magic';
	public const SHOW_ID_CACHE_KEY = 'tmdb_show_id';
	public const REQUIRED_MESSAGE = 'This product uses the TMDb API but is not endorsed or certified by TMDb.';

	/**
	 * @var \Tmdb\Client|null
	 */
	private static $client;

	public static function apiKeyConfigured():bool {
		return \defined('TMDB_API_KEY') && !empty(TMDB_API_KEY);
	}

	public static function getClient():?\Tmdb\Client {
		if (!self::apiKeyConfigured())
			return null;

		if (self::$client === null){
			$token = new \Tmdb\ApiToken(TMDB_API_KEY);

			self::$client = new \Tmdb\Client($token, [
				'cache' => [
					// We do our own caching in the DB
					'enabled' => false,
				],
			]);

			/* Maybe someday */
			/* $cache_handler = new RedisCache();
			$cache_handler->setRedis(RedisHelper::getInstance());

			self::$client = new \Tmdb\Client($token, [
				'cache' => ['handler' => $cache_handler],
			]); */
		}

		return self::$client;
	}

	/**
	 * @param \Tmdb\Client $client
	 *
	 * @return int
	 * @throws \RuntimeException
	 */
	public static function getShowId(\Tmdb\Client $client = null):int {
		if ($client === null)
			$client = self::getClient();

		$cached_id = RedisHelper::get(self::SHOW_ID_CACHE_KEY);
		if (empty($cached_id)){
			$shows = $client->getSearchApi()->searchTv(self::SHOW_NAME);
			if (empty($shows['results'][0]) || $shows['results'][0]['name'] !== self::SHOW_NAME)
				throw new \RuntimeException("Could not find MLP:FiM on TMDB, query results:\n".var_export($shows, true));

			$cached_id = (int)$shows['results'][0]['id'];
			RedisHelper::set(self::SHOW_ID_CACHE_KEY, $cached_id, null);
		}

		return $cached_id;
	}

	public static function getEpisodes(\Tmdb\Client $client, Episode $ep):array {
		$parts = $ep->twoparter ? 2 : 1;
		$eps = [];
		for ($i = 0; $i < $parts; $i++){
			$data = self::getEpisode($client, $ep->season, $ep->episode + $i);
			if ($data!== null)
				$eps[] = $data;
		}

		return $eps;
	}

	public static function getEpisode(\Tmdb\Client $client, int $season, int $episode):?array {
		$ep_data = $client->getTvEpisodeApi()->getEpisode(self::getShowId($client), $season, $episode);

		if (empty($ep_data))
			throw new \RuntimeException("Could not find S{$season}E{$episode} on TMDB, query results:\n".var_export($ep_data, true));

		return empty($ep_data['overview']) ? null : $ep_data;
	}

	public static function getImageUrl(\Tmdb\Client $client, string $path):string {
		$conf_rep = new \Tmdb\Repository\ConfigurationRepository($client);
		$config = $conf_rep->load();
		$helper = new \Tmdb\Helper\ImageHelper($config);

		return 'https://'.$helper->getUrl($path, 'w300');
	}
}
