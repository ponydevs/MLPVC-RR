<?php
use App\CoreUtils;
use App\CGUtils;
use App\Tags;
/** @var $heading string */
/** @var $EQG bool */
/** @var $Changes array */
/** @var $Pagination \App\Pagination */ ?>
<div id="content">
	<h1><?=$heading?></h1>
	<p>Displaying <?=$Pagination->getItemsPerPage()?> items/page</p>
	<div class='align-center button-block'>
		<a class='btn link typcn typcn-arrow-back' href="/cg/<?=$EQG?'eqg':'pony'?>">Back to <?=$EQG?'EQG':'Pony'?> Guide</a>
		<a class='btn link typcn typcn-world' href="/cg/<?=$EQG?'pony':'eqg'?>/changes">View <?=$EQG?'Pony':'EQG'?> Changes</a>
		<a class='btn link typcn typcn-tags' href="/cg/tags">List of tags</a>
	</div>
	<div class="responsive-table"><?=$Pagination . CGUtils::getMajorChangesHTML($Changes) . $Pagination?></div>
</div>

<?  echo CoreUtils::exportVars([
	'TAG_TYPES_ASSOC' => Tags::TAG_TYPES,
]);
