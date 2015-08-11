<?php
	$Upcoming = $Database->where('airs > NOW()')->get('episodes');
	if (!empty($Upcoming)){ ?>
	<section id=upcoming>
		<h2>Upcoming episodes</h2>
		<ul><?=get_upcoming_eps($Upcoming)?></ul>
	</section>
<?  } ?>
	<section class=<?=$signedIn?'welcome':'login'?>>
		<h2>Welcome!</h2>
<?php
	usercard_render();
	if ($signedIn){
		sidebar_links_render(); ?>
		<div class=buttons>
			<button id="signout" class="typcn typcn-arrow-back">Sign out</button>
		</div>
<?  } else { ?>
		<div class="notice info">
			<p>Please sign in with your DeviantArt account to gain access to the site's features.</p>
			<button class="typcn typcn-link" id="signin">Sign In with DeviantArt</button>
		</div>
		<script>var OAUTH_URL = "https://www.deviantart.com/oauth2/authorize?response_type=code&scope=user+browse&client_id=<?=DA_CLIENT.oauth_redirect_uri()?>";</script>
<?php } ?>
	</section>
	<section class=quote>
		<blockquote id=quote></blockquote>
	</section>
