<?php // DO NOT USE SHORT OPEN TAGS IN THIS FILE - THIS IS PART OF THE PAGE THAT DISPLAYS THE WARNING ABOUT THE OPTION BEING DISABLED
use App\Auth;
use App\CoreUtils;
use App\Episodes;
use App\Notifications;
use App\UserPrefs;
/** @var $do string */
/** @var $scope array */
/** @var $view \App\View */
/** @var $CurrentEpisode \App\Models\Episode */ ?>
	<div class='mobile-nav'>
		<nav><ul><?=CoreUtils::getNavigationHTML(isset($view) && $view->name === 'fatalerr', $scope)?></ul></nav>
	</div>
	<div class='logged-in'>
		<?php
	if (Auth::$signed_in)
		echo Auth::$user->getAvatarWrap();
	else echo (new \App\Models\FailsafeUser([
		'name' => 'Guest',
		'role' => 'guest',
		'avatar_url' => GUEST_AVATAR
	]))->getAvatarWrap(); ?>
		<div class="user-data">
			<span class="user-name"><?=Auth::$signed_in?Auth::$user->getProfileLink(App\Models\User::LINKFORMAT_TEXT):'Curious Pony'?></span>
			<span class="user-role"><?=Auth::$signed_in?Auth::$user->rolelabel:'Guest'?></span>
		</div>
	</div>
<?php
	if (!defined('FATAL_ERROR')){
		if (Auth::$signed_in){
			$Notifications = Notifications::get(Notifications::UNREAD_ONLY); ?>
	<section class="notifications"<?=empty($Notifications)?' style="display:none"':''?>>
		<h2>Unread notifications</h2>
		<?= Notifications::getHTML($Notifications) ?>
	</section>
<?php   } ?>
	<section class="<?=Auth::$signed_in?'welcome':'login'?>">
		<h2 class="hidden">Welcome!</h2>
<?php	CoreUtils::renderSidebarUsefulLinks(); ?>
		<div class="buttons">
<?php
		if (Auth::$signed_in){ ?>
			<button id="signout" class="typcn typcn-arrow-back">Sign out</button>
<?php   }
		else { ?>
			<button class="typcn btn-da da-login" id="signin">Sign in</button>
			<!--suppress ES6ConvertVarToLetConst, JSUnusedLocalSymbols -->
			<script>var OAUTH_URL = "<?=\App\DeviantArt::OAuthProviderInstance()->getAuthorizationUrl()?>";</script>
<?php   }
		if (!UserPrefs::get('p_hidediscord') && (Auth::$signed_in ? !Auth::$user->isDiscordMember() : true)){ ?>
			<a class="btn typcn btn-discord discord-join" href="http://fav.me/d9zt1wv">Join Discord</a>
<?php   } ?>
		</div>
	</section>
	<section id="episode-live-update" class="hidden">
		<h2><span class="live-circle"></span> Live updates enabled</h2>
		<p>Changes to the posts on this page are visible in real time to everyone who sees this message.<br>
			<small>If you'd like to have the option to disable this feature please <a class="send-feedback">let us know</a>.</small>
		</p>
	</section>
	<section id="entry-live-update" class="hidden">
		<h2><span class="live-circle"></span> Live updates enabled</h2>
		<p>Changes to post scores are visible in real time to everyone who sees this message.</p>
	</section>
<?php   if ($view->name === 'episode' && !empty($CurrentEpisode)){ ?>
	<section id="voting">
		<h2><?=$CurrentEpisode->is_movie?'Movie':'Episode'?> rating</h2>
		<?=Episodes::getSidebarVoting($CurrentEpisode)?>
	</section>
<?php	}
		if ($do === 'colorguide' && (!empty($Appearance) || !empty($Ponies)) && empty($Map)){ ?>
	<section id="hash-copy">
		<h2>Color Guide</h2>
		<p>You can click any colored square on this page to copy its HEX color code to your clipboard. Holding Shift while clicking will display a dialog with the RGB color values instead.</p>
		<button class='blue typcn typcn-refresh' id='toggle-copy-hash'>Checking&hellip;</button>
	</section>
<?php
		}
		echo CoreUtils::getSidebarUpcoming();
	}
	else { ?>

	<section class="login">
		<h2>Welcome!</h2>
		<p>Signing in is not possible at the moment. Please check back later.</p>
	</section>
<?php
	}
