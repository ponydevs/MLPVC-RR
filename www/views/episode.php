<?php

	if ($do === 'da-auth' && isset($err)){
		echo Notice('fail',"There was a(n) <strong>$err</strong> error while trying to authenticate with DeviantArt".(isset($OAUTH_RESPONSE[$err])?"; {$OAUTH_RESPONSE[$err]}":'.').(!empty($errdesc)?"\n\nAdditional details: $errdesc":''),true) ?>
<script>try{history.replaceState('',{},'/')}catch(e){}</script>
<?  } ?>
<div id="content">
<?  if (!empty($CurrentEpisode)){
		$isMovie = $CurrentEpisode['season'] === 0;?>
	<h1><?=format_episode_title($CurrentEpisode)?></h1>
	<p>Vector Requests & Reservations</p>
<?php   if (PERM('inspector')){ ?>
	<p class="align-center"><em><?=$isMovie?'Movie':'Episode'?> added by <?=profile_link(get_user($CurrentEpisode['posted_by'])).' '.timetag($CurrentEpisode['posted'])?></em></p>
<?php   }
	echo render_ep_video($CurrentEpisode); ?>
	<section class="about-res">
		<h2>What Vector Reservations Are<?=PERM('inspector')?'<button class="blue typcn typcn-pencil" id="edit-about_reservations">Edit</button>':''?></h2>
		<?=get_config_var('about_reservations')?>
	</section>
	<section class="rules">
		<h2>Reservation Rules<?=PERM('inspector')?'<button class="orange typcn typcn-pencil" id="edit-reservation_rules">Edit</button>':''?></h2>
		<?=get_config_var('reservation_rules')?>
	</section>
<?php   $EpTagIDs = get_ep_tag_ids($CurrentEpisode);
		if (!empty($EpTagIDs)){
			$TaggedAppearances = $CGDb->rawQuery(
				"SELECT p.id, p.label
				FROM tagged t
				LEFT JOIN appearances p ON t.ponyid = p.id
				WHERE t.tid IN (".implode(',',$EpTagIDs).")
				ORDER BY p.label");

			if (!empty($TaggedAppearances)){ ?>
	<section class="appearances">
		<h2>Related <a href="/colorguide"><?=$Color?> Guide</a> <?=plur('page', count($TaggedAppearances))?></h2>
		<p><?php
				$HTML = '';
				foreach ($TaggedAppearances as $p)
					$HTML .= "<a href='/colorguide/appearance/{$p['id']}'>{$p['label']}</a>, ";
				echo rtrim($HTML,', ');
		?></p>
	</section>
<?php		}
		}
		if (PERM('inspector')){ ?>
	<section class="admin">
		<h2>Administration area</h2>
		<p class="align-center">
			<button id="video" class="typcn typcn-video large darkblue">Set video links</button>
		</p>
	</section>
<?php   }
		echo reservations_render($Reservations);
		echo requests_render($Requests);
		$export = array(
			'SEASON' => $CurrentEpisode['season'],
			'EPISODE' => $CurrentEpisode['episode'],
		);
		if (PERM('developer'))
			$export['USERNAME_REGEX'] = $USERNAME_REGEX;
		if ($signedIn)
			$export['FULLSIZE_MATCH_REGEX'] = $FULLSIZE_MATCH_REGEX;
		ExportVars($export);
	} else { ?>
	<h1>There's nothing here yet&hellip;</h1>
	<p>&hellip;but there will be!</p>

<?php   if (PERM('inspector'))
			echo Notice('info','No episodes found',"To make the site functional, you must add an episode to the database first. Head on over to the <a href='/episodes'>Episodes</a> page and add one now!");
	} ?>
</div>
