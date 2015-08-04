<div id=content>
<?php
	if (isset($MSG)){
		echo "<h1>$MSG</h1>";
		if (isset($SubMSG)) echo "<p>$SubMSG</p>";
	}
	else {
		echo get_avatar_wrap($User); ?>
	<h1><?=$User['name']?> <a class="da" title="Visit DeviantArt profile" href="<?=da_link($User,LINK_ONLY)?>"><svg xmlns="http://www.w3.org/2000/svg" version="1" viewBox="0 0 100 167"><path d=" M100 0 L99.96 0 L99.95 0 L71.32 0 L68.26 3.04 L53.67 30.89 L49.41 33.35 L0 33.35 L0 74.97 L26.40 74.97 L29.15 77.72 L0 133.36 L0 166.5 L0 166.61 L0 166.61 L28.70 166.6 L31.77 163.55 L46.39 135.69 L50.56 133.28 L100 133.28 L100 91.68 L73.52 91.68 L70.84 89 L100 33.33 "></path><image src="//st.deviantart.net/minish/main/logo/logo-mark.png"></image></svg></a></h1>
	<p><?php
		echo "<span>{$User['rolelabel']}</span>";
		if ($canEdit){
			echo ' <button id="change-role" class="blue typcn typcn-spanner'.($User['role']==='ban'?' hidden':'').'" title="Change '.s($User['name']).' group"></button>';
			$BanLabel = ($User['role']==='ban'?'Un-ban':'Ban').'ish';
			$Icon = $User['role']==='ban'?'world':'weather-night';
			if (PERM('inspector', $User['role']))
				$Icon .= ' hidden';
			echo ' <button id="ban-toggle" class="darkblue typcn typcn-'.$Icon.' '.strtolower($BanLabel).'" title="'."$BanLabel user".'"></button>';
		}
	?></p>
	<div class="details">
<?php if (PERM('developer')){ ?>
		<section>
			<label>User ID:</label>
			<span><?=$User['id']?></span>
		</section>
<?php } ?>
		<section class="bans">
			<label>Banishment history</label>
			<ul><?php
		$Banishes = $Database
			->where('target', $User['id'])
			->join('log l',"l.reftype = 'banish' && l.refid = b.entryid")
			->orderBy('l.timestamp')
			->get('log__banish b',null,"b.reason, l.initiator, l.timestamp, 'Banish' as Action");
		if (!empty($Banishes)){
			$Unbanishes = $Database
				->where('target', $User['id'])
				->join('log l',"l.reftype = 'un-banish' && l.refid = b.entryid")
				->get('`log__un-banish` b',null,"b.reason, l.initiator, l.timestamp, 'Un-banish' as Action");
			if (!empty($Unbanishes)){
				$Banishes = array_merge($Banishes,$Unbanishes);
				usort($Banishes, function($a, $b){
					$a = strtotime($a['timestamp']);
					$b = strtotime($b['timestamp']);
					return $a > $b ? -1 : ($a < $b ? 1 : 0);
				});
				unset($Unbanishes);
			}

			$displayInitiator = PERM('inspector');

			foreach ($Banishes as $b){
				$initiator = $displayInitiator ? get_user($b['initiator']) : null;
				$b['reason'] = htmlspecialchars($b['reason']);
				echo "<li class=".strtolower($b['Action'])."><blockquote>{$b['reason']}</blockquote> - ".(isset($initiator)?profile_link($initiator).' ':'').timetag($b['timestamp'])."</li>";
			}
		}
			?></ul>
		</section>
	</div>
	<div class="settings"><?php
		if ($sameUser || PERM('manager')){ ?>
		<section class="sessions">
			<label>Sessions</label>
<?php       if (isset($CurrentSession) || !empty($Sessions)){ ?>
			<p>Below is a list of all the browsers where <?=$sameUser?"you've":'this user has'?> logged in from.</p>
			<ul class="session-list"><?php
				if (isset($CurrentSession)) render_session_li($CurrentSession,CURRENT);
				if (!empty($Sessions)){
					foreach ($Sessions as $s) render_session_li($s);
				}
			?></ul>
			<p><button class="typcn typcn-arrow-back yellow" id=signout-everywhere>Sign out everywhere</button></p>
<?php       } else { ?>
			<p><?=$sameUser?"You are":'This user is'?>n't logged in anywhere.</p>
<?php       } ?>
		</section>
<?php   }
		if ($sameUser){ ?>
		<section>
			<label>Unlink account</label>
			<p>By unlinking your account you revoke this site's access to your account information. This will also log you out on any device where you're currently logged in. The next time you want to log in, you'll have to link your account again. This will not remove any of your data from our site, all previously collected data is still kept locally.</p>
	        <button id="unlink" class="orange typcn typcn-times">Unlink Account</button>
	    </section>
<?php   } ?></div>
<?php } ?>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>

<?php if ($canEdit){ ?>
<script>var ROLES = <?php
	$Echo = array();
	foreach ($UsableRoles as $r)
		$Echo[$r['name']] = $r['label'];
	echo json_encode($Echo);
?>;</script>
<?php }?>
