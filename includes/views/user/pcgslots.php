<?php
use App\CoreUtils;
use App\CGUtils;
use App\Tags;
/** @var $heading string */
/** @var $Entries \App\Models\PCGSlotHistory[] */
/** @var $Pagination \App\Pagination */
/** @var $User \App\Models\User */
/** @var $isOwner bool */ ?>
<div id="content">
	<h1><?=$heading?></h1>
	<p>Displaying <?=$Pagination->itemsPerPage?> items/page</p>

<?php   if (\App\Permission::sufficient('developer')){ ?>
	<div class="align-center">
		<button class="orange typcn typcn-refresh" id="recalc-button">Recalculate</button>
	</div>
<?php   }
		echo $Pagination . CGUtils::getPCGSlotHistoryHTML($Entries) . $Pagination; ?>
</div>

<?  echo CoreUtils::exportVars([
		'username' => $User->name,
	]);
