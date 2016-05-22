<?php

	if ($do === 'da-auth' && isset($err)){
		echo CoreUtils::Notice('fail',"There was a(n) <strong>$err</strong> error while trying to authenticate with DeviantArt".(isset(DeviantArt::$OAUTH_RESPONSE[$err])?'; '.DeviantArt::$OAUTH_RESPONSE[$err]:'.').(!empty($errdesc)?"\n\nAdditional details: $errdesc":''),true) ?>
<script>try{history.replaceState('',{},'/')}catch(e){}</script>
<?  } ?>
<div id="content">
<?  if (!empty($CurrentEpisode)){
		$isMovie = $CurrentEpisode['season'] === 0;?>
	<h1><?=Episode::FormatTitle($CurrentEpisode)?></h1>
	<p>Vector Requests & Reservations</p>
<?php   if (Permission::Sufficient('staff')){ ?>
	<p class="align-center"><em><?=$isMovie?'Movie':'Episode'?> added by <?=User::GetProfileLink(User::Get($CurrentEpisode['posted_by'])).' '.Time::Tag($CurrentEpisode['posted'])?></em></p>
<?php   }
	echo Episode::RenderVideos($CurrentEpisode); ?>
	<section class="about-res">
		<h2>What Vector Reservations Are<?=Permission::Sufficient('staff')?'<button class="blue typcn typcn-pencil" id="edit-about_reservations">Edit</button>':''?></h2>
		<?=GlobalSettings::Get('about_reservations')?>
	</section>
	<section class="rules">
		<h2>Reservation Rules<?=Permission::Sufficient('staff')?'<button class="orange typcn typcn-pencil" id="edit-reservation_rules">Edit</button>':''?></h2>
		<?=GlobalSettings::Get('reservation_rules')?>
	</section>
<?php   echo Episode::GetAppearancesSectionHTML($CurrentEpisode);
		if (Permission::Sufficient('staff')){ ?>
	<section class="admin">
		<h2>Administration area</h2>
		<p class="align-center">
			<button id="video" class="typcn typcn-pencil large darkblue">Video links</button>
			<button id="cg-relations" class="typcn typcn-pencil large darkblue">Guide relations</button>
		</p>
	</section>
<?php   }
		echo Posts::GetReservationsSection($Reservations);
		echo Posts::GetRequestsSection($Requests);
		$export = array(
			'SEASON' => $CurrentEpisode['season'],
			'EPISODE' => $CurrentEpisode['episode'],
		);
		if (Permission::Sufficient('developer'))
			$export['USERNAME_REGEX'] = $USERNAME_REGEX;
		if ($signedIn)
			$export['FULLSIZE_MATCH_REGEX'] = $FULLSIZE_MATCH_REGEX;
		CoreUtils::ExportVars($export);
	} else { ?>
	<h1>There's nothing here yet&hellip;</h1>
	<p>&hellip;but there will be!</p>

<?php   if (Permission::Sufficient('staff'))
			echo CoreUtils::Notice('info','No episodes found',"To make the site functional, you must add an episode to the database first. Head on over to the <a href='/episodes'>Episodes</a> page and add one now!");
	} ?>
</div>
