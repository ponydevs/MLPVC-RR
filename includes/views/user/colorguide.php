<?php
use App\CoreUtils;
use App\Permission;
use App\Appearances;
use App\Tags;
/** @var $heading string */
/** @var $User \App\Models\User */
/** @var $isOwner bool */
/** @var $Pagination \App\Pagination */
/** @var $Ponies array */ ?>
<div id="content">
	<h1><?=$heading?></h1>
	<p>Unofficial colors maintained by <?=$User->toAnchor()?></p>
<?  if ($isOwner || Permission::sufficient('staff')){ ?>
	<div class="notice warn tagediting">
		<label>Limited editing</label>
		<p>Editing tags or colors from the guide page does not work on mobile devices. If you want to edit those, please go the appearance’s page.</p>
	</div>
<?  } ?>
<?  if ($isOwner || Permission::sufficient('staff')){ ?>
	<p class='align-center links'>
		<button class='green typcn typcn-plus' id="new-appearance-btn">Add new appearance</button>
	</p>
<?  } ?>
	<?=$Pagination . Appearances::getHTML($Ponies, WRAP, Permission::sufficient('staff') || $isOwner) . $Pagination?>
</div>

<?  $export = [
		'EQG' => false,
		'AppearancePage' => false,
		'PersonalGuide' => $User->name,
	];
	if (Permission::sufficient('staff') || $isOwner){
		global $HEX_COLOR_REGEX;
		$export = array_merge($export, [
			'TAG_TYPES_ASSOC' => Tags::TAG_TYPES,
			'MAX_SIZE' => CoreUtils::getMaxUploadSize(),
			'HEX_COLOR_PATTERN' => $HEX_COLOR_REGEX,
		]);
	}
	echo CoreUtils::exportVars($export); ?>
