<?php
	if ($do === 'da-auth' && isset($_GET['error'])){
		$err = $_GET['error'];
?>
<div class="notice fail align-center">
	<p>There was a(n) <strong><?=$err?></strong> error while trying to authenticate with deviantArt<?=isset($OAUTH_RESPONSE[$err])?"; {$OAUTH_RESPONSE[$err]}":'.'?></p>
<?php   if (!empty($_GET['error_description'])){ ?>
	<p>Additional details: <?=$_GET['error_description']?></p>
<?php   } ?>
</div>
<?php } ?>
<div class="content grid-70">
	<p>Please keep in mind that this is not a finished product, bugs may occur, things may break, and the design is still not complete, but it'll all be better, I promise!</p>
</div>
<div class="sidebar grid-30">
<?php include "views/sidebar.php"; ?>
</div>