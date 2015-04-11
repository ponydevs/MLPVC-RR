<section class="<?=$signedIn?'welcome':'login'?>">
	<h2>Welcome!</h2>
<?php if ($signedIn){ ?>
	<p>Signed in as</p>
	<div class="usercard">
		<img src="<?=$currentUser['avatar_url']?>" class=avatar>
		<span class="un"><?=$currentUser['username']?></span>
		<span class="role"><?=$currentUser['rolelabel']?></span>
	</div>
	<em></em>
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
		<p>In order to use this site's core functions you'll need to link your deviantArt account. This is a quick process which lets us check your identity, and gives you access to features only available to deviantArt users.</p>
		<a class="btn" href="https://www.deviantart.com/oauth2/authorize?response_type=code&scope=user&client_id=<?=DA_CLIENT.oauth_redirect_uri()?>">Sign In with deviantArt</a>
	</div>
<?php } ?>
</section>