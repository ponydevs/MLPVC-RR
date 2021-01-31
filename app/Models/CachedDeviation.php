<?php

namespace App\Models;

use App\CoreUtils;
use App\JSON;
use App\RedisHelper;

class CachedDeviation {
  public ?string $provider;
  public ?string $id;
  public ?string $title;
  public ?string $author;
  public ?string $preview;
  public ?string $fullsize;
  public ?string $type;

  public function __construct(array $data) {
    foreach ($data as $k => $v){
      if (property_exists($this, $k)){
        $this->{$k} = $v;
      }
    }
  }

  public static function getCacheKey(string $id, string $provider):string {
    return "deviation_v1_{$id}_$provider";
  }

  public static function find(string $id, string $provider):?self {
    $cached = RedisHelper::get(self::getCacheKey($id, $provider));
    if (empty($cached))
      return null;

    return new self(JSON::decode($cached));
  }

  public static function create(array $data):?self {
    $instance = new self($data);
    $instance->save();

    return $instance;
  }

  public function save():?self {
    RedisHelper::set(self::getCacheKey($this->id, $this->provider), JSON::encode([
      'provider' => $this->provider,
      'id' => $this->id,
      'title' => $this->title,
      'author' => $this->author,
      'preview' => $this->preview,
      'fullsize' => $this->fullsize,
      'type' => $this->type,
    ]));

    return $this;
  }

  public function update_attributes(array $attributes) {
    foreach ($attributes as $key => $value)
      if (property_exists($this, $key))
        $this->{$key} = $value;
    $this->save();
  }

  public function toLinkWithPreview() {
    $stitle = CoreUtils::aposEncode($this->title);

    return "<a class='deviation-link with-preview' href='http://{$this->provider}/{$this->id}'><img src='{$this->preview}' alt='$stitle'><span>$stitle</span></a>";
  }

  public function delete():void {
    RedisHelper::del(self::getCacheKey($this->id, $this->provider));
  }
}
