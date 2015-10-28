<div id="content">
	<?=(file_exists(APPATH.($relpath="img/cg/{$Appearance['id']}.png")))?"<img src='/$relpath' alt='{$Appearance['id']}'>":''?>
	<h1><?=$heading?></h1>
	<p>from the MLP-VectorClub <a href="/<?=$color?>guide"><?=$Color?> Guide</a></p>
<?php
	$RenderPath = APPATH."img/cg_render/{$Appearance['id']}.png";
	$FileModTime = '?t='.(file_exists($RenderPath) ? filemtime($RenderPath) : time());
	echo "<div class='align-center'><a class='darkblue btn typcn typcn-image' href='/{$color}guide/appearance/{$Appearance['id']}.png$FileModTime' target='_blank'>View as PNG</a></div>";

	if (!empty($Appearance['notes'])){ ?>
	<section>
		<label><span class='typcn typcn-info-large'></span>Additional notes</label>
		<p><?=$Appearance['notes']?></p>
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
	<ul id="colors"><?
	foreach (get_cgs($Appearance['id']) as $cg)
		echo get_cg_html($cg, WRAP, NO_COLON); ?></ul>
<?  if (!empty($Changes)){ ?>
	<section>
		<label><span class='typcn typcn-warning'></span>List of major changes</label>
		<?=render_changes_html($Changes)?>
	</section>
<?  } ?>
</div>

<script>var Color = '<?=$Color?>', color = '<?=$color?>';</script>
