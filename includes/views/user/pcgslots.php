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
	<p>Displaying <?= $Pagination->itemsPerPage ?> items/page</p>

	<div class="align-center button-block">
		<a href="<?=$User->toURL()?>" class="btn link typcn typcn-user">Profile page</a>
<?php   if (\App\Permission::sufficient('staff')){
			if (\App\Permission::sufficient('developer')){ ?>
		<button class="orange typcn typcn-refresh" id="recalc-button">Recalculate</button>
<?php       } ?>
		<button class="blue typcn typcn-gift" id="pending-gifts-button">Pending gifts</button>
<?php   } ?>
	</div>
	<?= $Pagination . CGUtils::getPCGSlotHistoryHTML($Entries) . $Pagination; ?>
</div>

<?  echo CoreUtils::exportVars([
		'username' => $User->name,
	]);
