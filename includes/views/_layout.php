<?php

use App\Auth;
use App\DeviantArt;
use App\JSON;
use App\Models\Appearance;
use App\Models\User;
use App\Permission;
use App\CoreUtils;
use App\View;

/** @var $view App\View */
/** @var $Owner User */
/** @var $User User */
/** @var $scope array */
/** @var $Appearance Appearance */

$Title = (isset($title)?$title.' - ':'').SITE_TITLE;
$Description = 'Handling requests, reservations & the Color Guide since 2015';

$ThumbImage = '/img/logo.png';
switch ($view->class ?? null){
	case 'colorguide':
		if (!empty($Appearance)){
			$sprite = $Appearance->getSpriteURL();
			if ($sprite)
				$ThumbImage = $sprite;

			if (isset($Owner))
				$Description = 'Colors for "'.$Appearance->label.'" from '.CoreUtils::posess($Owner->name).' Personal Color Guide on the the MLP-VectorClub\'s website';
			else $Description = 'Show accurate colors for "'.$Appearance->label.'" from the MLP-VectorClub\'s Official Color Guide';
		}
	break;
	case 'user':
		if (!empty($User)){
			$ThumbImage = $User->avatar_url;

			$Description = CoreUtils::posess($User->name).' profile on the MLP-VectorClub\'s website';
		}
	break;
	case 'episode':
		if (!empty($LinkedPost)){
			/** @var $LinkedPost \App\Models\Post */
			$_oldTitle = $Title;
			if (!$LinkedPost->finished)
				$ThumbImage = $LinkedPost->preview;
			else {
				$finishdeviation = DeviantArt::getCachedDeviation($LinkedPost->deviation_id);
				if (!empty($finishdeviation->preview))
					$ThumbImage  = $finishdeviation->preview;
			}
			$Title = $LinkedPost->label;
			if ($LinkedPost->is_request)
				$Description = 'A request';
			else $Description = "A reservation by {$LinkedPost->reserver->name}";
			$Description .= ' on the MLP-VectorClub\'s website';
		}
	break;
}
if ($ThumbImage[0] === '/')
	$ThumbImage = ABSPATH.ltrim($ThumbImage, '/');
$Title = CoreUtils::escapeHTML($Title);
$fatalErrorPage = defined('FATAL_ERROR'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?=$Title?></title>
	<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1, maximum-scale=1, user-scalable=no">
	<meta property="og:type" content="website">
	<meta property="og:image" content="<?=$ThumbImage?>">
	<meta property="og:title" content="<?=$Title?>">
	<meta property="og:url" content="<?=ABSPATH.ltrim($_SERVER['REQUEST_URI'], '/')?>">
	<meta property="og:description" content='<?=CoreUtils::aposEncode($Description)?>'>
	<meta name="description" content='<?=CoreUtils::aposEncode($Description)?>'>
	<meta name="format-detection" content="telephone=no">
	<meta name="theme-color" content="#2C73B1">
	<link rel="image_src" href="<?=$ThumbImage?>">

	<link rel="apple-touch-icon" sizes="57x57" href="/apple-touch-icon-57x57.png">
	<link rel="apple-touch-icon" sizes="60x60" href="/apple-touch-icon-60x60.png">
	<link rel="apple-touch-icon" sizes="72x72" href="/apple-touch-icon-72x72.png">
	<link rel="apple-touch-icon" sizes="76x76" href="/apple-touch-icon-76x76.png">
	<link rel="apple-touch-icon" sizes="114x114" href="/apple-touch-icon-114x114.png">
	<link rel="apple-touch-icon" sizes="120x120" href="/apple-touch-icon-120x120.png">
	<link rel="apple-touch-icon" sizes="144x144" href="/apple-touch-icon-144x144.png">
	<link rel="apple-touch-icon" sizes="152x152" href="/apple-touch-icon-152x152.png">
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon-180x180.png">
	<link rel="icon" type="image/png" href="/img/favicons-v1/favicon-32x32.png" sizes="32x32">
	<link rel="icon" type="image/png" href="/img/favicons-v1/android-chrome-192x192.png" sizes="192x192">
	<link rel="icon" type="image/png" href="/img/favicons-v1/favicon-96x96.png" sizes="96x96">
	<link rel="icon" type="image/png" href="/img/favicons-v1/favicon-16x16.png" sizes="16x16">
	<link rel="manifest" href="/manifest">
	<link rel="mask-icon" href="/img/favicons-v1/safari-pinned-tab.svg" color="#2c73b1">
	<meta name="apple-mobile-web-app-title" content="MLP-VectorClub">
	<meta name="application-name" content="MLP-VectorClub">
	<meta name="msapplication-TileColor" content="#2c73b1">
	<meta name="msapplication-TileImage" content="/img/favicons-v1/mstile-144x144.png">
	<meta name="msapplication-config" content="/browserconfig.xml">

	<link rel="shortcut icon" href="/favicon.ico">
<?php
	if (isset($norobots))
		echo "\t<meta name='robots' content='noindex'>\n";
	if (isset($customCSS)){
		foreach ($customCSS as $css)
			echo "\t<link rel='stylesheet' href='$css'>\n";
	}
	if (!empty(GA_TRACKING_CODE) && !$fatalErrorPage && Permission::insufficient('developer'))
		require INCPATH.'views/_ga.php'; ?>
</head>
<body>
	<header>
		<nav class="dragscroll"><ul>
			<li class="sidebar-toggle"></li>
			<?=CoreUtils::getNavigationHTML($fatalErrorPage)?>
		</ul><div id="to-the-top" class="typcn typcn-arrow-up"></div></nav>
	</header>

	<aside id="sidebar">
<?php include INCPATH.'views/_sidebar.php'; ?>
	</aside>

	<ol id="breadcrumbs" itemscope itemtype="http://schema.org/BreadcrumbList"><?=CoreUtils::getBreadcrumbsHTML($fatalErrorPage, $scope, $view ?? null)?></ol>

	<div id="main">
<?php  if (isset($view) && $view instanceof View)
		require $view;
	else echo $mainContent;
?>
	</div>

	<footer><?=CoreUtils::getFooter(isset($view) && $view === 'fatalerr')?></footer>

<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
<script src="https://polyfill.io/v2/polyfill.min.js?features=IntersectionObserver"></script>
<script src="https://ws.<?=$_SERVER['SERVER_NAME']?>:8667/socket.io/socket.io.js" id="wss"></script>
<?php
	echo CoreUtils::exportVars([
		'PRINTABLE_ASCII_PATTERN' => PRINTABLE_ASCII_PATTERN,
		'signedIn' => Auth::$signed_in,
	]);
	if (isset($customJS)) foreach ($customJS as $js){
		echo "<script src='$js'></script>\n";
	} ?>
</body>
</html>
