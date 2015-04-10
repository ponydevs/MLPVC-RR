<?php if ($signedIn){ ?>
<section class="welcome">
	<h2>Welcome!</h2>
	<p>Signed in as</p>
	<div class="usercard card-<?=$currentUser['role']?>">
		<img src="<?=$currentUser['avatar_url']?>">
		<span class="un"><?=$currentUser['username']?></span>
		<span class="role"><?=$currentUser['rolelabel']?></span>
	</div>
	<em></em>
	<button id="signout">Sign out</button>
	<button id="unlink" class="red">Unlink Account</button>
<?php } else { ?>
<section class="login">
	<div class="notice info">
		<p>In order to use this site you'll need to verify your deviantArt account. This is a quick process and allows us to check your identity.</p>
		<p>If you do not wish to sign in, you can still browse the site, but with limited functionality.</p>
		<a class="btn" href="https://www.deviantart.com/oauth2/authorize?response_type=code&client_id=<?=DA_CLIENT.oauth_redirect_uri()?>">Sign In with deviantArt</a>
	</div>
<?php } ?>
</section>