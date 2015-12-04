<div id="content">
	<h1>Full <?=$EQG?'character':'pony'?> list</h1>
	<p>List of appearances sorted <?=$GuideOrder?'by relevance':'alphabetcally'?></p>

	<p class='align-center links'>
		<a class='btn blue typcn typcn-<?=$GuideOrder?'sort-alphabetically':'arrow-unsorted'?>' href="/<?=$color?>guide<?=($EQG?'/eqg':'')?>/full<?=$GuideOrder?'':'?guide-order'?>"><?=$GuideOrder?'Order alphabetcally':'Order by relevance'?></a>
		<a class='btn blue typcn typcn-world' href="/<?=$color?>guide<?=($EQG?'':'/eqg')?>/full<?=$GuideOrder?'?guide-order':''?>"><?=$EQG?'List of Ponies':'List of Equestria Girls'?></a>
		<a class='btn darkblue typcn typcn-tags' href="/<?=$color?>guide/tags">List of tags</a>
		<a class='btn darkblue typcn typcn-warning' href="/<?=$color?>guide/changes">List of major changes</a>
	</p>

	<?=render_full_list_html($Appearances, $GuideOrder)?>
</div>

<script>var Color = '<?=$Color?>', color = '<?=$color?>', EQG = <?=$EQG?'true':'false'?>;</script>
<?php if (PERM('inspector')){ ?>
<script>var TAG_TYPES_ASSOC = <?=json_encode($TAG_TYPES_ASSOC)?>, MAX_SIZE = '<?=get_max_upload_size()?>', HEX_COLOR_PATTERN = <?=rtrim(HEX_COLOR_PATTERN,'u')?>;</script>
<?php } ?>
</div>
