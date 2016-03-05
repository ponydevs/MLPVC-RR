	<section class='mobile-nav'>
		<h2>Pages</h2>
		<nav><ul><?=get_nav_html()?></ul></nav>
	</section>
<?php
if (!empty($Database)){ ?>
	<section class="<?=$signedIn?'welcome':'login'?>">
		<h2><?=$signedIn?'Signed in as':'Welcome!'?></h2>
<?php
	usercard_render(true);
	if ($signedIn){
		sidebar_links_render(); ?>
		<div class="buttons">
			<button id="signout" class="typcn typcn-arrow-back">Sign out</button>
		</div>
<?  } else { ?>
		<div class="buttons">
			<button class="typcn typcn-link green" id="signin">Sign In with DeviantArt</button>
		</div>
		<script>var OAUTH_URL = "<?=get_oauth_authorization_url()?>";</script>
<?php } ?>
	</section>
<?php
	if ($view === 'episode' && !empty($CurrentEpisode)){
		$CurrentEpisode['willair'] = gmdate('c', strtotime('+'.(!$CurrentEpisode['twoparter'] ? '30' : '60').' minutes',strtotime($CurrentEpisode['airs']))); ?>
	<section id="voting">
		<h2><?=$CurrentEpisode['season']==0?'Movie':'Episode'?> rating</h2>
		<?=get_episode_voting($CurrentEpisode)?>
	</section>
<?php
	}
	if ($do === 'colorguide' && (!empty($Appearance) || !empty($Ponies))){ ?>
	<section id="hash-copy">
		<h2>Color Guide</h2>
		<p>You can click any <?=$color?>ed square on this page to copy its HEX <?=$color?> code to your clipboard. Holding Shift while clicking will display a dialog with the RGB <?=$color?> values instead.</p>
		<button class='blue typcn typcn-refresh' id='toggle-copy-hash'>Checking&hellip;</button>
	</section>
<?php
	}
	echo get_upcoming_eps();
} else { ?>

	<section class="login">
		<h2>Welcome!</h2>
		<p>We're having some technical difficulties and signing in is not possible at the moment. Please check back later.</p>
	</section>
<? } ?>
