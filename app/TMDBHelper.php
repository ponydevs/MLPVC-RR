<?php

namespace App;

use App\Models\Show;
use Doctrine\Common\Cache\RedisCache;
use RuntimeException;
use Tmdb\ApiToken;
use Tmdb\Client;
use Tmdb\Exception\TmdbApiException;
use Tmdb\Helper\ImageHelper;
use Tmdb\Repository\ConfigurationRepository;

class TMDBHelper {
  public const SHOW_NAME = 'My Little Pony: Friendship Is Magic';
  public const SHOW_ID_CACHE_KEY = 'tmdb_show_id';
  public const REQUIRED_MESSAGE = 'This product uses the TMDb API but is not endorsed or certified by TMDb.';

  /**
   * @var Client|null
   */
  private static $client;

  public static function apiKeyConfigured():bool {
    return !empty(CoreUtils::env('TMDB_API_KEY'));
  }

  public static function getClient():Client {
    if (!self::apiKeyConfigured())
      throw new RuntimeException(__METHOD__.' called without a configured TMDb API key');

    if (self::$client === null){
      $token = new ApiToken(CoreUtils::env('TMDB_API_KEY'));
      $cache_handler = new RedisCache();
      $cache_handler->setRedis(RedisHelper::getInstance());

      self::$client = new Client($token, [
        'cache' => ['handler' => $cache_handler],
      ]);
    }

    return self::$client;
  }

  /**
   * @param Client $client
   *
   * @return int
   * @throws RuntimeException
   */
  public static function getShowId(Client $client = null):int {
    if ($client === null)
      $client = self::getClient();

    $cached_id = RedisHelper::get(self::SHOW_ID_CACHE_KEY);
    if (empty($cached_id)){
      $shows = $client->getSearchApi()->searchTv(self::SHOW_NAME);
      if (empty($shows['results'][0]) || $shows['results'][0]['name'] !== self::SHOW_NAME)
        throw new RuntimeException("Could not find MLP:FiM on TMDB, query results:\n".var_export($shows, true));

      $cached_id = (int)$shows['results'][0]['id'];
      RedisHelper::set(self::SHOW_ID_CACHE_KEY, $cached_id, null);
    }

    return $cached_id;
  }

  public static function getEpisodes(Client $client, Show $ep):array {
    $parts = $ep->parts;
    $eps = [];
    for ($i = 0; $i < $parts; $i++){
      $data = self::getEpisode($client, $ep->season, $ep->episode + $i);
      if ($data !== null)
        $eps[] = $data;
    }

    return $eps;
  }

  public static function getEpisode(Client $client, int $season, int $episode):?array {
    try {
      $ep_data = $client->getTvEpisodeApi()->getEpisode(self::getShowId($client), $season, $episode);
    }
    catch (TmdbApiException $e){
      $response = $e->getResponse();
      if ($response && $response->getCode() === 404)
        return null;
    }

    if (empty($ep_data))
      throw new RuntimeException("Could not find S{$season}E{$episode} on TMDB, query results:\n".var_export($ep_data, true));

    return empty($ep_data['overview']) ? null : $ep_data;
  }

  public static function getImageUrl(Client $client, string $path):string {
    $conf_rep = new ConfigurationRepository($client);
    $config = $conf_rep->load();
    $helper = new ImageHelper($config);

    return 'https://'.$helper->getUrl($path, 'w300');
  }
}
