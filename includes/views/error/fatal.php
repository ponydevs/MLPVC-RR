<?php // DO NOT USE SHORT OPEN TAGS IN THIS FILE - THIS DISPLAYS THE WARNING ABOUT THE OPTION BEING DISABLED
use App\CoreUtils;
use App\HTTP;
use App\Time;
/** @var $e Exception */
ob_start();
HTTP::statusCode(503);

function strip_trace(string $msg):string {
	return rtrim(preg_replace(new \App\RegExp('Stack trace.*$','is'),'',str_replace(PROJPATH,'~/',$msg)));
}

$customCSS = ['/scss/min/theme.css'];
foreach ($customCSS as $k => $el)
	$customCSS[$k] .= '?'.filemtime(APPATH.mb_substr($el,1));
$scope = []; ?>
<div id="content">
<?php
switch($errcause){
	case 'db': ?>
	<h1>Database connection error</h1>
	<p>Could not connect to database on <?=DB_HOST?></p>
<?php
		echo CoreUtils::notice('info','<span class="typcn typcn-info-large"></span> The database of our website cannot be reached. Hopefully this is just a temporary issue and everything will be back to normal soon. Sorry for the inconvenience. <a class="send-feedback">Notify the developer</a>',true);
			echo CoreUtils::notice('warn','<strong>Probable cause / debug information:</strong><pre><code>'.strip_trace($e->getMessage()).'</code></pre>',true);
	break;
	case 'libmiss': ?>
	<h1>Configuration problem</h1>
	<p>A required extension/setting is missng</p>
<?php   echo CoreUtils::notice('info','<span class="typcn typcn-info-large"></span> One of the site\'s core modules have not been installed yet. This usually happens after a software upgrade/reinstall and is just a temporary issue, no data has been lost and everything will be back to normal very soon. Sorry for the inconvenience. <a class="send-feedback">Notify the developer</a>',true);
		echo CoreUtils::notice('warn','<strong>Probable cause / debug information:</strong><pre><code>'.strip_trace($e->getMessage()).'</code></pre>',true);
	break;
	case 'maintenance': ?>
	<h1>Website Maintenance</h1>
<?php if (defined('MAINTENANCE_START')){ ?>
	<p>Started <?=Time::tag(MAINTENANCE_START)?>
<?php }
	echo CoreUtils::notice('info','<span class="typcn typcn-info-large"></span> The developer is currently performing some actions that requre the site to be temporarily offline. We\'ll be back up and running as soon as possible, thank you for your understanding.',true);
	break;
	} ?>
</div>
<?php
echo CoreUtils::exportVars(['ServiceUnavailableError' => true]);
$customJS = ['/js/min/moment.js', '/js/min/shared-utils.js', '/js/min/global.js', '/js/min/dialog.js'];
foreach ($customJS as $k => $el)
	$customJS[$k] .= '?'.filemtime(APPATH.mb_substr($el,1));
$mainContent = ob_get_clean();
// Since we're setting the content explicitly we don't want any views to load
unset($view);
define('FATAL_ERROR', true);
require INCPATH.'views/_layout.php';
