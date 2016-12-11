<?php
use App\CoreUtils;
use App\Episodes;
use App\Notifications;
use App\Time;
use App\UserPrefs;
use App\Users;
/** @var $signedIn bool */
/** @var $do string */ ?>
	<div class='mobile-nav'>
		<nav><ul><?=CoreUtils::getNavigationHTML()?></ul></nav>
	</div>
<?php
	if (!empty($Database)){
		if ($signedIn){
			$Notifications = Notifications::get(null, Notifications::UNREAD_ONLY); ?>
	<section class="notifications"<?=empty($Notifications)?' style="display:none"':''?>>
		<h2>Unread notifications</h2>
<?php
		try {
			echo Notifications::getHTML($Notifications);
		}
		catch(Throwable $e){
			error_log('Exception caught when rendering notifications: '.$e->getMessage()."\n".$e->getTraceAsString());
			echo '<ul class="notif-list"><li>An error prevented the notifications from appearing. If this persists, <a class="send-feedback">let us know</a>.</li></ul>';
		} ?>
	</section>
<?php   } ?>
	<section class="<?=$signedIn?'welcome':'login'?>">
		<h2><?=$signedIn?'Signed in as':'Welcome!'?></h2>
<?php
		Users::renderCard();
		CoreUtils::renderSidebarUsefulLinks(); ?>
		<div class="buttons">
<?php
		if ($signedIn){ ?>
			<button id="signout" class="typcn typcn-arrow-back">Sign out</button>
<?php   }
		else { ?>
			<button class="typcn green da-login" id="signin">Sign in</button>
			<!--suppress ES6ConvertVarToLetConst, JSUnusedLocalSymbols -->
			<script>var OAUTH_URL = "<?=OAUTH_AUTHORIZATION_URL?>";</script>
<?php   }
		if (!UserPrefs::get('p_hidediscord')){ ?>
			<a class="btn typcn discord-join" href="http://fav.me/d9zt1wv" target="_blank">Join Discord</a>
<?php   } ?>
		</div>
	</section>
<?php   if ($view === 'episode' && !empty($CurrentEpisode)){ ?>
	<section id="voting">
		<h2><?=$CurrentEpisode->isMovie?'Movie':'Episode'?> rating</h2>
		<?=Episodes::getSidebarVoting($CurrentEpisode)?>
	</section>
<?php       if ($CurrentEpisode->isLatest() && time() > $CurrentEpisode->willairts && $CurrentEpisode->willairts + (Time::$IN_SECONDS['hour']*4) > time()){ ?>
	<section id="live-update">
		<h2>Live reload</h2>
		<p>The episode has just aired, and posts are likely changing faster than usual.</p>
		<div>
			<p>The posts will reload in <strong class="timer">&hellip;</strong> to reflect the changes.</p>
			<p class="hidden">Live reloading is disabled.</p>
			<button class="blue reload typcn typcn-refresh">Reload now</button> <button class="red disable typcn typcn-times">Disable</button>
		</div>
	</section>
<?php       }
		}
		if ($do === 'colorguide' && (!empty($Appearance) || !empty($Ponies)) && empty($Map)){ ?>
	<section id="hash-copy">
		<h2>Color Guide</h2>
		<p>You can click any <?=$color?>ed square on this page to copy its HEX <?=$color?> code to your clipboard. Holding Shift while clicking will display a dialog with the RGB <?=$color?> values instead.</p>
		<button class='blue typcn typcn-refresh' id='toggle-copy-hash'>Checking&hellip;</button>
	</section>
<?php
		}
		echo Episodes::getSidebarUpcoming();
	}
	else { ?>

	<section class="login">
		<h2>Welcome!</h2>
		<p>We're having some technical difficulties and signing in is not possible at the moment. Please check back later.</p>
	</section>
<?php
	}
