<div id="content">
<?php
use App\Auth;
use App\CoreUtils;
use App\Models\Logs\Banish;
use App\Models\User;
use App\Permission;
use App\Time;
use App\UserPrefs;
use App\Users;

/** @var $User User */
/** @var $sameUser bool */
/** @var $canEdit bool */
/** @var $Sessions \App\Models\Session[] */
/** @var $CurrentSession \App\Models\Session|null */

if (isset($MSG)){
	echo "<h1>$MSG</h1>";
	if (isset($SubMSG))
		echo "<p>$SubMSG</p>";
}
else {
	$vectorapp = UserPrefs::get('p_vectorapp', $User);
	$discordmember = $User->discord_member;
	$colorscheme = UserPrefs::get('p_theme', $User);
?>
	<div class="briefing">
		<?=$User->getAvatarWrap()?>
		<div class="title">
			<h1><span class="username"><?=$User->name?></span><a class="da" title="Visit DeviantArt profile" href="<?=$User->toDALink()?>"><?=str_replace(' fill="#FFF"','',\App\File::get(APPATH.'img/da-logo.svg'))?></a><?=$User->getVectorAppIcon()?><?=!empty($discordmember)?"<img class='discord-logo' src='/img/discord-logo.svg' alt='Discord logo' title='This user is a member of our Discord server as @".CoreUtils::escapeHTML($discordmember->name)."'>":''?></h1>
			<p><?php
echo "<span class='rolelabel'>{$User->rolelabel}</span>";
if ($canEdit){
	echo ' <button id="change-role" class="blue typcn typcn-spanner'.($User->role==='ban'?' hidden':'').'" title="Change '.CoreUtils::posess($User->name).' group"></button>';
	$BanLabel = ($User->role==='ban'?'Un-ban':'Ban').'ish';
	$Icon = $User->role==='ban'?'world':'weather-night';
	if (Permission::sufficient('staff', $User->role))
		$Icon .= ' hidden';
	echo ' <button id="ban-toggle" class="darkblue typcn typcn-'.$Icon.' '.strtolower($BanLabel).'" title="'."$BanLabel user".'"></button>';
}
if (Permission::sufficient('developer'))
	echo " &bullet; <span class='userid'>{$User->id}</span>", !empty($discordmember->id) ? " &bullet; <span class='discid'>{$discordmember->id}</span>" : '';
			?></p>
		</div>
	</div>
	<div class="details section-container">
<?php
$isStaff = Permission::sufficient('staff');
if ($sameUser || $isStaff){
	$OldNames = $User->name_changes;
	if (!empty($OldNames)){
		$PrevNames = [];
		foreach ($OldNames as $entry)
			$PrevNames[] = $entry->old; ?>
		<section class="old-names">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Previous names <span class="typcn typcn-info color-blue cursor-help" title="Upper/lower-case letters may not match"></span></h2>
			<div><?=implode(', ',$PrevNames)?></div>
		</section>
<?php
	}
}
echo $User->getKnownIPsSection();
echo Users::getContributionsHTML($User, $sameUser);
$isUserMember = Permission::sufficient('member', $User->role);
if ($isUserMember)
	echo Users::getPersonalColorGuideHTML($User, $sameUser);

if (Auth::$signed_in)
	echo $User->getPendingReservationsHTML($sameUser, $isUserMember);
if ($isUserMember)
	echo $User->getAwaitingApprovalHTML($sameUser); ?>
		<section class="bans">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['public']:''?>Banishment history</h2>
			<ul><?php
$Actions = ['Banish', 'Un-banish'];
$Banishes = $User->banishments;
if (!empty($Banishes)){
	$Unbanishes = $User->unbanishments;
	if (!empty($Unbanishes)){
		$Banishes = array_merge($Banishes,$Unbanishes);
		usort($Banishes, function(Banish $a, Banish $b){
			$a = strtotime($a->log['timestamp']);
			$b = strtotime($b->log['timestamp']);
			return $a > $b ? -1 : ($a < $b ? 1 : 0);
		});
		unset($Unbanishes);
	}

	$displayInitiator = $isStaff;

	foreach ($Banishes as $b){
		$initiator = $displayInitiator ? $b->log->actor : null;
		$reason = htmlspecialchars($b->reason);
		$action = strtolower($Actions[$b instanceof Banish ? 0 : 1]);
		echo "<li class='$action'><blockquote>{$reason}</blockquote> - ".(isset($initiator)?$initiator->toAnchor().' ':'').Time::tag($b->log->timestamp).'</li>';
	}
}
			?></ul>
		</section>
	</div>
	<div id="settings" class="section-container"><?php
	if ($sameUser || $isStaff){ ?>
		<section class="guide-settings">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Color Guide</h2>
<?php	(new \App\UserSettingForm('cg_itemsperpage', $User))->render();
		(new \App\UserSettingForm('cg_hidesynon', $User))->render();
		(new \App\UserSettingForm('cg_hideclrinfo', $User))->render();
		(new \App\UserSettingForm('cg_fulllstprev', $User))->render(); ?>
		</section>
		<section class="eppage-settings">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Episode pages</h2>
<?php	(new \App\UserSettingForm('ep_noappprev', $User))->render();
		(new \App\UserSettingForm('ep_revstepbtn', $User))->render(); ?>
		</section>
		<section class="personal-settings">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Personal</h2>
<?php	(new \App\UserSettingForm('p_avatarprov', $User))->render();
		(new \App\UserSettingForm('p_vectorapp', $User))->render();
		(new \App\UserSettingForm('p_hidediscord', $User))->render();
		(new \App\UserSettingForm('p_hidepcg', $User))->render(); ?>
		</section>
		<section class="staff-limits">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Account limitations</h2>
<?php	(new \App\UserSettingForm('a_pcgearn', $User, 'staff'))->render();
		(new \App\UserSettingForm('a_postreq', $User, 'staff'))->render();
		(new \App\UserSettingForm('a_postres', $User, 'staff'))->render();
		(new \App\UserSettingForm('a_reserve', $User, 'staff'))->render(); ?>
		</section>
		<section class="sessions">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Sessions</h2>
<?php   if (!empty($Sessions)){ ?>
			<p>Below is a list of all the browsers <?=$sameUser?"you've":'this user has'?> logged in from.</p>
			<ul class="session-list"><?php
			foreach ($Sessions as $s)
				Users::renderSessionLi($s, $s->id === ($CurrentSessionID ?? null));
			?></ul>
			<p><button class="typcn typcn-arrow-back yellow" id="signout-everywhere">Sign out everywhere</button></p>
<?php   }
		else { ?>
			<p><?=$sameUser?'You are':'This user is'?>n’t logged in anywhere.</p>
<?php   } ?>
		</section>
<?php
	}
	if ($sameUser){ ?>
		<section>
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['private']:''?>Unlink account</h2>
			<p>By unlinking your account you revoke this site’s access to your account information. This will also log you out on every device where you're currently logged in. The next time you want to log in, you'll have to link your account again. This will not remove any of your <strong>public</strong> data from our site, it’s still kept locally.</p>
	        <button id="unlink" class="orange typcn typcn-times">Unlink Account</button>
	    </section>
<?  } ?></div>
<?php
} ?>
</div>

<?php
if ($canEdit){
	$ROLES = [];
	if ($canEdit){
		$_Roles = Permission::ROLES_ASSOC;
		unset($_Roles['guest'], $_Roles['ban']);
		foreach ($_Roles as $name => $label){
			if (Permission::insufficient($name, Auth::$user->role))
				continue;
			$ROLES[$name] = $label;
		}
	}
	echo CoreUtils::exportVars([
		'ROLES' => $ROLES,
	]);
} ?>
