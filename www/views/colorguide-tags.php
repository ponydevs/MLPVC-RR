<div id="content">
	<h1><?=$heading?></h1>
	<p>Displaying <?=$Pagination->itemsPerPage?> items/page</p>
	<p class='align-center links'>
		<a class='btn darkblue typcn typcn-arrow-back' href="/<?=$color?>guide">Back to <?=$Color?> Guide</a>
		<a class='btn darkblue typcn typcn-warning' href="/<?=$color?>guide/changes">Major Changes</a>
	</p>
	<?=$Pagination->HTML?>
	<table id="tags">
		<thead><?php
	$cspan = Permission::Sufficient('inspector') ? '" colspan="2' : '';
	$refresher = Permission::Sufficient('inspector') ? " <button class='typcn typcn-arrow-sync refresh-all' title='Refresh usage data on this page'></button>" : '';
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
		<?=get_taglist_html($Tags)?>
		<tfoot><?=$thead?></tfoot>
	</table>
	<?=$Pagination->HTML?>
</div>

<?  CoreUtils::ExportVars(array(
		'Color' => $Color,
		'color' => $color,
		'TAG_TYPES_ASSOC' => $TAG_TYPES_ASSOC,
	));
