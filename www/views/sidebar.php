<section class="<?=$signedIn?'welcome':'login'?>">
	<h2>Welcome!</h2>
<?php if ($signedIn){ ?>
	<p>Signed in as</p>
	<div class="usercard">
		<img src="<?=$currentUser['avatar_url']?>" class=avatar>
		<span class="un"><?=$currentUser['username']?></span>
		<span class="role"><?=$currentUser['rolelabel']?></span>
	</div>
	<blockquote></blockquote>

<?=sidebar_links_render()?>

	<button id="signout">Sign out</button>
	<button id="unlink" class="red">Unlink Account</button>
<?php } else { ?>
	<p>You're browsing the site as</p>
	<div class="usercard">
		<img src="<?=djpth('img>favicon.png')?>">
		<span class="un">Curious Pony</span>
		<span class="role">Guest</span>
	</div>
	<div class="notice info">
		<p>Please sign in with your deviantArt account to gain access to the site's features.</p>
		<a class="btn" href="https://www.deviantart.com/oauth2/authorize?response_type=code&scope=user&client_id=<?=DA_CLIENT.oauth_redirect_uri()?>">Sign In with deviantArt</a>
	</div>
<?php } ?>
</section>