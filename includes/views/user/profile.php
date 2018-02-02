<div id="content">
<?php
use App\Auth;
use App\CoreUtils;
use App\Models\DiscordMember;
use App\Models\User;
use App\Permission;
use App\Time;
use App\UserPrefs;
use App\Users;

/** @var $User User */
/** @var $sameUser bool */
/** @var $canEdit bool */
/** @var $devOnDev bool */
/** @var $Sessions \App\Models\Session[] */
/** @var $CurrentSession \App\Models\Session|null */

if (isset($MSG)){
	echo "<h1>$MSG</h1>";
	if (isset($SubMSG))
		echo "<p>$SubMSG</p>";
}
else { ?>
	<div class="briefing">
		<?=$User->getAvatarWrap()?>
		<div class="title">
			<h1><span class="username"><?=$User->name?></span><a class="da" title="Visit DeviantArt profile" href="<?=$User->toDALink()?>"><?=str_replace(' fill="#FFF"','',\App\File::get(APPATH.'img/da-logo.svg'))?></a><?=$User->getVectorAppIcon()?><?=$User->isDiscordServerMember()?"<img class='discord-logo' src='/img/discord-logo.svg' alt='Discord logo' title='This user is a member of our Discord server".($User->discord_member->name !== $User->name ? ' as '.CoreUtils::escapeHTML($User->discord_member->name):'')."'>":''?></h1>
			<p><?php
echo "<span class='role-label'>{$User->maskedRoleLabel()}</span>";
if ($devOnDev)
	echo ' <span id="change-dev-role-mask" class="inline-btn typcn typcn-edit" title="Change developer\'s displayed role"></span>';
if ($canEdit)
	echo ' <button id="change-role" class="blue typcn typcn-spanner" title="Change '.CoreUtils::posess($User->name).' role"></button>';
if (Permission::sufficient('developer')){
	echo " &bullet; <span class='user-id'>{$User->id}</span>";
	if ($User->isDiscordLinked())
		echo " &bullet; <span class='discord-id'>{$User->discord_member->id}</span>";
} ?></p>
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
echo Users::getPersonalColorGuideHTML($User, $sameUser);

$isUserMember = Permission::sufficient('member', $User->role);
if (Auth::$signed_in)
	echo $User->getPendingReservationsHTML($sameUser, $isUserMember);
if ($isUserMember)
	echo $User->getAwaitingApprovalHTML($sameUser); ?>
	</div>
	<div id="settings" class="section-container"><?php
	if ($sameUser || $isStaff){ ?>
		<section class="guide-settings">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Color Guide</h2>
<?php	(new \App\UserSettingForm('cg_itemsperpage', $User))->render();
		//(new \App\UserSettingForm('cg_hidesynon', $User))->render();
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
		(new \App\UserSettingForm('p_hidepcg', $User))->render();
		(new \App\UserSettingForm('p_homelastep', $User))->render(); ?>
		</section>
		<section class="staff-limits">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Account limitations</h2>
<?php	(new \App\UserSettingForm('a_pcgearn', $User, 'staff'))->render();
		(new \App\UserSettingForm('a_pcgmake', $User, 'staff'))->render();
		(new \App\UserSettingForm('a_pcgsprite', $User, 'staff'))->render();
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
			<p><button class="typcn typcn-arrow-back yellow" id="sign-out-everywhere">Sign out everywhere</button></p>
<?php   }
		else { ?>
			<p><?=$sameUser?'You are':'This user is'?>n't logged in anywhere.</p>
<?php   } ?>
		</section>
		<section id="discord-connect">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Discord account</h2>
<?php   if ($User->isDiscordLinked()){
			$member = $User->discord_member;
			$unlinkBtn = '<button class="orange typcn typcn-user-delete unlink">Unlink</button>'; ?>
			<p><?=$sameUser?'Your':'This'?> account <?=$member->isLinked()?'is linked':'was manually bound'?> to <strong><?=CoreUtils::escapeHTML($member->discord_tag)?></strong><?php
			if ($member->isLinked()){
				$you = $sameUser?'you':'they';
				if ($member->isServerMember())
					echo " and $you've joined our <a href='https://discordapp.com/channels/".DISCORD_SERVER_ID."'>Discord server</a> ".Time::tag($member->joined_at);
				else echo " but $you haven't joined our <a href='".DISCORD_INVITE_LINK."'>Discord server</a> yet";
			} ?>.</p>
<?php       if ($member->isLinked()){ ?>
			<p id="discord-sync-info" data-cooldown="<?=DiscordMember::SYNC_COOLDOWN?>"><?php
				if ($member->isServerMember())
					echo "Server members' DiscordTag and avatar is updated automatically, but you can always use the button below to force an update.";
				else echo "You can use the button below to force an update to your account information at any time. Server members' data is updated automatically."; ?><br>
				Your account information was last updated <?=Time::tag($member->last_synced)?>.<?=!$member->canBeSynced()?'<span class="wait-message"> At least '.CoreUtils::makePlural('minute', DiscordMember::SYNC_COOLDOWN/60, PREPEND_NUMBER).' must pass before syncing again.</span>':''?>
			</p>
			<div class="button-block align-center">
				<button class="green typcn typcn-arrow-sync sync" <?=!$member->canBeSynced()?'disabled':''?>>Sync</button>
				<?=$unlinkBtn?>
			</div>
<?php       }
			else $member = false;
		}
		if (empty($member)){ ?>
			<p>Link your account to be able to choose between your Discord and DeviantArt avatars as well as to participate in events for Discord server members.</p>
			<div class="button-block align-center">
				<a href="/discord-connect/begin" class="btn link typcn typcn-link">Link account</a>
				<?=isset($member)&&$member===false?$unlinkBtn:''?>
			</div>
<?php   } ?>
		</section>
<?php
	}
	if ($sameUser){ ?>
		<section>
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['private']:''?>Unlink DeviantArt account</h2>
			<p>All this really does is that the next time you want to log in, you'll be asked to link your account again. Normally this would prevent the site from reading any resources you've given access to when you linked your account, but since we only ask for the bare minimum permissions required to verify your identity there's not much for us to access. This also won't remove any of your data from our site, that's still kept locally.</p>
	        <button id="unlink" class="orange typcn typcn-user-delete">Unlink Account</button>
	    </section>
<?  } ?></div>
<?php
} ?>
</div>

<?php
if ($canEdit || $devOnDev){
	$ROLES = [];
	if ($canEdit){
		$_Roles = Permission::ROLES_ASSOC;
		unset($_Roles['guest']);
		foreach ($_Roles as $name => $label){
			if (Permission::insufficient($name, Auth::$user->role))
				continue;
			$ROLES[$name] = $label;
		}
	}
	else if ($devOnDev)
		$ROLES = Permission::ROLES_ASSOC;
	echo CoreUtils::exportVars([
		'ROLES' => $ROLES,
	]);
} ?>
