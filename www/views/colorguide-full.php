<div id="content">
	<h1>Complete <?=$EQG?'EQG Character':'Pony'?> List</h1>
	<p>Sorted <?php
		if (!$EQG){
	?><select id="sort-by" data-baseurl="/<?=$color?>guide<?=($EQG?'/eqg':'')?>/full">
		<option value='alphabetically'<?=$GuideOrder?'':' selected'?>>alphabetcally</option>
		<option value=''<?=$GuideOrder?' selected':''?>>by relevance</option>
	</select><?php
		}
		else echo 'alphabetcially';
	?></p>

	<p class='align-center links'>
		<a class='btn darkblue typcn typcn-arrow-back' href="/<?=$color?>guide<?=$EQG?'/eqg':''?>">Back to <?=($EQG?'EQG ':'').$Color?> Guide</a>
<?php if (PERM('inspector') && !$EQG){ ?>
		<button class='darkblue typcn typcn-arrow-unsorted' id="guide-reorder"<?=!$GuideOrder?' disabled':''?>>Re-order</button>
<?php } ?>
		<a class='btn blue typcn typcn-world' href="/<?=$color?>guide<?=($EQG?'':'/eqg')?>/full">List of <?=$EQG?'Ponies':'Equestria Girls'?></a>
		<a class='btn darkblue typcn typcn-tags' href="/<?=$color?>guide/tags">Tags</a>
		<a class='btn darkblue typcn typcn-warning' href="/<?=$color?>guide/changes">Major Changes</a>
	</p>

	<?=render_full_list_html($Appearances, $GuideOrder)?>
</div>

<?  $export = array(
		'Color' => $Color,
		'color' => $color,
		'EQG' => $EQG,
	);
	if (PERM('inspector'))
		$export = array_merge($export,array(
			'TAG_TYPES_ASSOC' => $TAG_TYPES_ASSOC,
			'MAX_SIZE' => get_max_upload_size(),
			'HEX_COLOR_PATTERN' => $HEX_COLOR_PATTERN,
		));
	ExportVars($export);
