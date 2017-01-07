<?php
use App\CoreUtils;
use App\Permission;
use App\Appearances;
use App\Tags;
/** @var $heading string */
/** @var $EQG bool */
/** @var $elasticAvail bool */
/** @var $Pagination \App\Pagination */
/** @var $Ponies array */ ?>
<div id="content">
	<h1><?=$heading?></h1>
	<p>A searchable list of character <?=$color?>s from the <?=$EQG?'movies':'show'?></p>
	<p class="align-center">
		If you can’t find a character here, check the old guides: <a href="https://sta.sh/0kic0ngp3fy">Pony</a> / <a href="http://fav.me/d7120l1">EQG</a><br>
		Looking for this information in a machine-readable format? <a href="/dist/mlpvc-colorguide.json" target="_blank" download="mlpvc-colorguide.json">JSON</a></p>
<?  if (Permission::sufficient('staff')){ ?>
	<div class="notice warn tagediting">
		<label>Limited editing</label>
		<p>Editing tags or colors from the guide page does not work on mobile devices. If you want to edit those, please go the appearance’s page.</p>
	</div>
<?  }
	$Universal = $Database->where('id',0)->getOne('appearances');
	if (!empty($Universal))
		echo "<ul id='universal' class='appearance-list'>".Appearances::getHTML(array($Universal), NOWRAP)."</ul>"; ?>
	<p class='align-center links'>
<?  if (Permission::sufficient('staff')){ ?>
		<button class='green typcn typcn-plus' id="new-appearance-btn">Add new <?=$EQG?'Character':'Pony'?></button>
<?  } ?>
		<a class='btn blue typcn typcn-world' href="/cg<?=$EQG?'':'/eqg'?>/1">View <?=$EQG?'Ponies':'Equestria Girls'?></a>
		<a class='btn darkblue typcn typcn-th-menu' href="/cg<?=$EQG?'/eqg':''?>/full">Full List of <?=$EQG?'Equestria Girls':'Ponies'?></a>
		<a class='btn darkblue typcn typcn-arrow-forward' href="/blending">Blending Calculator</a>
		<a class='btn darkblue typcn typcn-tags' href="/cg/tags">Tags</a>
		<a class='btn darkblue typcn typcn-warning' href="/cg/changes">Major Changes</a>
<?  if (Permission::sufficient('developer')){ ?>
		<button class='darkblue typcn typcn-download cg-export'>Export</button>
		<button class='darkblue typcn typcn-document cg-reindex'>Re-index</button>
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
	else echo CoreUtils::notice('warn','<span class="typcn typcn-warning"></span> <strong>ElasticSearch server is down!</strong> Please <a class="send-feedback">let us know</a>, and in the meantime, use the <a class="btn darkblue typcn typcn-th-menu" href="/cg'.($EQG?'/eqg':'').'/full">Full List</a> to find appearances faster. Sorry for the inconvenience.',true); ?>
	<?=$Pagination->HTML . Appearances::getHTML($Ponies) . $Pagination->HTML?>
</div>

<?  $export = array(
		'Color' => $Color,
		'color' => $color,
		'EQG' => $EQG,
		'AppearancePage' => false,
	);
	if (Permission::sufficient('staff'))
		$export = array_merge($export, array(
			'TAG_TYPES_ASSOC' => Tags::$TAG_TYPES_ASSOC,
			'MAX_SIZE' => CoreUtils::getMaxUploadSize(),
			'HEX_COLOR_PATTERN' => $HEX_COLOR_REGEX,
		));
	echo CoreUtils::exportVars($export); ?>
