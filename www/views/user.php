<div id=content>
<?php if (isset($MSG)){ ?>
		<h1><?=$MSG?></h1>
<?php } else {
		echo get_avatar_wrap($User); ?>
	<h1><?=$User['name']?> <a class="da" title="Visit deviantArt profile" href="<?=da_link($User,LINK_ONLY)?>"><svg xmlns="http://www.w3.org/2000/svg" version="1" viewBox="0 0 100 167"><path d=" M100 0 L99.96 0 L99.95 0 L71.32 0 L68.26 3.04 L53.67 30.89 L49.41 33.35 L0 33.35 L0 74.97 L26.40 74.97 L29.15 77.72 L0 133.36 L0 166.5 L0 166.61 L0 166.61 L28.70 166.6 L31.77 163.55 L46.39 135.69 L50.56 133.28 L100 133.28 L100 91.68 L73.52 91.68 L70.84 89 L100 33.33 "></path>
                <image src="//st.deviantart.net/minish/main/logo/logo-mark.png"></image></svg></a></h1>
	<p><?php
		echo "<span>{$User['rolelabel']}</span>";
		if ($canEdit){
			?> <button id="change-role" class="blue typcn typcn-spanner" title="Change user's group"></button><?php
		}
	?></p>
	<div class="settings"><?php
		if ($sameUser || PERM('manager')){ ?>
		<section class="sessions">
			<label><!--Manage s-->Sessions</label>
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