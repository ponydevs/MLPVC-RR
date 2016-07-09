<?php
	$DIR = dirname(__FILE__);
	$signedIn = false;
	header($_SERVER['SERVER_PROTOCOL']." 503 Service Unavailable");

	$customCSS = array("/css/theme.min.css");
	foreach ($customCSS as $k => $el)
		$customCSS[$k] .= '?'.filemtime(APPATH.substr($el,1));
	$view = 'fatalerr';
	require "$DIR/header.php"; ?>
<div id="content">
<?  switch($errcause){
		case "db": ?>
	<h1>Database connection error</h1>
	<p>Could not connect to database on <?=DB_HOST?></p>
<?php
			echo CoreUtils::Notice('info','<span class="typcn typcn-info-large"></span> The database of our website cannot be reached. Hopefully this is just a temporary issue and everything will be back to normal soon. Sorry for the inconvenience. <a class="send-feedback">Notify the developer</a>',true);
			$code = $e->getCode();
			$CODE_ERRORS = array(
				0 => 'The PDO PostgreSQL extension is not loaded/installed, check the PHP configuration',
				7 => 'The PostgreSQL database server is down',
			);
			echo CoreUtils::Notice('warn','<strong>Probable cause / debug information:</strong><pre><code>'.(isset($CODE_ERRORS[$code]) ? $CODE_ERRORS[$code] : "Error $code: ".$e->getMessage()).'</code></pre>',true);
		break;
		case "libmiss": ?>
	<h1>Missing runtime library</h1>
	<p>A required extension/library is missng</p>
<?php       echo CoreUtils::Notice('info','<span class="typcn typcn-info-large"></span> One of the site\'s core modules have not been installed yet. This usually happens after a software upgrade/reinstall and is just a temporary issue, no data has been lost and everything will be back to normal very soon. Sorry for the inconvenience. <a class="send-feedback">Notify the developer</a>',true);
			echo CoreUtils::Notice('warn','<strong>Probable cause / debug information:</strong><pre><code>'.$e->getMessage().'</code></pre>',true);
	break;
	} ?>
</div>
<?php
	CoreUtils::ExportVars(array('ServiceUnavailableError' => true));
	$customJS = array("/js/global.min.js","/js/moment.min.js","/js/dialog.min.js");
	foreach ($customJS as $k => $el)
		$customJS[$k] .= '?'.filemtime(APPATH.substr($el,1));
	require "$DIR/footer.php"; ?>
