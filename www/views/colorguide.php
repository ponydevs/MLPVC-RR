<div id="content">
	<h1><?=$heading?></h1>
	<p>A searchable list of character <?=$color?>s from the <?=$EQG?'movies':'show'?></p>
<? if (PERM('inspector')){ ?>
	<div class="notice warn tagediting">
		<label>Some features are unavailable</label>
		<p>Because you seem to be using a mobile device, editing tags & colors may not work, as it requires you to right-click. If you want to do either of these, please do so from a computer.</p>
	</div>
<? }
	$Universal = $CGDb->where('id',0)->getOne('appearances');
	if (!empty($Universal))
		echo "<ul id='universal' class='appearance-list'>".render_ponies_html(array($Universal), NOWRAP)."</ul>"; ?>
	<p class='align-center links'>
<? if (PERM('inspector')){ ?>
		<button class='green typcn typcn-plus' id="new-appearance-btn">Add new <?=$EQG?'Character':'Pony'?></button>
<? } ?>
		<a class='btn blue typcn typcn-world' href="/<?=$color?>guide<?=($EQG?'':'/eqg')?>/1">View <?=$EQG?'Ponies':'Equestria Girls'?></a>
		<a class='btn darkblue typcn typcn-th-menu' href="/<?=$color?>guide/full">Full list of <?=$EQG?'Equestria Girls':'Ponies'?></a>
		<a class='btn darkblue typcn typcn-tags' href="/<?=$color?>guide/tags">List of tags</a>
		<a class='btn darkblue typcn typcn-warning' href="/<?=$color?>guide/changes">List of major changes</a>
	</p>

<?  if (PERM('user')){ ?>
	<form id="search-form"><input name="q" <?=!empty($_GET['q'])?" value='".apos_encode($_GET['q'])."'":''?> title='Search'> <button class='blue typcn typcn-zoom'></button><button type='reset' class='orange typcn typcn-times' title='Clear'<?=empty($_GET['q'])?'disabled':''?>></button><p>Enter tags separated by commas. You can search for up to 6 tags at a time.</p></form>
<?  }
	else echo Notice('info',"<span class='typcn typcn-info-large'></span> Please sign in with the button in the sidebar to use the search feature.</p>",true); ?>
	<?=$Pagination?>
	<?=render_ponies_html($Ponies)?>
	<?=$Pagination?>
</div>

<script>var Color = '<?=$Color?>', color = '<?=$color?>', EQG = <?=$EQG?'true':'false'?>;</script>
<?php if (PERM('inspector')){ ?>
<script>var TAG_TYPES_ASSOC = <?=json_encode($TAG_TYPES_ASSOC)?>, MAX_SIZE = '<?=get_max_upload_size()?>', HEX_COLOR_PATTERN = <?=rtrim(HEX_COLOR_PATTERN,'u')?>;</script>
<?php } ?>
</div>
