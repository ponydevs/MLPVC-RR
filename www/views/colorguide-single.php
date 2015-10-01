<div id="content">
	<?=(file_exists(APPATH.($relpath="img/cg/{$Appearance['id']}.png")))?"<img src='/$relpath' alt='{$Appearance['id']}'>":''?>
	<h1><?=$heading?></h1>
	<p>from the MLP-VectorClub <a href="/colorguide"><?=$Color?> Guide</a></p>
<?  if (!empty($Appearance['notes'])){ ?>
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
		<ul id="changes">
<?php   $seeInitiator = PERM('inspector');
		$UserCache = array();
		foreach ($Changes as $c){
			$initiator = '';
			if ($seeInitiator){
				$UserID = $c['initiator'];
				if (empty($UserCache[$UserID])){
					$UserCache[$UserID] = get_user($UserID);
				}
				$User = $UserCache[$UserID];
				$initiator = " by <a href='/u/{$User['name']}'>{$User['name']}</a>";
			}
			echo "<li>{$c['reason']} - ".timetag($c['timestamp'])."$initiator</li>";
		}?>
		</ul>
	</section>
<?  } ?>
	<ul id
</div>

<script>var Color = '<?=$Color?>', color = '<?=$color?>';</script>
