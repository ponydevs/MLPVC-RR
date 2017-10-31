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
		We add characters based on demand, please <a class="send-feedback">let us know</a> if you'd like us to make a guide for a character.<br>
		<small>Alternatively, use the old color guides: <a href="https://sta.sh/0kic0ngp3fy">Pony</a> / <a href="http://fav.me/d7120l1">EQG</a></small><br>
		Looking for this information in a machine-readable format? <a href="<?=$jsonExport = CoreUtils::cachedAssetLink('mlpvc-colorguide','dist','json')?>" target="_blank" download="mlpvc-colorguide.json">JSON</a> (updated <?=\App\Time::tag((int) explode('?',$jsonExport)[1])?>)
	</p>
	<p class='align-center links'>
<?  if (Permission::sufficient('staff')){ ?>
		<button class='green typcn typcn-plus' id="new-appearance-btn">Add new <?=$EQG?'Character':'Pony'?></button>
<?	} ?>
		<a class='btn link typcn typcn-world' href="/cg<?=$EQG?'':'/eqg'?>/1">View <?=$EQG?'Ponies':'Equestria Girls'?></a>
		<a class='btn link typcn typcn-th-menu' href="/cg<?=$EQG?'/eqg':''?>/full">Full List of <?=$EQG?'Equestria Girls':'Ponies'?></a>
		<a class='btn link typcn typcn-arrow-forward' href="/cg/blending">Blending Calculator</a>
		<a class='btn link typcn typcn-pipette' href="/cg/picker">Color Picker</a>
		<a class='btn link typcn typcn-tags' href="/cg/tags">Tags</a>
		<a class='btn link typcn typcn-warning' href="/cg/changes">Major Changes</a>
<?  if (Permission::sufficient('staff')){ ?>
		<button class='blue typcn typcn-adjust-contrast cg-sprite-colors'>Sprite Color Checkup</button>
<?  } ?>
<?  if (Permission::sufficient('developer')){ ?>
		<button class='blue typcn typcn-download cg-export'>Export</button>
		<button class='blue typcn typcn-document cg-reindex'>Re-index</button>
<?  } ?>
	</p>
<?  $Universal = \App\Models\Appearance::find(0);
	if (!empty($Universal))
		echo "<ul id='universal' class='appearance-list'>".Appearances::getHTML([$Universal], NOWRAP).'</ul>'; ?>
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
	if (Permission::sufficient('staff')){
		global $HEX_COLOR_REGEX;
		$export = array_merge($export, [
			'TAG_TYPES_ASSOC' => Tags::TAG_TYPES,
			'MAX_SIZE' => CoreUtils::getMaxUploadSize(),
			'HEX_COLOR_PATTERN' => $HEX_COLOR_REGEX,
		]);
	}
	echo CoreUtils::exportVars($export); ?>
