<div id="content">
<?php
	if (isset($MSG)){
		echo "<h1>$MSG</h1>";
		if (isset($SubMSG)) echo "<p>$SubMSG</p>";
	}
	else {
		$vectorapp = UserPrefs::Get('p_vectorapp', $User['id']); ?>
	<div class="briefing">
		<?=User::GetAvatarWrap($User)?>
		<div class="title">
			<h1><span class="role-badge"><?=Permission::LabelInitials($User['role'])?></span><span><?=$User['name']?></span><a class="da" title="Visit DeviantArt profile" href="<?=User::GetDALink($User,User::LINKFORMAT_URL)?>"><?=str_replace(' fill="#FFF"','',file_get_contents(APPATH.'img/da-logo.svg'))?></a><?=!empty($vectorapp)?"<img class='vectorapp-logo' src='/img/vapps/$vectorapp.svg' alt='$vectorapp logo' title='".CoreUtils::$VECTOR_APPS[$vectorapp]." user'>":''?></h1>
			<p><?php
	echo "<span>{$User['rolelabel']}</span>";
	if ($canEdit){
		echo ' <button id="change-role" class="blue typcn typcn-spanner'.($User['role']==='ban'?' hidden':'').'" title="Change '.CoreUtils::Posess($User['name']).' group"></button>';
		$BanLabel = ($User['role']==='ban'?'Un-ban':'Ban').'ish';
		$Icon = $User['role']==='ban'?'world':'weather-night';
		if (Permission::Sufficient('staff', $User['role']))
			$Icon .= ' hidden';
		echo ' <button id="ban-toggle" class="darkblue typcn typcn-'.$Icon.' '.strtolower($BanLabel).'" title="'."$BanLabel user".'"></button>';
	}
	if (Permission::Sufficient('developer'))
		echo " &bullet; <span class='userid'>{$User['id']}</span>";
			?></p>
		</div>
	</div>
	<div class="details">
<?php
	if ($sameUser || Permission::Sufficient('staff')){
		$OldNames = $Database->where('id', $User['id'])->orderBy('entryid',OLDEST_FIRST)->get('log__da_namechange',null,'old');
		if (!empty($OldNames)){
			$PrevNames = array();
			foreach ($OldNames as $row)
				$PrevNames[] = $row['old']; ?>
		<section class="old-names">
			<h2><?=$sameUser?User::$PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Previous names <span class="typcn typcn-info color-blue cursor-help" title="Upper/lower-case letters may not match"></span></h2>
			<div><?=implode(', ',$PrevNames)?></div>
		</section>
<?php   }
	}
	if (Permission::Sufficient('member', $User['role'])){
		echo User::GetPendingReservationsHTML($User['id'], $sameUser, $YouHave);

		$cols = "id, season, episode, deviation_id as deviation";
		$AwaitingApproval = array_merge(
			$Database
				->where('reserved_by', $User['id'])
				->where('deviation_id IS NOT NULL')
				->where('"lock" IS NOT TRUE')
				->get('reservations',null,$cols),
			$Database
				->where('reserved_by', $User['id'])
				->where('deviation_id IS NOT NULL')
				->where('"lock" IS NOT TRUE')
				->get('requests',null,"$cols, true as rq")
		);
		$AwaitCount = count($AwaitingApproval);
		$them = $AwaitCount!==1?'them':'it'; ?>
		<section class="awaiting-approval">
			<h2><?=$sameUser?User::$PROFILE_SECTION_PRIVACY_LEVEL['public']:''?>Vectors waiting for approval</h2>
<?php   if ($sameUser){ ?>
			<p>After you finish an image and submit it to the group gallery, an admin will check your vector and may ask you to fix some issues on your image, if any. After an image is accepted to the gallery, it can be marked as "approved", which gives it a green check mark, indicating that it's most likely free of any errors.</p>
<?php   } ?>
			<p><?="$YouHave ".(!$AwaitCount?'no':"<strong>$AwaitCount</strong>")?> image<?=$AwaitCount!==1?'s':''?> waiting to be submited to and/or approved by the group<?=
				!$AwaitCount
					? '.'
					: ", listed below.".(
						$sameUser
						? "Please submit $them to the group gallery as soon as possible to have $them spot-checked for any issues. As stated in the rules, the goal is to add finished images to the group gallery, making $them easier to find for everyone.".(
							$AwaitCount>10
							? " You seem to have a large number of images that have not been approved yet, please submit them to the group soon if you haven't already."
							: ''
						)
						:''
					).'</p><p>You can click the <strong class="color-green"><span class="typcn typcn-tick"></span> Check</strong> button below the '.CoreUtils::MakePlural('image',$AwaitCount).' in case we forgot to click it ourselves after accepting it.'?></p>
<?php   if ($AwaitCount){ ?>
			<ul id="awaiting-deviations"><?
			foreach ($AwaitingApproval as $row){
				$deviation = DeviantArt::GetCachedSubmission($row['deviation']);
				$url = "http://{$deviation['provider']}/{$deviation['id']}";
				list($link,$page) = Posts::GetLink($row);
				$thing = isset($row['rq']) ? 'request' : 'reservation';
				$checkBtn = Permission::Sufficient('member') ? "\n\t\t<button class='green typcn typcn-tick check'>Check</button>" : '';

				echo <<<HTML
<li id="{$thing}-{$row['id']}">
	<div class="image deviation">
		<a href="$url" target="_blank">
			<img src="{$deviation['preview']}" alt="{$deviation['title']}">
		</a>
	</div>
	<span class="label"><a href="$url" target="_blank">{$deviation['title']}</a></span>
	<em>Posted under <a href='$link'>$page</a></em>
	<div>
		<a href='$link' class='btn blue typcn typcn-arrow-forward'>View</a>$checkBtn
	</div>
</li>
HTML;

			} ?></ul>
<?php   } ?>
		</section>
<?  } ?>
		<section class="bans">
			<h2><?=$sameUser?User::$PROFILE_SECTION_PRIVACY_LEVEL['public']:''?>Banishment history</h2>
			<ul><?php
		$Actions = array('Banish','Un-banish');
		$Banishes = $Database
			->where('target', $User['id'])
			->join('log l',"l.reftype = 'banish' AND l.refid = b.entryid")
			->orderBy('l.timestamp')
			->get('log__banish b',null,"b.reason, l.initiator, l.timestamp, 0 as action");
		if (!empty($Banishes)){
			$Unbanishes = $Database
				->where('target', $User['id'])
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

			$displayInitiator = Permission::Sufficient('staff');

			foreach ($Banishes as $b){
				$initiator = $displayInitiator ? User::Get($b['initiator']) : null;
				$b['reason'] = htmlspecialchars($b['reason']);
				echo "<li class=".strtolower($Actions[$b['action']])."><blockquote>{$b['reason']}</blockquote> - ".(isset($initiator)?User::GetProfileLink($initiator).' ':'').Time::Tag($b['timestamp'])."</li>";
			}
		}
			?></ul>
		</section>
	</div>
	<div id="settings"><?php
		if ($sameUser || Permission::Sufficient('staff')){ ?>
		<section class="guide-settings">
			<h2><?=$sameUser?User::$PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Color Guide</h2>
			<form action="/preference/set/cg_itemsperpage">
				<label>
					<span>Appearances per page</span>
					<input type="number" min="7" max="20" name="value" value="<?=UserPrefs::Get('cg_itemsperpage', $User['id'])?>" step="1"<?=!$sameUser?' disabled':''?>>
<?php       if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php       } ?>
				</label>
			</form>
<?php       if (Permission::Sufficient('staff', $User['role'])){ ?>
			<form action="/preference/set/cg_hidesynon">
				<label>
					<input type="checkbox" name="value" value="1"<?=UserPrefs::Get('cg_hidesynon', $User['id'])?' checked':''?> <?=!$sameUser?' disabled':''?>>
					<span>Hide synonym relations</span>
<?php           if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php           } ?>
				</label>
			</form>
<?php       } ?>
			<form action="/preference/set/cg_hideclrinfo">
				<label>
					<input type="checkbox" name="value" value="1"<?=UserPrefs::Get('cg_hideclrinfo', $User['id'])?' checked':''?> <?=!$sameUser?' disabled':''?>>
					<span>Hide color details on appearance pages</span>
<?php           if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php           } ?>
				</label>
			</form>
		</section>
		<section class="personal-settings">
			<h2><?=$sameUser?User::$PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Personal</h2>
<?php           if (Permission::Insufficient('developer', $User['role'])){ ?>
			<form action="/preference/set/p_disable_ga">
				<label>
					<input type="checkbox" name="value" value="1"<?=UserPrefs::Get('p_disable_ga', $User['id'])?' checked':''?> <?=!$sameUser?' disabled':''?>>
					<span>Don't associate my user ID with my on-site activity</span>
<?php               if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php               } ?>
				</label>
			</form>
<?php           } ?>
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
<?php           if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php           } ?>
				</label>
			</form>
			<form action="/preference/set/p_hidediscord">
				<label>
					<input type="checkbox" name="value" value="1"<?=UserPrefs::Get('p_hidediscord', $User['id'])?' checked':''?> <?=!$sameUser?' disabled':''?>>
					<span>Hide Discord server link from the sidebar</span>
<?php           if ($sameUser){ ?>
					<button class="save typcn typcn-tick green" disabled>Save</button>
<?php           } ?>
				</label>
			</form>
		</section>
		<section class="sessions">
			<h2><?=$sameUser?User::$PROFILE_SECTION_PRIVACY_LEVEL['staff']:''?>Sessions</h2>
<?php       if (isset($CurrentSession) || !empty($Sessions)){ ?>
			<p>Below is a list of all the browsers <?=$sameUser?"you've":'this user has'?> logged in from.</p>
			<ul class="session-list"><?php
				if (isset($CurrentSession)) User::RenderSessionLi($CurrentSession,CURRENT);
				if (!empty($Sessions)){
					foreach ($Sessions as $s) User::RenderSessionLi($s);
				}
			?></ul>
			<p><button class="typcn typcn-arrow-back yellow" id="signout-everywhere">Sign out everywhere</button></p>
<?php       } else { ?>
			<p><?=$sameUser?'You are':'This user is'?>n't logged in anywhere.</p>
<?php       } ?>
		</section>
<?php   }
		if ($sameUser){ ?>
		<section>
			<h2><?=$sameUser?User::$PROFILE_SECTION_PRIVACY_LEVEL['private']:''?>Unlink account</h2>
			<p>By unlinking your account you revoke this site's access to your account information. This will also log you out on every device where you're currently logged in. The next time you want to log in, you'll have to link your account again. This will not remove any of your <strong>public</strong> data from our site, it's still kept locally.</p>
	        <button id="unlink" class="orange typcn typcn-times">Unlink Account</button>
	    </section>
<?php   } ?></div>
<?php } ?>
</div>

<?php if ($canEdit){ ?>
<script>var ROLES = <?php
	$Echo = array();
	if ($canEdit){
		$_Roles = Permission::$ROLES_ASSOC;
		unset($_Roles['guest']);
		unset($_Roles['ban']);
		foreach ($_Roles as $name => $label){
			if (Permission::Insufficient($name, $currentUser['role']))
				continue;
			$Echo[$name] = $label;
		}
	}
	echo JSON::Encode($Echo);
?>;</script>
<?php }?>
