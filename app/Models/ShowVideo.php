<?php

namespace App\Models;

use ActiveRecord\DateTime;
use App\CoreUtils;
use App\ShowHelper;
use App\Time;
use App\VideoProvider;
use Google_Client;
use Google_Service_YouTube;
use Google_Service_YouTube_Video;
use RuntimeException;
use function count;
use function in_array;

/**
 * @property int      $id
 * @property int      $show_id
 * @property int      $part
 * @property bool     $fullep
 * @property string   $provider_abbr
 * @property string   $provider_id
 * @property DateTime $modified
 * @property DateTime $not_broken_at
 * @property Show     $show          (Via relations)
 * @property string   $url           (Via magic method)
 * @property string   $button_class  (Via magic method)
 * @property string   $provider_name (Via magic method)
 */
class ShowVideo extends NSModel {
  public static $table_name = 'show_videos';

  public static $belongs_to = [
    ['show'],
  ];

  public function get_url():string {
    return VideoProvider::getEmbed($this, VideoProvider::URL_ONLY);
  }

  public function get_button_class():string {
    return ShowHelper::PROVIDER_BTN_CLASSES[$this->provider_abbr];
  }

  public function get_provider_name():string {
    return ShowHelper::VIDEO_PROVIDER_NAMES[$this->provider_abbr];
  }

  public function isBroken():bool {
    if ($this->not_broken_at !== null && $this->not_broken_at->getTimestamp() + (Time::IN_SECONDS['hour'] * 2) > time())
      return false;

    switch ($this->provider_abbr){
      case 'yt':
        $client = new Google_Client();
        $client->setApplicationName(GITHUB_URL);
        $client->setDeveloperKey(CoreUtils::env('GOOGLE_API_KEY'));
        $service = new Google_Service_YouTube($client);
        $details = $service->videos->listVideos('contentDetails', ['id' => $this->provider_id]);

        if (!empty($details)){
          /** @var $video Google_Service_YouTube_Video */
          $items = $details->getItems();
          if (empty($items))
            $broken = true;
          else {
            $video = $items[0];
            $blocked = $video->getContentDetails()->getRegionRestriction()->blocked;
            $broken = !empty($blocked) && (count($blocked) > 100 || in_array('US', $blocked, true));
          }
        }
        else $broken = false;
      break;
      case 'dm':
        $broken = !CoreUtils::isURLAvailable("https://api.dailymotion.com/video/{$this->provider_id}");
      break;
      case 'mg':
        // Skip over
      break;
      case 'sv':
        $broken = !CoreUtils::isURLAvailable(VideoProvider::getEmbed($this, VideoProvider::URL_ONLY));
      break;
      default:
        throw new RuntimeException("No breakage check defined for provider {$this->provider_abbr}");
    }

    if (!$broken){
      $this->not_broken_at = date('c');
      $this->save();
    }

    return $broken;
  }
}
