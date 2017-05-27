<div id="content">
<?php
use App\Auth;
use App\CoreUtils;
use App\Models\User;
use App\DeviantArt;
use App\JSON;
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
	$vectorapp = UserPrefs::get('p_vectorapp', $User->id);
	$discordmember = $Database->disableAutoClass()->where('userid', $User->id)->getOne('discord-members',"id, coalesce(nick,username) as displayname");
?>
	<div class="briefing">
		<?=$User->getAvatarWrap()?>
		<div class="title">
			<h1><span class="username"><?=$User->name?></span><a class="da" title="Visit DeviantArt profile" href="<?=$User->getDALink(User::LINKFORMAT_URL)?>"><?=str_replace(' fill="#FFF"','',file_get_contents(APPATH.'img/da-logo.svg'))?></a><?=!empty($vectorapp)?"<img class='vectorapp-logo' src='/img/vapps/$vectorapp.svg' alt='$vectorapp logo' title='".CoreUtils::$VECTOR_APPS[$vectorapp]." user'>":''?><?=!empty($discordmember)?"<img class='discord-logo' src='/img/discord-logo.svg' alt='Discord logo' title='This user is a member of our Discord server as @".CoreUtils::escapeHTML($discordmember['displayname'])."'>":''?></h1>
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
	echo " &bullet; <span class='userid'>{$User->id}</span>", !empty($discordmember['id']) ? " &bullet; <span class='discid'>{$discordmember['id']}</span>" : '';
			?></p>
		</div>
	</div>
	<div class="details">
<?php
if ($sameUser || Permission::sufficient('staff')){
	$OldNames = $Database->where('id', $User->id)->orderBy('entryid',OLDEST_FIRST)->get('log__da_namechange',null,'old');
	if (!empty($OldNames)){
		$PrevNames = array();
		foreach ($OldNames as $Post)
			$PrevNames[] = $Post['old']; ?>
		<section class="old-names">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Previous names <span class="typcn typcn-info color-blue cursor-help" title="Upper/lower-case letters may not match"></span></h2>
			<div><?=implode(', ',$PrevNames)?></div>
		</section>
<?php
	}
}
echo Users::getContributionsHTML($User, $sameUser);
$isUserMember = Permission::sufficient('member', $User->role);
if ($isUserMember)
	echo Users::getPersonalColorGuideHTML($User, $sameUser);

if (Auth::$signed_in)
	echo Users::getPendingReservationsHTML($User->id, $sameUser, $isUserMember); ?>
<? if ($isUserMember){ ?>
<section class="awaiting-approval"></section>
<? } ?>
		<section class="bans">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['public']:''?>Banishment history</h2>
			<ul><?php
$Actions = array('Banish','Un-banish');
$Banishes = $Database
	->where('target', $User->id)
	->join('log l',"l.reftype = 'banish' AND l.refid = b.entryid")
	->orderBy('l.timestamp')
	->get('log__banish b',null,"b.reason, l.initiator, l.timestamp, 0 as action");
if (!empty($Banishes)){
	$Unbanishes = $Database
		->where('target', $User->id)
		->join('log l',"l.reftype = 'un-banish' AND l.refid = b.entryid")
		->get('log__un-banish b',null,"b.reason, l.initiator, l.timestamp, 1 as action");
	if (!empty($Unbanishes)){
		$Banishes = array_merge($Banishes,$Unbanishes);
		usort($Banishes, function($a, $b){
			$a = strtotime($a['timestamp']);
			$b = strtotime($b['timestamp']);
			return $a > $b ? -1 : ($a < $b ? 1 : 0);
		});
		unset($Unbanishes);
	}

	$displayInitiator = Permission::sufficient('staff');

	foreach ($Banishes as $b){
		$initiator = $displayInitiator ? Users::get($b['initiator']) : null;
		$b['reason'] = htmlspecialchars($b['reason']);
		echo "<li class=".strtolower($Actions[$b['action']])."><blockquote>{$b['reason']}</blockquote> - ".(isset($initiator)?$initiator->getProfileLink().' ':'').Time::tag($b['timestamp'])."</li>";
	}
}
			?></ul>
		</section>
	</div>
	<div id="settings"><?php
	if ($sameUser || Permission::sufficient('staff')){ ?>
		<section class="guide-settings">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Color Guide</h2>
			<form action="/preference/set/cg_itemsperpage">
				<label>
					<span>Appearances per page</span>
					<input type="number" min="7" max="20" name="value" value="<?=UserPrefs::get('cg_itemsperpage', $User->id)?>" step="1"<?=!$sameUser?' disabled':''?>>
<?php   if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php   } ?>
				</label>
			</form>
<?php   if (Permission::sufficient('staff', $User->role)){ ?>
			<form action="/preference/set/cg_hidesynon">
				<label>
					<input type="checkbox" name="value" value="1"<?=UserPrefs::get('cg_hidesynon', $User->id)?' checked':''?> <?=!$sameUser?' disabled':''?>>
					<span>Hide synonym relations</span>
<?php       if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php       } ?>
				</label>
			</form>
<?php   } ?>
			<form action="/preference/set/cg_hideclrinfo">
				<label>
					<input type="checkbox" name="value" value="1"<?=UserPrefs::get('cg_hideclrinfo', $User->id)?' checked':''?> <?=!$sameUser?' disabled':''?>>
					<span>Hide color details on appearance pages</span>
<?php   if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php   } ?>
				</label>
			</form>
			<form action="/preference/set/cg_fulllstprev">
				<label>
					<input type="checkbox" name="value" value="1"<?=UserPrefs::get('cg_fulllstprev', $User->id)?' checked':''?> <?=!$sameUser?' disabled':''?>>
					<span>Display previews and alternate names on the full list</span>
<?php   if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php   } ?>
				</label>
			</form>
		</section>
		<section class="eppage-settings">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Episode pages</h2>
			<form action="/preference/set/ep_noappprev">
				<label>
					<input type="checkbox" name="value" value="1"<?=UserPrefs::get('ep_noappprev', $User->id)?' checked':''?> <?=!$sameUser?' disabled':''?>>
					<span>Hide preview squares in front of related appearance names</span>
<?php   if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php   } ?>
				</label>
			</form>
		</section>
		<section class="personal-settings">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Personal</h2>
			<form action="/preference/set/p_vectorapp">
				<label>
					<span>Publicly show my vector progam of choice: </span>
					<select name="value"<?=!$sameUser?' disabled':''?>><?php
				$apps = CoreUtils::$VECTOR_APPS;
				echo "<option value=''".($vectorapp===''?' selected':'').">{$apps['']}</option>";
				unset($apps['']);
				echo "<optgroup label='Vectoring applications'>";
				foreach ($apps as $id => $label)
					echo "<option value='$id'".($vectorapp===$id?' selected':'').">$label</option>";
				echo "</optgroup>";
					?></select>
<?php   if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php   } ?>
				</label>
			</form>
<?php   if (!$User->isDiscordMember()){ ?>
			<form action="/preference/set/p_hidediscord">
				<label>
					<input type="checkbox" name="value" value="1"<?=UserPrefs::get('p_hidediscord', $User->id)?' checked':''?> <?=!$sameUser?' disabled':''?>>
					<span>Hide Discord server link from the sidebar</span>
<?php       if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php       } ?>
				</label>
			</form>
<?php   } ?>
			<form action="/preference/set/p_hidepcg">
				<label>
					<input type="checkbox" name="value" value="1"<?=UserPrefs::get('p_hidepcg', $User->id)?' checked':''?> <?=!$sameUser?' disabled':''?>>
					<span>Hide my Personal Color Guide from the public</span>
<?php   if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php   } ?>
				</label>
			</form>
		</section>
		<section class="sessions">
			<h2><?=$sameUser? Users::PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Sessions</h2>
<?php   if (isset($CurrentSession) || !empty($Sessions)){ ?>
			<p>Below is a list of all the browsers <?=$sameUser?"you've":'this user has'?> logged in from.</p>
			<ul class="session-list"><?php
				if (isset($CurrentSession)) Users::renderSessionLi($CurrentSession,CURRENT);
				if (!empty($Sessions)){
					foreach ($Sessions as $s) Users::renderSessionLi($s);
				}
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
	$ROLES = array();
	if ($canEdit){
		$_Roles = Permission::ROLES_ASSOC;
		unset($_Roles['guest']);
		unset($_Roles['ban']);
		foreach ($_Roles as $name => $label){
			if (Permission::insufficient($name, Auth::$user->role))
				continue;
			$ROLES[$name] = $label;
		}
	}
	echo CoreUtils::exportVars(array(
		'ROLES' => $ROLES,
	));
} ?>
