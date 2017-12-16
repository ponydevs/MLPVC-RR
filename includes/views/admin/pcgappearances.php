<?php
/** @var $Pagination App\Pagination */
/** @var $Appearances App\Models\Appearance[] */ ?>
<div id="content" class="section-container">
	<h1><?=$heading?></h1>
	<p>Displaying <?=$Pagination->itemsPerPage?> items/page</p>
	<p class='align-center links'>
		<a class='btn link typcn typcn-arrow-back' href="/admin">Back to Admin Area</a>
	</p>

	<?=$Pagination?>
	<div class="responsive-table">
	<?=\App\Appearances::getPCGListHTML($Appearances)?>
	</div>
	<?=$Pagination?>
</div>
