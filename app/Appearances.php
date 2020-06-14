<?php

namespace App;

use App\Models\Appearance;
use App\Models\Notification;
use Elasticsearch\Common\Exceptions\BadRequest400Exception as ElasticBadRequest400Exception;
use Elasticsearch\Common\Exceptions\Missing404Exception as ElasticMissing404Exception;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException as ElasticNoNodesAvailableException;
use function count;
use function is_int;
use function is_string;

class Appearances {
  public const COUNT_COL = 'COUNT(*) as cnt';
  public const PCG_APPEARANCE_MAKE_DISABLED = 'You are not allowed to create personal color guide appearances';

  /**
   * @param string|null $guide
   * @param int|int[] $limit
   * @param string    $userid
   * @param string    $cols
   *
   * @return Appearance[]
   */
  public static function get(?string $guide, $limit = null, ?string $userid = null, ?string $cols = null) {
    if ($userid !== null)
      DB::$instance->where('owner_id', $userid);
    else {
      DB::$instance->where('owner_id IS NULL');
      self::_order();
      if ($guide !== null)
        DB::$instance->where('guide', $guide)->where('id', 0, '!=');
    }
    if ($cols === self::COUNT_COL)
      DB::$instance->disableAutoClass();

    return DB::$instance->get('appearances', $limit, $cols);
  }

  /**
   * Order appearances
   *
   * @param string $dir
   */
  private static function _order($dir = 'ASC') {
    DB::$instance->orderByLiteral('CASE WHEN "order" IS NULL THEN 1 ELSE 0 END', $dir)
      ->orderBy('"order"', $dir)
      ->orderBy('id', $dir);
  }

  /**
   * Sort appearances based on tags
   *
   * @param Appearance[] $Appearances
   * @param string       $guide
   * @param bool         $simpleArray
   *
   * @return array
   */
  public static function sort($Appearances, ?string $guide, bool $simpleArray = false) {
    $GroupTagIDs = array_keys(CGUtils::GROUP_TAG_IDS_ASSOC[$guide]);
    $Sorted = [];
    $Tagged = [];
    $_tagged = DB::$instance->where('tag_id IN ('.implode(',', $GroupTagIDs).')')->orderBy('appearance_id')->get('tagged');
    foreach ($_tagged as $row)
      $Tagged[$row->appearance_id][] = $row->tag_id;
    foreach ($Appearances as $p){
      if (!empty($Tagged[$p->id])){
        if (count($Tagged[$p->id]) > 1)
          usort($Tagged[$p->id], function ($a, $b) use ($GroupTagIDs) {
            return array_search($a, $GroupTagIDs, true) - array_search($b, $GroupTagIDs, true);
          });
        $tid = $Tagged[$p->id][0];
      }
      else $tid = -1;
      $Sorted[$tid][] = $p;
    }
    if ($simpleArray){
      $idArray = [];
      foreach (CGUtils::GROUP_TAG_IDS_ASSOC[$guide] as $Category => $CategoryName){
        if (empty($Sorted[$Category]))
          continue;
        /** @var $Sorted Appearance[][] */
        foreach ($Sorted[$Category] as $p)
          $idArray[] = $p->id;
      }

      return $idArray;
    }
    else return $Sorted;
  }

  /**
   * @param string|int[] $ids
   */
  public static function reorder($ids) {
    if (empty($ids))
      return;

    $normalized_ids = is_string($ids) ? explode(',', $ids) : $ids;
    $order_map = array_flip($normalized_ids);
    /** @var $appearances Appearance[] */
    $appearances = DB::$instance->where('id', $normalized_ids)->get(Appearance::$table_name);
    foreach ($appearances as $app){
      if (!isset($order_map[$app->id]))
        continue;

      $app->order = $order_map[$app->id];
      if (!$app->save())
        Response::fail("Updating appearance #{$app->id} failed, process halted");

      $app->updateIndex();
    }
  }

  public static function getSortReorder(?string $guide) {
    if ($guide !== 'pony')
      return;
    self::reorder(self::sort(self::get($guide, null, null, 'id'), $guide, SIMPLE_ARRAY));
  }

  public static function reindex() {
    $elastic_client = CoreUtils::elasticClient();
    try {
      $elastic_client->indices()->delete(CGUtils::ELASTIC_BASE);
    }
    catch (ElasticMissing404Exception $e){
      $message = JSON::decode($e->getMessage());

      // Eat exception if the index we're re-creating does not exist yet
      if ($message['error']['type'] !== 'index_not_found_exception' || $message['error']['index'] !== CGUtils::ELASTIC_BASE['index'])
        throw $e;
    }
    catch (ElasticNoNodesAvailableException $e){
      Response::fail('Re-index failed, ElasticSearch server is down!');
    }
    $params = array_merge(CGUtils::ELASTIC_BASE, [
      'body' => [
        'mappings' => [
          'properties' => [
            'label' => [
              'type' => 'text',
              'analyzer' => 'overkill',
            ],
            'order' => ['type' => 'integer'],
            'guide' => ['type' => 'keyword'],
            'private' => ['type' => 'boolean'],
            'tags' => [
              'type' => 'text',
              'analyzer' => 'overkill',
            ],
          ],
        ],
        'settings' => [
          'analysis' => [
            'analyzer' => [
              'overkill' => [
                'type' => 'custom',
                'tokenizer' => 'overkill',
                'filter' => [
                  'lowercase',
                ],
              ],
            ],
            'tokenizer' => [
              'overkill' => [
                'type' => 'edge_ngram',
                'min_gram' => 2,
                'max_gram' => 30,
                'token_chars' => [
                  'letter',
                  'digit',
                ],
              ],
            ],
          ],
        ],
      ],
    ]);
    try {
      $elastic_client->indices()->create($params);
    }
    catch (ElasticBadRequest400Exception $e){
      Response::fail('Failed to create index:<br><pre>'.CoreUtils::escapeHTML(JSON::encode(JSON::decode($e->getMessage()), JSON_PRETTY_PRINT)).'</pre>');
    }
    catch (ElasticNoNodesAvailableException $e){
      Response::fail('Re-index failed, ElasticSearch server is down!');
    }
    /** @var $appearances Appearance[] */
    $appearances = DB::$instance->where('id != 0')->where('owner_id IS NULL')->get('appearances');

    $params = ['body' => []];
    foreach ($appearances as $i => $a){
      $meta = $a->getElasticMeta();
      $params['body'][] = [
        'index' => [
          '_index' => $meta['index'],
          '_id' => $meta['id'],
        ],
      ];

      $params['body'][] = $a->getElasticBody();

      if ($i % 100 === 0){
        self::handleBulkError($elastic_client->bulk($params));
        $params = ['body' => []];
      }
    }
    if (!empty($params['body'])){
      self::handleBulkError($elastic_client->bulk($params));
    }

    Response::success('Re-index completed');
  }

  private static function handleBulkError(array $bulkResult) {
    if ($bulkResult['errors'] !== true)
      return;

    $error_messages = [];
    foreach ($bulkResult['items'] as $item){
      $ix = $item['index'];
      $error_messages[] = "#{$ix['_id']}: HTTP {$ix['status']}: {$ix['error']['type']} - {$ix['error']['reason']}";
    }

    Response::fail('Bulk index update failed, see the errors below.<br><pre>'.CoreUtils::escapeHTML(implode("\n", $error_messages)).'</pre>');
  }

  public const SPRITE_NAG_USER_ID = 139;

  /**
   * @param int    $appearance_id
   * @param string $nag_id ID of user to nag
   *
   * @return Notification[]
   */
  public static function getSpriteColorIssueNotifications(int $appearance_id, ?string $nag_id = self::SPRITE_NAG_USER_ID) {
    if ($nag_id !== null)
      DB::$instance->where('recipient_id', $nag_id);

    return DB::$instance
      ->where('type', 'sprite-colors')
      ->where("data->>'appearance_id'", (string)$appearance_id)
      ->where('read_at', null)
      ->orderBy('created_at', 'DESC')
      ->get(Notification::$table_name);
  }

  /**
   * @param int|Notification[] $appearance_id
   * @param string             $action What to set as the notification clearing action
   * @param string             $nag_id ID of user to nag
   */
  public static function clearSpriteColorIssueNotifications($appearance_id, string $action = 'clear', ?string $nag_id = self::SPRITE_NAG_USER_ID) {
    if (is_int($appearance_id))
      $notifs = self::getSpriteColorIssueNotifications($appearance_id, $nag_id);
    else $notifs = $appearance_id;
    if (empty($notifs))
      return;

    foreach ($notifs as $n)
      $n->safeMarkRead($action);
  }
}
