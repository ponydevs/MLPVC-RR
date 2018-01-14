<?php
use App\Tags;
use App\CoreUtils;
use App\Permission;
use App\Models\Tag;
/** @var $heading string */
/** @var $Tags Tag[] */
/** @var $Pagination \App\Pagination */ ?>
<div id="content">
	<h1><?=$heading?></h1>
	<p>Displaying <?=$Pagination->itemsPerPage?> items/page</p>
	<div class='align-center button-block'>
		<a class='btn link typcn typcn-arrow-back' href="/cg">Back to Color Guide</a>
		<a class='btn link typcn typcn-warning' href="/cg/changes">Major Changes</a>
	</div>
	<?=$Pagination?>
	<table id="tags">
		<thead><?php
	$cspan = Permission::sufficient('staff') ? '" colspan="2' : '';
	$refresher = Permission::sufficient('staff') ? " <button class='typcn typcn-arrow-sync refresh-all' title='Refresh usage data on this page'></button>" : '';
	echo $thead = <<<HTML
			<tr>
				<th class="tid">ID</th>
				<th class="name{$cspan}">Name</th>
				<th class="title">Description</th>
				<th class="type">Type</th>
				<th class="uses">Uses$refresher</th>
			</tr>
HTML;
?></thead>
		<?=Tags::getTagListHTML($Tags)?>
		<tfoot><?=$thead?></tfoot>
	</table>
	<?=$Pagination?>
</div>

<?  echo CoreUtils::exportVars([
	'TAG_TYPES_ASSOC' => Tags::TAG_TYPES,
]);
