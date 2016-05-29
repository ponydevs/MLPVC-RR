<div id="content">
	<h1>Complete <?=$EQG?'EQG Character':'Pony'?> List</h1>
	<p>Sorted <?php
		if (!$EQG){
	?><select id="sort-by" data-baseurl="/cg<?=($EQG?'/eqg':'')?>/full">
		<option value='alphabetically'<?=$GuideOrder?'':' selected'?>>alphabetcally</option>
		<option value=''<?=$GuideOrder?' selected':''?>>by relevance</option>
	</select><?php
		}
		else echo 'alphabetcially';
	?></p>

	<p class='align-center links'>
		<a class='btn darkblue typcn typcn-arrow-back' href="/cg<?=$EQG?'/eqg':''?>">Back to <?=($EQG?'EQG ':'').$Color?> Guide</a>
<?php if (Permission::Sufficient('staff') && !$EQG){ ?>
		<button class='darkblue typcn typcn-arrow-unsorted' id="guide-reorder"<?=!$GuideOrder?' disabled':''?>>Re-order</button>
<?php } ?>
		<a class='btn blue typcn typcn-world' href="/cg<?=($EQG?'':'/eqg')?>/full">List of <?=$EQG?'Ponies':'Equestria Girls'?></a>
		<a class='btn darkblue typcn typcn-tags' href="/cg/tags">Tags</a>
		<a class='btn darkblue typcn typcn-warning' href="/cg/changes">Major Changes</a>
	</p>

	<?=CGUtils::GetFullListHTML($Appearances, $GuideOrder)?>
</div>

<?  $export = array(
		'Color' => $Color,
		'color' => $color,
		'EQG' => $EQG,
	);
	if (Permission::Sufficient('staff'))
		$export = array_merge($export,array(
			'TAG_TYPES_ASSOC' => \CG\Tags::$TAG_TYPES_ASSOC,
			'MAX_SIZE' => CoreUtils::GetMaxUploadSize(),
			'HEX_COLOR_PATTERN' => $HEX_COLOR_REGEX,
		));
	CoreUtils::ExportVars($export);
