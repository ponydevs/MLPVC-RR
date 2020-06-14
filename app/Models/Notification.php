<?php

namespace App\Models;

use App\CoreUtils;
use App\DB;
use App\JSON;
use App\Notifications;
use App\Twig;
use ElephantIO\Exception\ServerConnectionFailureException;
use RuntimeException;

/**
 * @property int    $id
 * @property int    $recipient_id
 * @property string $type
 * @property string $data
 * @property string $created_at
 * @property string $read_at
 * @property string $read_action
 * @property User   $recipient
 * @property array  $actions    (Via magic method)
 * @method static Notification find(int $id)
 */
class Notification extends NSModel {
  public static $table_name = 'notifications';

  public static $belongs_to = [
    ['recipient', 'class' => 'User', 'foreign_key' => 'recipient_id'],
  ];

  public function get_actions() {
    return self::ACTIONABLE_NOTIF_OPTIONS[$this->type] ?? null;
  }

  /** @var Post|null */
  private $post = false;

  public function getPost():?Post {
    if (!CoreUtils::startsWith($this->type, 'post-'))
      return null;

    if ($this->post !== false)
      return $this->post;

    $data = JSON::decode($this->data);
    $this->post = Post::find($data['id']);

    return $this->post;
  }

  /** @var Appearance|null */
  private $appearance = false;

  public function getAppearance():?Appearance {
    if (!CoreUtils::startsWith($this->type, 'sprite-'))
      return null;

    if ($this->appearance !== false)
      return $this->appearance;

    $data = JSON::decode($this->data);
    $this->appearance = Appearance::find($data['appearance_id']);

    return $this->appearance;
  }

  public const ACTIONABLE_NOTIF_OPTIONS = [
    'sprite-colors' => [
      'recheck' => [
        'label' => 'Recheck',
        'icon' => 'refresh',
        'color' => 'lavender',
        'confirm' => false,
        'action' => 'Recheck sprite colors',
      ],
      'deny' => [
        'label' => 'Ignore',
        'icon' => 'times',
        'color' => 'orange',
        'action' => 'Ignore color issues',
      ],
    ],
  ];
  public const NOTIF_TYPES = [
    #---------------# (max length)
    'post-finished' => true,
    'post-approved' => true,
    'sprite-colors' => true,
  ];

  public static function send(string $recipient_id, string $type, $data) {
    if (empty(self::NOTIF_TYPES[$type]))
      throw new RuntimeException("Invalid notification type: $type");

    switch ($type){
      case 'post-finished':
      case 'post-approved':
        $post_type = $data['type'] ?? 'post';

        DB::$instance->query(
          "UPDATE notifications SET read_at = NOW() WHERE recipient_id = ? && type = ? && data->>'id' = ? && data->>'type' = ?",
          [$recipient_id, $type, $data['id'], $post_type]
        );
      break;
    }

    self::create([
      'recipient_id' => $recipient_id,
      'type' => $type,
      'data' => JSON::encode($data),
    ]);

    try {
      CoreUtils::socketEvent('notify-pls', ['user' => $recipient_id]);
    }
    catch (ServerConnectionFailureException $e){
      CoreUtils::error_log("Error while notifying $recipient_id with type $type (data:".JSON::encode($data).")\nError message: {$e->getMessage()}\nTrace:\n{$e->getTraceAsString()}");

      return 'Notification server is down! Please <a class="send-feedback">let us know</a>.';
    }

    return 0;
  }

  public function safeMarkRead(?string $action = null, bool $silent = true) {
    Notifications::safeMarkRead($this->id, $action, $silent);
  }

  public function getViewName() {
    return 'notifications/_type_'.str_replace('-', '_', $this->type).'.html.twig';
  }

  public function getHtml():string {
    $view_name = $this->getViewName();
    if (Twig::$env->getLoader()->exists($view_name))
      return $this->getElement(Twig::$env->render($view_name, ['notif' => $this]));

    return "<li><code>Notification({$this->type})#{$this->id}</code> <span class='nobr'>&ndash; Missing handler</span></li>";
  }

  /**
   * @param string $html
   *
   * @return string
   */
  private function getElement(string $html):string {
    return Twig::$env->render('notifications/_element.html.twig', [
      'html' => $html,
      'notif' => $this,
    ]);
  }
}
