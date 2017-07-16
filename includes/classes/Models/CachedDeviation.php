<?php

namespace App\Models;

use ActiveRecord\DateTime;
use ActiveRecord\Model;
use App\CoreUtils;

/**
 * @property string   $provider
 * @property string   $id
 * @property string   $title
 * @property string   $author
 * @property string   $preview
 * @property string   $fullsize
 * @property string   $type
 * @property DateTime $updated_on
 * @method static CachedDeviation find_by_id_and_provider(string $id, string $provider)
 */
class CachedDeviation extends Model {
	public static $primary_key = ['provider', 'id'];

	public function toLinkWithPreview(){
		$stitle = CoreUtils::escapeHTML($this->title);
		return "<a class='deviation-link with-preview' href='http://{$this->provider}/{$this->id}'><img src='{$this->preview}' alt='$stitle'><span>$stitle</span></a>";
	}
}
