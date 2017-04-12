<?php // DO NOT USE SHORT OPEN TAGS IN THIS FILE - THIS DISPLAYS THE WARNING ABOUT THE OPTION BEING DISABLED
use App\CoreUtils;
use App\HTTP;
use App\Time;
$signedIn = false;
HTTP::statusCode(503);

// TODO Add *that* image of the club mascot above <h1>

$customCSS = array("/scss/min/theme.css");
foreach ($customCSS as $k => $el)
	$customCSS[$k] .= '?'.filemtime(APPATH.CoreUtils::substring($el,1));
$view = 'fatalerr';
$scope = [];
require "header.php"; ?>
<div id="content">
<?php
switch($errcause){
	case "db": ?>
	<h1>Database connection error</h1>
	<p>Could not connect to database on <?=DB_HOST?></p>
<?php
		echo CoreUtils::notice('info','<span class="typcn typcn-info-large"></span> The database of our website cannot be reached. Hopefully this is just a temporary issue and everything will be back to normal soon. Sorry for the inconvenience. <a class="send-feedback">Notify the developer</a>',true);
			echo CoreUtils::notice('warn','<strong>Probable cause / debug information:</strong><pre><code>'.$e->getMessage().'</code></pre>',true);
	break;
	case "libmiss": ?>
	<h1>Configuration problem</h1>
	<p>A required extension/setting is missng</p>
<?php   echo CoreUtils::notice('info','<span class="typcn typcn-info-large"></span> One of the site’s core modules have not been installed yet. This usually happens after a software upgrade/reinstall and is just a temporary issue, no data has been lost and everything will be back to normal very soon. Sorry for the inconvenience. <a class="send-feedback">Notify the developer</a>',true);
		echo CoreUtils::notice('warn','<strong>Probable cause / debug information:</strong><pre><code>'.$e->getMessage().'</code></pre>',true);
	break;
	case "maintenance": ?>
	<h1>Website Maintenance</h1>
<?php if (defined('MAINTENANCE_START')){ ?>
	<p>Started <?=Time::tag(MAINTENANCE_START)?>
<?php }
	echo CoreUtils::notice('info','<span class="typcn typcn-info-large"></span> The developer is currently performing some actions that requre the site to be temporarily offline. We\'ll be back up and running as soon as possible, thank you for your understanding.',true);
	break;
	} ?>
</div>
<?php
echo CoreUtils::exportVars(array('ServiceUnavailableError' => true));
$customJS = array("/js/min/moment.js","/js/min/global.js","/js/min/dialog.js");
foreach ($customJS as $k => $el)
	$customJS[$k] .= '?'.filemtime(APPATH.CoreUtils::substring($el,1));
require "footer.php";
