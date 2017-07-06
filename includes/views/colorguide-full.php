<?php
use App\Permission;
use App\CGUtils;
use App\CoreUtils;
use App\Tags;
/** @var $EQG bool */
/** @var $GuideOrder bool */
/** @var $Appearances array */ ?>
<div id="content">
	<h1>Complete <?=$EQG?'EQG Character':'Pony'?> List</h1>
	<p>Sorted <?php
		if (!$EQG){
	?><select id="sort-by" data-baseurl="/cg<?=($EQG?'/eqg':'')?>/full">
		<option value='alphabetically'<?=$GuideOrder?'':' selected'?>>alphabetically</option>
		<option value=''<?=$GuideOrder?' selected':''?>>by relevance</option>
	</select><?php
		}
		else echo 'alphabetcially';
	?></p>

	<p class='align-center links'>
		<a class='btn link typcn typcn-arrow-back' href="/cg<?=$EQG?'/eqg':''?>">Back to <?=($EQG?'EQG ':'')?>Color Guide</a>
<?php if (Permission::sufficient('staff') && !$EQG){ ?>
		<button class='darkblue typcn typcn-arrow-unsorted' id="guide-reorder"<?=!$GuideOrder?' disabled':''?>>Re-order</button>
		<button class='red typcn typcn-times hidden' id="guide-reorder-cancel">Cancel</button>
<?php } ?>
		<a class='btn link typcn typcn-world' href="/cg<?=($EQG?'':'/eqg')?>/full">List of <?=$EQG?'Ponies':'Equestria Girls'?></a>
		<a class='btn link typcn typcn-tags' href="/cg/tags">Tags</a>
		<a class='btn link typcn typcn-warning' href="/cg/changes">Major Changes</a>
	</p>

	<?=CGUtils::getFullListHTML($Appearances, $GuideOrder)?>
</div>

<?  $export = [
		'EQG' => $EQG,
];
	if (Permission::sufficient('staff'))
		$export = array_merge($export, [
			'TAG_TYPES_ASSOC' => Tags::$TAG_TYPES_ASSOC,
			'MAX_SIZE' => CoreUtils::getMaxUploadSize(),
			'HEX_COLOR_PATTERN' => $HEX_COLOR_REGEX,
		]);
	echo CoreUtils::exportVars($export);
