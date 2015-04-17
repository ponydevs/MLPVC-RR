<section class=<?=$signedIn?'welcome':'login'?>>
	<h2>Welcome!</h2>
<?php if ($signedIn){ ?>
	<p>Signed in as</p>
<?=usercard_render()?>

<?=sidebar_links_render()?>

	<div class=buttons>
		<button id="signout">Sign out</button>
		<button id="unlink" class="red">Unlink Account</button>
	</div>
<?php } else { ?>
	<p>You're browsing the site as</p>
<?=usercard_render()?>
	<div class="notice info">
		<p>Please sign in with your deviantArt account to gain access to the site's features.</p>
		<a class="btn" href="https://www.deviantart.com/oauth2/authorize?response_type=code&scope=user&client_id=<?=DA_CLIENT.oauth_redirect_uri()?>">Sign In with deviantArt</a>
	</div>
<?php } ?>
</section>
<section class=quote>
	<blockquote id=quote></blockquote>
</section>