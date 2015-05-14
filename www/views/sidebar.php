	<section class=<?=$signedIn?'welcome':'login'?>>
		<h2>Welcome!</h2>
<?php if ($signedIn){
	usercard_render();
	sidebar_links_render(); ?>
		<div class=buttons>
			<button id="signout" class="typcn typcn-arrow-back">Sign out</button>
		</div>
<?php } else { ?>
<?=usercard_render()?>
		<div class="notice info">
			<p>Please sign in with your deviantArt account to gain access to the site's features.</p>
			<a class="btn typcn typcn-link" href="https://www.deviantart.com/oauth2/authorize?response_type=code&scope=user+browse&client_id=<?=DA_CLIENT.oauth_redirect_uri()?>">Sign In with deviantArt</a>
		</div>
<?php } ?>
	</section>
	<section class=quote>
		<blockquote id=quote></blockquote>
	</section>
