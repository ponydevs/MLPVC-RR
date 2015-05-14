<div id=content>
<?php if (isset($MSG)){ ?>
		<h1><?=$MSG?></h1>
<?php } else {
		echo get_avatar_wrap($User); ?>
	<h1><?=da_link($User,TEXT_ONLY)?></h1>
	<p><?php
		echo "<span>{$User['rolelabel']}</span>";
		if ($canEdit){
			?> <button id="change-role" class="blue typcn typcn-spanner" title="Change user's group"></button><?php
		}
	?></p>
<?php   if ($sameUser){ ?>
	<div class="notice warn">
		<label>Unlink account</label>
		<p>By unlinking your account you revoke this site's access to your account information. The next time you want to log in, you'll have to link your account again. Also, this will not remove any of your data from our site, all previously collected data is still kept locally.</p>
        <button id="unlink" class="typcn typcn-times">Unlink Account</button>
    </div>
<?php   }
	} ?>
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