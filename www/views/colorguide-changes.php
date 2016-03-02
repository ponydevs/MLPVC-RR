<div id="content">
	<h1><?=$heading?></h1>
	<p>Displaying <?=$ItemsPerPage?> items/page</p>
	<p class='align-center links'>
		<a class='btn darkblue typcn typcn-arrow-back' href="/<?=$color?>guide">Back to <?=$Color?> Guide</a>
		<a class='btn darkblue typcn typcn-tags' href="/<?=$color?>guide/tags">List of tags</a>
	</p>
	<?=$Pagination?>
	<?=render_changes_html($Changes, WRAP, SHOW_APPEARANCE_NAMES)?>
	<?=$Pagination?>
</div>

<?  ExportVars(array(
		'Color' => $Color,
		'color' => $color,
		'TAG_TYPES_ASSOC' => $TAG_TYPES_ASSOC,
	));
