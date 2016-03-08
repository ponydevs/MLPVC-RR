<div id="content">
	<h1><?=$heading?></h1>
	<p>A searchable list of character <?=$color?>s from the <?=$EQG?'movies':'show'?></p>
	<p class="align-center">If you can't find a character here, check the old gudes: <a href="https://sta.sh/0kic0ngp3fy">Pony</a> / <a href="http://fav.me/d7120l1">EQG</a></p>
<? if (PERM('inspector')){ ?>
	<div class="notice warn tagediting">
		<label>Limited editing</label>
		<p>Editing tags or colors from the guide page does not work on mobile devices. If you want to edit those, please go the appearance's page.</p>
	</div>
<? }
	$Universal = $CGDb->where('id',0)->getOne('appearances');
	if (!empty($Universal))
		echo "<ul id='universal' class='appearance-list'>".render_ponies_html(array($Universal), NOWRAP)."</ul>"; ?>
	<p class='align-center links'>
<? if (PERM('inspector')){ ?>
		<button class='green typcn typcn-plus' id="new-appearance-btn">Add new <?=$EQG?'Character':'Pony'?></button>
<? } ?>
		<a class='btn blue typcn typcn-world' href="/<?=$color?>guide<?=$EQG?'':'/eqg'?>/1">View <?=$EQG?'Ponies':'Equestria Girls'?></a>
		<a class='btn darkblue typcn typcn-th-menu' href="/<?=$color?>guide<?=$EQG?'/eqg':''?>/full">Full List of <?=$EQG?'Equestria Girls':'Ponies'?></a>
		<a class='btn darkblue typcn typcn-arrow-forward' href="/blending">Blending Calculator</a>
		<a class='btn darkblue typcn typcn-tags' href="/<?=$color?>guide/tags">Tags</a>
		<a class='btn darkblue typcn typcn-warning' href="/<?=$color?>guide/changes">Major Changes</a>
	</p>

	<form id="search-form"><input name="q" <?=!empty($_GET['q'])?" value='".apos_encode($_GET['q'])."'":''?> title='Search'> <button class='blue typcn typcn-zoom'></button><button type='reset' class='orange typcn typcn-times' title='Clear'<?=empty($_GET['q'])?'disabled':''?>></button><p>Enter tags separated by commas. You can search for up to 6 tags at a time.</p></form>
	<?=$Pagination?>
	<?=render_ponies_html($Ponies)?>
	<?=$Pagination?>
</div>

<?  $export = array(
		'Color' => $Color,
		'color' => $color,
		'EQG' => $EQG,
		'AppearancePage' => false,
	);
	if (PERM('inspector'))
		$export = array_merge($export, array(
			'TAG_TYPES_ASSOC' => $TAG_TYPES_ASSOC,
			'MAX_SIZE' => get_max_upload_size(),
			'HEX_COLOR_PATTERN' => $HEX_COLOR_PATTERN,
		));
	ExportVars($export); ?>
