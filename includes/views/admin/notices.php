<?php
/** @var string $heading */
/** @var \App\Models\Notice[] $notices */
/** @var \App\Pagination $Pagination */ ?>
<div id="content">
	<h1><?=$heading?></h1>
	<p>Manage site-wide notices</p>
	<div class='align-center button-block'>
		<button class="green typcn typcn-plus" id="create-notice" disabled>Create notice</button>
		<a class='btn link typcn typcn-arrow-back' href="/admin">Back to Admin Area</a>
	</div>

	<div class="notice info align-center">I was too lazy to add editing controls to this page, so for now this page is read-only, but it will be finished eventually&trade;<br>&mdash;The developer</div>

	<?=$Pagination?>
	<?=\App\CoreUtils::getNoticeListHTML($notices)?>
	<?=$Pagination?>
</div>

<?php
	echo \App\CoreUtils::exportVars([
		'PRINTABLE_ASCII_PATTERN' => PRINTABLE_ASCII_PATTERN
	]);
