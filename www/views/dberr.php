<?php
	$DIR = dirname(__FILE__);
	require "$DIR/../includes/Utils.php";
	$signedIn = false;
	$FTS = '?'.time();
	$customCSS = array("/css/theme.min.css$FTS");
	$view = 'dberr';
	require "$DIR/header.php"; ?>
<div id="content">
	<h1>Database connection error</h1>
	<p>Could not connect to database on <?=DB_HOST?></p>
	<?=Notice('info','<span class="typcn typcn-info-large"></span> The database of our website cannot be reached. Hopefully this is just a temporary issue and everything will be back to normal soon. Sorry for the inconvenience.',true)?>
	<?php
	$code = $e->getCode();
	$CODE_ERRORS = array(
		7 => 'PostgreSQL server is not running',
	);
	echo Notice('fail','<strong>Probable cause / debug information:</strong><pre><code>'.(isset($CODE_ERRORS[$code]) ? $CODE_ERRORS[$code] : $e->getMessage()).'</code></pre>',true)?>
</div>
<?php
	$customJS = array("/js/global.min.js$FTS","/js/moment.min.js$FTS","/js/dyntime.min.js$FTS");
	require "$DIR/footer.php"; ?>
