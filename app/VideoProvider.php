<?php

namespace App;

use App\Models\ShowVideo;
use Exception;
use RuntimeException;

class VideoProvider {
  public static $id, $embed;
  /** @var ShowVideo */
  public $episodeVideo;

  public function __construct(string $url) {
    $this->episodeVideo = self::getEpisodeVideo(CoreUtils::trim($url));
    self::$id = $this->episodeVideo->id;
    self::getEmbed($this->episodeVideo);
  }

  private static $providerRegexes = [
    'youtu(?:\.be/|be.com/watch.*[&?]v=)([^&?=]+)(?:&|$)' => 'yt',
    'dai(?:\.ly/|lymotion.com/video/(?:embed/)?)([a-z\d]+)(?:_|$)' => 'dm',
    'sendvid\.com/(?:embed/)?([a-z\d]+)$' => 'sv',
    'mega(?:\.co)?\.nz/(?:embed)?#!([A-Za-z\d!_-]+)$' => 'mg',
  ];

  /**
   * @param string $url
   * @param string $pattern
   * @param string $name
   *
   * @return ShowVideo|null
   */
  private static function testProvider(string $url, string $pattern, string $name) {
    $match = [];
    if (preg_match("~^(?:https?://(?:www\\.)?)?$pattern~", $url, $match))
      return new ShowVideo([
        'provider' => $name,
        'id' => $match[1],
      ]);

    return null;
  }

  public static function getEpisodeVideo(string $url):ShowVideo {
    foreach (self::$providerRegexes as $pattern => $name){
      $test = self::testProvider($url, $pattern, $name);
      if (!empty($test))
        return $test;
    }
    throw new Exception('Unsupported provider');
  }

  public const URL_ONLY = true;

  public static function getEmbed(ShowVideo $video, bool $urlOnly = false):string {
    $urlOnly = $urlOnly === self::URL_ONLY;

    switch ($video->provider){
      case 'yt':
        $url = $urlOnly ? "http://youtu.be/$video->id" : "https://www.youtube.com/embed/$video->id";
      break;
      case 'dm':
        $url = $urlOnly ? "http://dai.ly/$video->id" : "https://www.dailymotion.com/embed/video/$video->id?related=0&quality=1080&highlight=2C73B1";
      break;
      case 'sv':
        $url = $urlOnly ? "https://sendvid.com/$video->id" : "https://sendvid.com/embed/$video->id";
      break;
      case 'mg':
        $url = $urlOnly ? "https://mega.nz/#!$video->id" : "https://mega.nz/embed#!$video->id";
      break;
      default:
        throw new RuntimeException("Unrecognized provider: {$video->provider}");
    }

    /** @noinspection HtmlUnknownAttribute */
    return $urlOnly ? $url : "<iframe async defer allowfullscreen src='$url'></iframe>";
  }
}
