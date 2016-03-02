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

<script>var Color = '<?=$Color?>', color = '<?=$color?>', EQG = <?=$EQG?'true':'false'?>;</script>
<?php if (PERM('inspector')){ ?>
<script>var TAG_TYPES_ASSOC = <?=JSON::Encode($TAG_TYPES_ASSOC)?>, MAX_SIZE = '<?=get_max_upload_size()?>', HEX_COLOR_PATTERN = <?=rtrim(HEX_COLOR_PATTERN,'u')?>;</script>
<?php } ?>
</div>
