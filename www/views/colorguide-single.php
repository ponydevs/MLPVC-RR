<div id="content">
	<?=get_sprite_html($Appearance)?>
	<h1><?=$heading?></h1>
	<p>from the MLP-VectorClub <a href="/<?=$color?>guide"><?=$Color?> Guide</a></p>

<?php
	$RenderPath = APPATH."img/cg_render/{$Appearance['id']}.png";
	$FileModTime = '?t='.(file_exists($RenderPath) ? filemtime($RenderPath) : time()); ?>
	<div id="p<?=$Appearance['id']?>">
		<div class='align-center'>
			<a class='darkblue btn typcn typcn-image' href='/<?=$color?>guide/appearance/<?="{$Appearance['id']}.png$FileModTime"?>' target='_blank'>View as PNG</a>
<?  if (PERM('inspector')){ ?>
			<button class='blue edit typcn typcn-pencil'>Edit metadata</button>
			<button class='red delete typcn typcn-trash'>Delete apperance</button>
<?  } ?>
		</div>

<?  if (!empty($Changes)){ ?>
		<section>
			<label><span class='typcn typcn-warning'></span>List of major changes</label>
			<?=render_changes_html($Changes)?>
		</section>
<?  }
	if ($CGDb->where('ponyid',$Appearance['id'])->has('tagged') || PERM('inspector')){ ?>
		<section id="tags">
			<label><span class='typcn typcn-tags'></span>Tags</label>
			<div class='tags'><?=get_tags_html($Appearance['id'],NOWRAP)?></div>
		</section>
<?php
	}
	echo get_episode_appearances($Appearance['id']);
	if (!empty($Appearance['notes'])){ ?>
		<section>
			<label><span class='typcn typcn-info-large'></span>Additional notes</label>
			<p id="notes"><?=$Appearance['notes']?></p>
		</section>
<?  }
	if (!empty($Appearance['cm_favme'])){
		$CM = da_cache_deviation($Appearance['cm_favme']); ?>
		<section>
			<label>Approved cutie mark vector</label>
			<a id="pony-cm" href="http://fav.me/<?=$CM['id']?>">
				<img src="<?=$CM['preview']?>" alt="<?=$CM['title']?>">
			</a>
		</section>
<?  } ?>
		<ul id="colors" class="colors"><?
		foreach (get_cgs($Appearance['id']) as $cg)
			echo get_cg_html($cg, WRAP, NO_COLON, OUTPUT_COLOR_NAMES); ?></ul>
	</div>
</div>

<?  $export = array(
		'Color' => $Color,
		'color' => $color,
		'EQG' => $EQG,
		'AppearancePage' => true,
	);
	if (PERM('inspector'))
		$export = array_merge($export, array(
			'TAG_TYPES_ASSOC' => $TAG_TYPES_ASSOC,
			'MAX_SIZE' => get_max_upload_size(),
			'HEX_COLOR_PATTERN' => rtrim(HEX_COLOR_PATTERN,'u'),
		));
	ExportVars($export); ?>
