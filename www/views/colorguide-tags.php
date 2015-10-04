<div id="content">
	<h1><?=$heading?></h1>
	<p>Displaying <?=$ItemsPerPage?> items/page</p>
	<p class='align-center links'>
		<a class='btn darkblue typcn typcn-arrow-back' href="/<?=$color?>guide">Back to <?=$Color?> Guide</a>
	</p>
	<?=$Pagination?>
	<table id="tags">
		<thead><?php
	$cspan = PERM('inspector') ? '" colspan="2' : '';
	$refresher = PERM('inspector') ? " <button class='typcn typcn-arrow-sync refresh-all' title='Refresh usage data on this page'></button>" : '';
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
	<?=$Pagination?>
</div>

<script>var Color = '<?=$Color?>', color = '<?=$color?>', TAG_TYPES_ASSOC = <?=json_encode($TAG_TYPES_ASSOC)?>;</script>
