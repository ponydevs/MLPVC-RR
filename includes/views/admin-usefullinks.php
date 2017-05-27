<?php /** @var string $heading */ ?>
<div id="content">
	<h1><?=$heading?></h1>
	<p>Add, reorder and remove useful links</p>
	<div class='align-center links'>
		<button class="green typcn typcn-plus" id="add-link">Add link</button>
		<button class='blue typcn typcn-arrow-unsorted' id="reorder-links">Re-order links</button>
		<a class='btn link typcn typcn-arrow-back' href="/admin">Back to Admin Area</a>
	</div>

	<section class="useful-links">
		<div><?=\App\CoreUtils::getSidebarUsefulLinksListHTML()?></div>
	</section>
</div>

<?php
	echo \App\CoreUtils::exportVars(array(
		'ROLES_ASSOC' => \App\Permission::ROLES_ASSOC,
		'PRINTABLE_ASCII_PATTERN' => PRINTABLE_ASCII_PATTERN
	));
