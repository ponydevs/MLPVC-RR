<?php
/** @var $Pagination App\Pagination */
/** @var $Appearances App\Models\Appearance[] */ ?>
<div id="content" class="section-container">
	<h1><?=$heading?></h1>
	<p>Displaying <?=$Pagination->getItemsPerPage()?> items/page</p>
	<div class='align-center button-block'>
		<a class='btn link typcn typcn-arrow-back' href="/admin">Back to Admin Area</a>
	</div>

	<?=$Pagination?>
	<div class="responsive-table">
	<?=\App\Appearances::getPCGListHTML($Appearances)?>
	</div>
	<?=$Pagination?>
</div>
