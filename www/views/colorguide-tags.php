<div id=content>
	<h1><?=$heading?></h1>
	<p>Displaying <?=$ItemsPerPage?> items/page</p>
	<p class=align-center>
		<a class='btn darkblue typcn typcn-arrow-back' href="/colorguide">Back to Color Guide</a>
	</p>
	<?=$Pagination = get_pagination_html('colorguide/tags', $Page, $MaxPages)?>
	<table id="tags">
		<thead><?=$thead = <<<HTML
			<tr>
				<th class="tid">ID</th>
				<th class="name" colspan=2>Name</th>
				<th class="title">Description</th>
				<th class="type">Type</th>
				<th class="uses">Uses</th>
			</tr>
HTML;
?></thead>
		<?=get_taglist_html($Tags)?>
		<tfoot><?=$thead?></tfoot>
	</table>
	<?=$Pagination?>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>

<script>var Color = '<?=$Color?>', color = '<?=$color?>', TAG_TYPES_ASSOC = <?=json_encode($TAG_TYPES_ASSOC)?>;</script>
