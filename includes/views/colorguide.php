<?php
use App\CoreUtils;
use App\Permission;
use App\Appearances;
use App\Tags;
/** @var $heading string */
/** @var $EQG bool */
/** @var $elasticAvail bool */
/** @var $Pagination \App\Pagination */
/** @var $Ponies array */
/** @var $Owner App\Models\User|array */ ?>
<div id="content">
	<h1><?=$heading?></h1>
	<p>A searchable list of character colors from the <?=$EQG?'movies':'show'?></p>
	<p class="align-center">
		If you can’t find a character here, check the old guides: <a href="https://sta.sh/0kic0ngp3fy">Pony</a> / <a href="http://fav.me/d7120l1">EQG</a><br>
		Looking for this information in a machine-readable format? <a href="<?=CoreUtils::cachedAsset('mlpvc-colorguide','dist','json')?>" target="_blank" download="mlpvc-colorguide.json">JSON</a></p>
<?  if (Permission::sufficient('staff')){ ?>
	<div class="notice warn tagediting">
		<label>Limited editing</label>
		<p>Editing tags or colors from the guide page does not work on mobile devices. If you want to edit those, please go the appearance’s page.</p>
	</div>
<?  }
	$Universal = \App\Models\Appearance::find(0);
	if (!empty($Universal))
		echo "<ul id='universal' class='appearance-list'>".Appearances::getHTML([$Universal], NOWRAP).'</ul>'; ?>
	<p class='align-center links'>
<?  if (Permission::sufficient('staff')){ ?>
		<button class='green typcn typcn-plus' id="new-appearance-btn">Add new <?=$EQG?'Character':'Pony'?></button>
<?  } ?>
		<a class='btn link typcn typcn-world' href="/cg<?=$EQG?'':'/eqg'?>/1">View <?=$EQG?'Ponies':'Equestria Girls'?></a>
		<a class='btn link typcn typcn-th-menu' href="/cg<?=$EQG?'/eqg':''?>/full">Full List of <?=$EQG?'Equestria Girls':'Ponies'?></a>
		<a class='btn link typcn typcn-arrow-forward' href="/cg/blending">Blending Calculator</a>
		<a class='btn link typcn typcn-pipette' href="/cg/picker">Color Picker</a>
		<a class='btn link typcn typcn-tags' href="/cg/tags">Tags</a>
		<a class='btn link typcn typcn-warning' href="/cg/changes">Major Changes</a>
<?  if (Permission::sufficient('developer')){ ?>
		<button class='blue typcn typcn-download cg-export'>Export</button>
		<button class='blue typcn typcn-document cg-reindex'>Re-index</button>
<?  } ?>
	</p>
<? if ($elasticAvail){ ?>
	<form id="search-form">
		<input name="q" <?=!empty($_GET['q'])?" value='".CoreUtils::aposEncode($_GET['q'])."'":''?> title='Search'>
		<button type='submit'  class='blue'>Search</button>
		<button type='button' class='green typcn typcn-flash sanic-button' title="I'm feeling lucky"></button>
		<button type='reset' class='red typcn typcn-times' title='Clear'<?=empty($_GET['q'])?' disabled':''?>></button>
	</form>
<?  }
	else echo \App\CGUtils::getElasticUnavailableNotice($EQG); ?>
	<?=$Pagination . Appearances::getHTML($Ponies) . $Pagination?>
</div>

<?  $export = [
		'EQG' => $EQG,
		'AppearancePage' => false,
		'PersonalGuide' => $Owner->name ?? false,
];
	if (Permission::sufficient('staff'))
		$export = array_merge($export, [
			'TAG_TYPES_ASSOC' => Tags::TAG_TYPES,
			'MAX_SIZE' => CoreUtils::getMaxUploadSize(),
			'HEX_COLOR_PATTERN' => $HEX_COLOR_REGEX,
		]);
	echo CoreUtils::exportVars($export); ?>
