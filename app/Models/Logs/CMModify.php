<?php

namespace App\Models\Logs;

use App\Models\Appearance;

/**
 * @inheritdoc
 * @property int        $appearance_id
 * @property string     $olddata
 * @property string     $newdata
 * @property Appearance $appearance
 */
class CMModify extends AbstractEntryType {
	public static $table_name = 'log__cm_modify';
}
