<div id="content">
	<h1><?=$heading?></h1>
	<p>A searchable list of character <?=$color?>s from the <?=$EQG?'movies':'show'?></p>
	<p class="align-center">
		If you can't find a character here, check the old guides: <a href="https://sta.sh/0kic0ngp3fy">Pony</a> / <a href="http://fav.me/d7120l1">EQG</a><br>
		Looking for this information in a machine-readable format? <a href="https://raw.githubusercontent.com/<?=GITHUB_PROJECT_NAME?>/master/setup/mlpvc-colorguide.json" target="_blank" download="mlpvc-colorguide.json">JSON</a> / <a href="https://raw.githubusercontent.com/<?=GITHUB_PROJECT_NAME?>/master/setup/mlpvc-colorguide.pg.sql" target="_blank" download="mlpvc-colorguide.pg.sql">PgSQL</a></p>
<? if (Permission::Sufficient('staff')){ ?>
	<div class="notice warn tagediting">
		<label>Limited editing</label>
		<p>Editing tags or colors from the guide page does not work on mobile devices. If you want to edit those, please go the appearance's page.</p>
	</div>
<? }
	$Universal = $CGDb->where('id',0)->getOne('appearances');
	if (!empty($Universal))
		echo "<ul id='universal' class='appearance-list'>".\CG\Appearances::GetHTML(array($Universal), NOWRAP)."</ul>"; ?>
	<p class='align-center links'>
<? if (Permission::Sufficient('staff')){ ?>
		<button class='green typcn typcn-plus' id="new-appearance-btn">Add new <?=$EQG?'Character':'Pony'?></button>
<? } ?>
		<a class='btn blue typcn typcn-world' href="/<?=$color?>guide<?=$EQG?'':'/eqg'?>/1">View <?=$EQG?'Ponies':'Equestria Girls'?></a>
		<a class='btn darkblue typcn typcn-th-menu' href="/<?=$color?>guide<?=$EQG?'/eqg':''?>/full">Full List of <?=$EQG?'Equestria Girls':'Ponies'?></a>
		<a class='btn darkblue typcn typcn-arrow-forward' href="/blending">Blending Calculator</a>
		<a class='btn darkblue typcn typcn-tags' href="/<?=$color?>guide/tags">Tags</a>
		<a class='btn darkblue typcn typcn-warning' href="/<?=$color?>guide/changes">Major Changes</a>
<?  if (Permission::Sufficient('developer')){ ?>
		<button class='darkblue typcn typcn-download cg-export'>Export</button>
<?  } ?>
	</p>

	<form id="search-form"><input name="q" <?=!empty($_GET['q'])?" value='".CoreUtils::AposEncode($_GET['q'])."'":''?> title='Search'> <button class='blue typcn typcn-zoom'></button><button type='reset' class='orange typcn typcn-times' title='Clear'<?=empty($_GET['q'])?' disabled':''?>></button><p>Enter tags/names separated by commas. Force name-based search by using&nbsp;<strong>?</strong>&nbsp;or&nbsp;<strong>*</strong>. You may search using <em>up to 6</em> tokens at a time.</p></form>
	<?=$Pagination->HTML . \CG\Appearances::GetHTML($Ponies) . $Pagination->HTML?>
</div>

<?  $export = array(
		'Color' => $Color,
		'color' => $color,
		'EQG' => $EQG,
		'AppearancePage' => false,
	);
	if (Permission::Sufficient('staff'))
		$export = array_merge($export, array(
			'TAG_TYPES_ASSOC' => \CG\Tags::$TAG_TYPES_ASSOC,
			'MAX_SIZE' => CoreUtils::GetMaxUploadSize(),
			'HEX_COLOR_PATTERN' => $HEX_COLOR_PATTERN,
		));
	CoreUtils::ExportVars($export); ?>
